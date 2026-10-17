<?php

namespace Tests\Feature;

use App\Models\Product;
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
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertSame(0, Product::count());
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('product_changes'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('products'));
        $this->assertFalse(Schema::hasTable('product_prices'));
        $this->assertFalse(Schema::hasTable('product_terms'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('api_idempotency_keys'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('product_changes'));
        $this->assertTrue(Schema::hasTable('product_prices'));
        $this->assertTrue(Schema::hasTable('product_terms'));
        $this->assertSame(0, Product::count());
    }
}
