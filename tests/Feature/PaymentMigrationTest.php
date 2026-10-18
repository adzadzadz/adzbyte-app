<?php

namespace Tests\Feature;

use App\Actions\Orders\OrderActions;
use App\Actions\Payments\PaymentAttemptActions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesPaymentOrder;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentMigrationTest extends TestCase
{
    use CreatesPaymentOrder, UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_090000_create_payment_attempts.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->createPaymentOrder();
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_order_upgrade_empty_reversal_reapply_and_populated_payment_fail_closed(): void
    {
        $actions = app(OrderActions::class);
        $before = $actions->read($this->customer, $this->order->public_id)->currentRevision->toArray();
        $migration = require database_path('migrations/2026_10_18_090000_create_payment_attempts.php');
        $migration->up();
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertEquals($before, $actions->read($this->customer, $this->order->public_id)->currentRevision->toArray());
        $migration->down();
        $this->assertFalse(Schema::hasTable('payment_attempts'));
        $this->assertFalse(Schema::hasColumn('orders', 'payment_attempt_id'));
        $migration->up();
        config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only']);
        Http::preventStrayRequests();
        Http::fake(['*/capabilities/payment_methods' => Http::response(['qrph']), '*/v2/checkout_sessions' => Http::response([], 503)]);
        $attempt = app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 2, (string) Str::uuid());
        $this->assertSame('unknown', $attempt->state);
        try {
            $migration->down();
            $this->fail('Destructive payment reversal succeeded.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retain payment history', $exception->getMessage());
        }
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->assertSame($attempt->id, $this->order->fresh()->payment_attempt_id);
        $this->assertEquals($before, $actions->read($this->customer, $this->order->public_id)->currentRevision->toArray());
    }
}
