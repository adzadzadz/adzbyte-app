<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CatalogMigrationTest extends TestCase
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

    public function test_catalog_and_audit_apply_rollback_and_reapply_preserve_prior_tables(): void
    {
        $this->migrateCatalog();
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertSame(0, Product::count());
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $price = ProductPrice::factory()->create(['product_id' => $product->id]);
        $terms = ProductTerms::factory()->create(['product_id' => $product->id]);
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('product_categories'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('product_changes'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue($product->is(Product::findOrFail($product->id)));
        $this->assertSame($price->amount_minor, $price->fresh()->amount_minor);
        $this->assertSame($terms->content, $terms->fresh()->content);
        // Upgrade a populated C1.1 schema to C1.2, preserving prior records.
        $this->migrateCatalog();
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertTrue($product->is(Product::findOrFail($product->id)));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('product_categories'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('products'));
        $this->assertFalse(Schema::hasTable('product_prices'));
        $this->assertFalse(Schema::hasTable('product_terms'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('api_idempotency_keys'));
        $this->migrateCatalog();
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertTrue(Schema::hasTable('product_prices'));
        $this->assertTrue(Schema::hasTable('product_terms'));
        $this->assertSame(0, Product::count());
        $this->assertSame($user->email, $user->fresh()->email);
    }

    private function migrateCatalog(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) <= '2026_10_18_020000_add_product_categories.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
    }
}
