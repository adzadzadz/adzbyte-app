<?php

namespace Tests\Feature;

use App\Models\PackageCheckout;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CustomerPortalMigrationTest extends TestCase
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

    public function test_live_package_ledger_and_encrypted_customer_data_survive_all_pending_portal_migrations(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_01' || str_contains(basename($path), 'package_')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $package = PackageCheckout::create([
            'operation_id' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'proof_hash' => str_repeat('b', 64),
            'package_code' => 'starter', 'package_name' => 'Starter', 'amount_minor' => 77700, 'currency' => 'PHP', 'quantity' => 1,
            'offer_version' => '2026-10-22', 'business_name' => 'Synthetic preserved customer', 'email' => 'preserved@example.test',
            'provider_mode' => 'test', 'status' => 'paid', 'creation_phase' => 'complete',
        ]);
        $before = $package->fresh()->getAttributes();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertEquals($before, $package->fresh()->getAttributes());
        $this->assertSame('preserved@example.test', $package->fresh()->email);
        $this->assertTrue(Schema::hasTable('work_items'));
        $this->assertTrue(Schema::hasTable('support_cases'));
        $this->assertDatabaseCount('package_checkouts', 1);
        $this->assertDatabaseCount('package_purchase_deliveries', 0);
    }

    public function test_upgrade_empty_reverse_and_reapply_preserve_prior_tables_and_populated_history_fails_closed(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_23_000000_create_customer_portal.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $customer = User::factory()->create();
        $before = $customer->getAttributes();
        $migration = require database_path('migrations/2026_10_23_000000_create_customer_portal.php');
        $migration->up();
        $this->assertTrue(Schema::hasTable('work_items'));
        $migration->down();
        $this->assertFalse(Schema::hasTable('work_items'));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertEquals($before, $customer->fresh()->getAttributes());
        $migration->up();
        WorkItem::create(['public_id' => (string) Str::ulid(), 'user_id' => $customer->id, 'type' => 'service', 'title' => 'Preserved service', 'summary' => 'Retained history']);
        try {
            $migration->down();
            $this->fail('Populated history was dropped');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Retain portal history', $e->getMessage());
        }
        $this->assertDatabaseCount('work_items', 1);
        $this->assertEquals($before, $customer->fresh()->getAttributes());
        $this->assertDatabaseCount('orders', 0);
    }
}
