<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class ProductCategoryMigrationTest extends TestCase
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

    public function test_category_upgrade_and_reversal_preserve_catalog_versions_and_prior_tables(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_020000_add_product_categories.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $price = ProductPrice::factory()->create(['product_id' => $product->id]);
        $terms = ProductTerms::factory()->create(['product_id' => $product->id]);
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_10_18_020000_add_product_categories.php'], '--force' => true])->assertSuccessful();
        $this->assertNull($product->fresh()->category_id);
        $category = ProductCategory::create(['name' => 'Synthetic migration category', 'limit_one_per_cart' => true]);
        $product->update(['category_id' => $category->id]);
        $this->assertTrue($product->fresh()->category->is($category));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('product_categories'));
        $this->assertFalse(Schema::hasColumn('products', 'category_id'));
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertSame($product->name, $product->fresh()->name);
        $this->assertSame($price->amount_minor, $price->fresh()->amount_minor);
        $this->assertSame($terms->content, $terms->fresh()->content);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_10_18_020000_add_product_categories.php'], '--force' => true])->assertSuccessful();
        $this->assertNull($product->fresh()->category_id);
        $this->assertDatabaseCount('product_categories', 0);
        $this->assertTrue(Schema::hasTable('product_category_changes'));
    }
}
