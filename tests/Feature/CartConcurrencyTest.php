<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Carts\CartPrincipal;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartConcurrencyTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
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

    public function test_identical_create_retries_commit_one_cart_and_receipt(): void
    {
        $results = $this->race('create', sameProduct: true, sameOperation: true);
        $this->assertSame(['passed'], array_values(array_unique(array_column($results, 'status'))));
        $this->assertCount(1, array_unique(array_column($results, 'reference')));
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_changes', 1);
    }

    #[DataProvider('addScenarios')]
    public function test_cart_mutations_serialize_without_losing_items_or_bypassing_limits(bool $sameProduct, bool $sameOperation, bool $restricted, int $lines, int $successes, int $receipts): void
    {
        $results = $this->race('add', $sameProduct, $sameOperation, $restricted);
        $this->assertCount($successes, array_filter($results, fn ($result) => $result['status'] === 'passed'));
        foreach (array_filter($results, fn ($result) => $result['status'] === 'rejected') as $rejected) {
            $this->assertSame(['product'], $rejected['fields']);
        }
        $this->assertDatabaseCount('cart_items', $lines);
        $this->assertDatabaseCount('cart_changes', $receipts);
        $this->assertSame($receipts - 1, Cart::firstOrFail()->revision);
    }

    public static function addScenarios(): array
    {
        return [
            'identical retry' => [true, true, false, 1, 4, 2],
            'different commands same product' => [true, false, false, 1, 4, 5],
            'distinct regular products' => [false, false, false, 4, 4, 5],
            'restricted category contention' => [false, false, true, 1, 1, 2],
        ];
    }

    private function race(string $command, bool $sameProduct, bool $sameOperation, bool $restricted = false): array
    {
        $secret = bin2hex(random_bytes(32));
        $principal = CartPrincipal::guest($secret);
        $cart = $command === 'create' ? null : app(CartActions::class)->create($principal, (string) Str::uuid());
        $category = $restricted ? ProductCategory::create(['name' => 'Synthetic contention category', 'limit_one_per_cart' => true]) : null;
        $products = [];
        if ($command === 'add') {
            for ($i = 0; $i < ($sameProduct ? 1 : 4); $i++) {
                $product = Product::factory()->published()->create(['category_id' => $category?->id]);
                $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
                $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic concurrent scope']]);
                $product->is_available = true;
                $product->save();
                $products[] = $product->public_id;
            }
        }
        $operation = (string) Str::uuid();
        $barrier = $this->temporary.'.go';
        for ($i = 0; $i < 4; $i++) {
            $worker = new Process([
                PHP_BINARY, base_path('tests/Support/cart-command.php'), $this->database, $secret, $command,
                $cart['public_id'] ?? '', $products[$sameProduct ? 0 : $i] ?? '',
                $sameOperation ? $operation : (string) Str::uuid(), $barrier,
            ], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(20);
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
            $this->assertTrue(json_validate($worker->getOutput()), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
