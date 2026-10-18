<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\OrderActions;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
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
        }
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    #[DataProvider('scenarios')]
    public function test_four_independent_submissions_create_one_accepted_order(string $scenario): void
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
        $input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic scope'], 'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'], 'accept_terms' => true,
            'fingerprint' => app(OrderActions::class)->review($customer)['fingerprint']];
        $operation = (string) Str::uuid();
        $barrier = $this->temporary.'.order-go';
        foreach (range(0, 3) as $i) {
            $current = $input;
            if ($scenario === 'different_input') {
                $current['brief']['goal'] .= ' '.$i;
            }
            $worker = new Process([PHP_BINARY, base_path('tests/Support/order-command.php'), $this->database, (string) $customer->id,
                json_encode($current, JSON_THROW_ON_ERROR), $scenario === 'retry' ? $operation : (string) Str::uuid(), $barrier,
            ], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(25);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 4 && microtime(true) < $deadline) {
            foreach ($this->workers as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }
            usleep(1000);
        }
        $this->assertCount(4, glob($barrier.'.*.ready'));
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $passed = array_values(array_filter($results, fn ($result) => $result['status'] === 'passed'));
        $this->assertCount($scenario === 'different_input' ? 1 : 4, $passed);
        $this->assertCount(1, array_unique(array_column($passed, 'reference')));
        $this->assertSame(['12500'], array_values(array_unique(array_column($passed, 'total'))));
        $this->assertSame([1], array_values(array_unique(array_column($passed, 'lines'))));
        if ($scenario === 'different_input') {
            $this->assertCount(3, array_filter($results, fn ($result) => $result['status'] === 'denied' && $result['code'] === 409));
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_revisions', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('order_events', $scenario === 'new_uuid' ? 4 : 1);
        $this->assertSame('unpaid', Order::sole()->payment_status);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public static function scenarios(): array
    {
        return [['retry'], ['new_uuid'], ['different_input']];
    }
}
