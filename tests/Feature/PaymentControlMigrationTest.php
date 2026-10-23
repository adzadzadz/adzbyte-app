<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentControlActions;
use App\Models\CommercePolicyVersion;
use App\Services\Orders\CommerceRules;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentControlMigrationTest extends TestCase
{
    use CreatesPaymentAttempt, UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_18_120000_create_payment_controls.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->installVisibilitySchemaForLegacyFixture();
        $legacy = CommerceRules::defaults();
        unset($legacy['payment']);
        $policy = (new CommercePolicyVersion)->forceFill(['version' => 1, 'operation_id' => (string) Str::uuid(), 'content' => $legacy]);
        $policy->save();
        DB::table('commerce_settings')->where('id', 1)->update(['current_version_id' => $policy->id]);
        $this->createPaymentAttempt();
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_populated_upgrade_keeps_legacy_snapshot_and_empty_reversal_reapply_then_protects_control_history(): void
    {
        $accepted = $this->order->currentRevision->fresh()->getRawOriginal();
        $attempt = $this->attempt->fresh()->getRawOriginal();
        $this->assertArrayNotHasKey('payment', $this->order->currentRevision->policy_snapshot);
        $migration = require database_path('migrations/2026_10_18_120000_create_payment_controls.php');
        $migration->up();
        $this->assertSame($accepted, $this->order->currentRevision->fresh()->getRawOriginal());
        $this->assertSame($attempt, $this->attempt->fresh()->getRawOriginal());
        $migration->down();
        $this->assertFalse(Schema::hasTable('payment_controls'));
        $migration->up();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $state = 'active';
        Http::fake(function ($request) use (&$state) {
            if ($request->method() === 'POST') {
                $state = 'expired';
            }

            return Http::response(['data' => ['id' => 'cs_signed', 'type' => 'checkout_session', 'attributes' => [
                ...$this->attempt->provider_payload['data']['attributes'], 'livemode' => false, 'status' => $state,
                'payments' => [], 'payment_intent' => null, 'checkout_url' => 'https://checkout.paymongo.com/signed',
            ]]]);
        });
        $command = app(PaymentControlActions::class)->start($this->customer, $this->order->public_id, 'cancel', ['expected_revision' => 3], (string) Str::uuid());
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame($accepted, $this->order->currentRevision->fresh()->getRawOriginal());
        try {
            $migration->down();
            $this->fail('Payment control history removed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Retain payment control commands', $error->getMessage());
        }
        $this->assertDatabaseCount('payment_controls', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }
}
