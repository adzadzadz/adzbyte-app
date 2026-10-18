<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartHandoffMigrationTest extends TestCase
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

    public function test_transfer_upgrade_rollback_reapply_preserves_cart_catalog_identity_and_retry_history(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_040000_create_cart_handoffs.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $service = User::factory()->create();
        $issuer = $service->createToken('storefront:upgrade', StorefrontCredential::abilities())->accessToken;
        $category = ProductCategory::create(['name' => 'Synthetic retained category', 'limit_one_per_cart' => true]);
        $product = Product::factory()->published()->create(['category_id' => $category->id]);
        $price = $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $terms = $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();
        $principal = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = app(CartActions::class)->create($principal, (string) Str::uuid());
        app(CartActions::class)->add($principal, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('cart_handoffs', 0);
        $operation = (string) Str::uuid();
        $link = app(CartHandoffActions::class)->mint($principal, $cart['public_id'], $issuer, $operation);
        $this->assertDatabaseCount('cart_handoffs', 1);
        $this->assertDatabaseCount('cart_changes', 3);
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('cart_handoffs'));
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('cart_changes', 3);
        $this->assertSame($price->amount_minor, $price->fresh()->amount_minor);
        $this->assertSame($terms->content, $terms->fresh()->content);
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame($service->email, $service->fresh()->email);
        $this->assertSame($issuer->id, $issuer->fresh()->id);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('cart_handoffs', 0);
        try {
            app(CartHandoffActions::class)->mint($principal, $cart['public_id'], $issuer, $operation);
            $this->fail('A reversed transfer was revived from an old operation UUID.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $fresh = app(CartHandoffActions::class)->mint($principal, $cart['public_id'], $issuer, (string) Str::uuid());
        $this->assertNotSame($link['url'], $fresh['url']);
        $this->assertCount(1, app(CartActions::class)->read($principal, $cart['public_id'])['lines']);
    }
}
