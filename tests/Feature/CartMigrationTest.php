<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_catalog_survives_cart_upgrade_rollback_and_reapply(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_030000_create_carts.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $user = User::factory()->create();
        $category = ProductCategory::create(['name' => 'Synthetic retained category', 'limit_one_per_cart' => true]);
        $product = Product::factory()->published()->create(['category_id' => $category->id]);
        $price = $product->prices()->create(['version' => 1, 'amount_minor' => 10000, 'currency' => 'PHP']);
        $terms = $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('carts', 0);
        $principal = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = app(CartActions::class)->create($principal, (string) Str::uuid());
        app(CartActions::class)->add($principal, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('cart_changes', 2);
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        foreach (['carts', 'cart_items', 'cart_changes'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame($category->public_id, $category->fresh()->public_id);
        $this->assertSame($price->amount_minor, $price->fresh()->amount_minor);
        $this->assertSame($terms->content, $terms->fresh()->content);
        $this->assertSame($user->email, $user->fresh()->email);
        foreach (['product_changes', 'product_category_changes', 'api_idempotency_keys'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('cart_changes', 0);
        $this->assertSame($category->id, $product->fresh()->category_id);
    }
}
