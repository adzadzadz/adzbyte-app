<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Models\OrderProposal;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class OrderReviewConcurrencyTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }$this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    #[DataProvider('scenarios')]
    public function test_review_commands_are_serialized_across_independent_processes(string $scenario): void
    {
        $customer = $this->customerCart();
        $submission = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true, 'fingerprint' => app(OrderActions::class)->review($customer)['fingerprint']];
        $order = app(OrderActions::class)->submit($customer, $submission, (string) Str::uuid());
        $admin = $this->admin();
        $actions = app(OrderReviewActions::class);
        if ($scenario === 'expiry') {
            $order = $actions->staff($admin, $order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid());
            // Test-only fixture: emulate an elapsed captured approval.
            DB::table('orders')->where('id', $order->id)->update(['approval_expires_at' => now()->subSecond()]);
        }
        if ($scenario === 'accept_vs_propose') {
            $order = $actions->staff($admin, $order->public_id, 'propose', ['expected_revision' => 1, 'message' => 'Synthetic revised offer'], (string) Str::uuid());
        }
        $barrier = $this->temporary.'.review-go';
        $uuid = (string) Str::uuid();
        foreach (range(0, 3) as $i) {
            $actor = $scenario === 'distinct_admins' ? $this->admin() : $admin;
            $command = ['mode' => 'staff', 'action' => 'approve', 'input' => ['expected_revision' => 1]];
            if ($scenario === 'expiry') {
                $command = ['mode' => 'expiry'];
            }
            if ($scenario === 'accept_vs_propose') {
                if ($i % 2 === 0) {
                    $actor = $customer;
                    $command = ['mode' => 'customer', 'action' => 'accept_proposal', 'input' => ['expected_revision' => 2, 'proposal' => OrderProposal::sole()->public_id, 'brief' => $submission['brief'], 'billing' => $submission['billing'], 'accept_terms' => true]];
                } else {
                    $actor = $this->admin();
                    $command = ['mode' => 'staff', 'action' => 'propose', 'input' => ['expected_revision' => 2, 'message' => 'Competing revised offer']];
                }
            }
            $worker = new Process([PHP_BINARY, base_path('tests/Support/order-review-command.php'), $this->database, (string) $actor->id, $order->public_id, json_encode($command, JSON_THROW_ON_ERROR), $scenario === 'retry' ? $uuid : (string) Str::uuid(), $barrier], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(30);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 4 && microtime(true) < $deadline) {
            foreach ($this->workers as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }usleep(1000);
        }
        $this->assertCount(4, glob($barrier.'.*.ready'));
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        if ($scenario === 'expiry') {
            $this->assertCount(1, array_filter($results, fn ($r) => $r['expired']));
            $this->assertSame('expired', $order->fresh()->approval_status);
            $this->assertDatabaseCount('order_events', 3);
        } else {
            $passed = array_values(array_filter($results, fn ($r) => $r['status'] === 'passed'));
            $this->assertCount($scenario === 'retry' ? 4 : 1, $passed);
            $this->assertCount($scenario === 'retry' ? 0 : 3, array_filter($results, fn ($r) => $r['status'] === 'denied' && $r['code'] === 409));
            $this->assertSame($scenario === 'accept_vs_propose' ? 3 : 2, $order->fresh()->revision);
            $this->assertDatabaseCount('order_events', $scenario === 'accept_vs_propose' ? 3 : 2);
            if ($scenario === 'accept_vs_propose') {
                $order = $order->fresh();
                if ($order->approval_status === 'pending') {
                    $this->assertNull($order->pending_proposal_id);
                    $this->assertDatabaseCount('order_revisions', 2);
                    $this->assertDatabaseCount('order_proposals', 1);
                    $this->assertSame('12500', app(OrderActions::class)->read($customer, $order->public_id)->currentRevision->total_minor);
                } else {
                    $this->assertSame('clarification', $order->approval_status);
                    $this->assertNotNull($order->pending_proposal_id);
                    $this->assertDatabaseCount('order_revisions', 1);
                    $this->assertDatabaseCount('order_proposals', 2);
                }
            }
        }
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('cart_items', 1);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $user->givePermissionTo(array_column(OrderCapability::cases(), 'value'));

        return $user;
    }

    private function customerCart(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic concurrent scope']]);
        $product->is_available = true;
        $product->save();
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $carts = app(CartActions::class);
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($customer, $guest, $cart['public_id']);
        $carts->attach($customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);

        return $customer;
    }

    public static function scenarios(): array
    {
        return [['retry'], ['distinct_admins'], ['expiry'], ['accept_vs_propose']];
    }
}
