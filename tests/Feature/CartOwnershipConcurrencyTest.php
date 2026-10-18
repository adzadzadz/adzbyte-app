<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CustomerCart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartOwnershipConcurrencyTest extends TestCase
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
    public function test_four_independent_claims_preserve_ownership_and_customer_choice(string $scenario): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Customer->value);
        $actions = app(CartActions::class);
        $category = $scenario === 'restricted' ? ProductCategory::create(['name' => 'Synthetic restricted', 'limit_one_per_cart' => true]) : null;
        $sameGuest = in_array($scenario, ['retry', 'cross_actor'], true);
        $sameProduct = $scenario === 'duplicates';
        $secret = bin2hex(random_bytes(32));
        $principal = CartPrincipal::guest($secret);
        $product = $this->offer($category);
        $source = $actions->create($principal, (string) Str::uuid())['public_id'];
        $actions->add($principal, $source, $product->public_id, (string) Str::uuid());
        $preview = $actions->mergePreview($actor, $principal, $source);
        $operation = (string) Str::uuid();
        $barrier = $this->temporary.'.claim-go';
        for ($i = 0; $i < 4; $i++) {
            $currentActor = $actor;
            if ($scenario === 'cross_actor' && $i > 0) {
                $currentActor = User::factory()->create();
                $currentActor->assignRole(UserRole::Customer->value);
            }
            if (! $sameGuest && $i > 0) {
                $secret = bin2hex(random_bytes(32));
                $principal = CartPrincipal::guest($secret);
                $source = $actions->create($principal, (string) Str::uuid())['public_id'];
                $product = $sameProduct ? $product : $this->offer($category);
                $actions->add($principal, $source, $product->public_id, (string) Str::uuid());
            }
            $payload = ['actor' => $currentActor->id];
            if ($sameGuest) {
                $payload['fingerprint'] = $preview['fingerprint'];
            }
            $worker = new Process([PHP_BINARY, base_path('tests/Support/cart-command.php'), $this->database, $secret,
                $sameGuest ? 'claim' : 'claim_current', $source, json_encode($payload, JSON_THROW_ON_ERROR),
                $sameGuest ? $operation : (string) Str::uuid(), $barrier,
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
        $this->assertDatabaseCount('customer_carts', 1);
        $target = Cart::findOrFail(CustomerCart::sole()->cart_id);
        $passed = array_values(array_filter($results, fn ($result) => $result['status'] === 'passed'));
        if ($scenario === 'cross_actor') {
            $this->assertCount(1, $passed);
            $this->assertCount(3, array_filter($results, fn ($result) => $result['status'] === 'denied' && $result['code'] === 404));
            $this->assertSame(1, $target->items()->count());
            $this->assertSame(1, $target->changes()->where('action', 'attach')->count());
        } elseif ($scenario === 'restricted') {
            $this->assertCount(1, $passed);
            $this->assertCount(3, array_filter($results, fn ($result) => $result['status'] === 'choice_required'));
            $this->assertSame(1, $target->items()->count());
            $this->assertSame(3, Cart::whereNull('user_id')->whereNull('merged_into_cart_id')->count());
            $this->assertDatabaseCount('cart_items', 4); // No conflicting selection silently removed.
        } else {
            $this->assertCount(4, $passed);
            $this->assertCount(1, array_unique(array_column($passed, 'reference')));
            $this->assertSame($scenario === 'regular' ? 4 : 1, $target->items()->count());
            $this->assertSame(1, $target->changes()->where('action', 'attach')->count());
        }
    }

    public static function scenarios(): array
    {
        return [['retry'], ['cross_actor'], ['regular'], ['duplicates'], ['restricted']];
    }

    private function offer(?ProductCategory $category): Product
    {
        $product = Product::factory()->published()->create(['category_id' => $category?->id]);
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();

        return $product;
    }
}
