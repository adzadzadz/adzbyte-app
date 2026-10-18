<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentEventActions;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentEventMigrationTest extends TestCase
{
    use CreatesPaymentAttempt, UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_100000_create_payment_events.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->createPaymentAttempt();
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_order_upgrade_empty_reversal_reapply_and_populated_payment_fail_closed(): void
    {
        $before = $this->attempt->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_18_100000_create_payment_events.php');
        $migration->up();
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertEquals($before, $this->attempt->fresh()->getRawOriginal());
        $migration->down();
        $this->assertFalse(Schema::hasTable('payment_events'));
        $this->assertFalse(Schema::hasTable('payment_settlements'));
        $migration->up();
        $payload = PaymentEventFixture::payload($this->attempt);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $event = app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
        $this->assertSame('applied', $event->state);
        try {
            $migration->down();
            $this->fail('Destructive payment event reversal succeeded.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Retain provider events', $exception->getMessage());
        }
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame($before['provider_payload'], $this->attempt->fresh()->getRawOriginal('provider_payload'));
    }
}
