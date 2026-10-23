<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentEventActions;
use App\Models\PaymentResolution;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentResolutionMigrationTest extends TestCase
{
    use CreatesPaymentAttempt, UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_110000_create_payment_resolutions.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->installVisibilitySchemaForLegacyFixture();
        $this->createPaymentAttempt();
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_payment_upgrade_empty_reversal_reapply_and_resolution_retention(): void
    {
        $body = json_encode(PaymentEventFixture::payload($this->attempt), JSON_THROW_ON_ERROR);
        $event = app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
        $before = $event->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_18_110000_create_payment_resolutions.php');
        $migration->up();
        $this->assertEquals($before, $event->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payment_settlements', 1);
        $migration->down();
        $this->assertFalse(Schema::hasTable('payment_resolutions'));
        $migration->up();
        $resolution = (new PaymentResolution)->forceFill(['public_id' => (string) Str::ulid(), 'payment_event_id' => $event->id, 'payment_attempt_id' => $this->attempt->id,
            'actor_id' => $this->admin->id, 'operation_id' => (string) Str::uuid(), 'request_hash' => str_repeat('0', 64),
            'original_state' => 'rejected', 'original_reason' => 'session_mismatch', 'note' => 'Synthetic retained resolution', 'verification' => ['livemode' => false]]);
        $resolution->save();
        try {
            $migration->down();
            $this->fail('Audited resolution history was removed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Retain audited payment resolutions', $error->getMessage());
        }
        $this->assertDatabaseCount('payment_resolutions', 1);
        $this->assertEquals($before, $event->fresh()->getRawOriginal());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }
}
