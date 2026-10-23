<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\DeliverPackagePurchase;
use App\Models\PackageCheckout;
use App\Models\PackagePurchaseDelivery;
use App\Models\User;
use App\Services\PackageCheckout\PackageCheckouts;
use App\Services\PackageCheckout\PackagePurchaseDeliveries;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PackageCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'synthetic-package-integration-token-only';

    private const PROOF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'package_checkout.enabled' => true, 'package_checkout.api_token' => self::TOKEN,
            'package_checkout.mode' => 'test', 'package_checkout.secret_key' => 'sk_test_synthetic123456',
            'package_checkout.webhook_secret' => 'whsk_synthetic_webhook_secret_only']);
        $this->travelTo(CarbonImmutable::parse('2026-10-22T12:00:00+08:00'));
        Http::preventStrayRequests();
        Bus::fake([DeliverPackagePurchase::class]);
    }

    public function test_flag_is_off_by_default_and_all_feature_endpoints_are_closed(): void
    {
        $defaults = require base_path('config/package_checkout.php');
        $this->assertFalse($defaults['enabled']);
        config(['package_checkout.enabled' => false]);
        $this->getJson('/api/v1/package-offers/starter', $this->headers())->assertNotFound();
        $this->create()->assertNotFound();
        $this->postJson('/api/v1/webhooks/package-qrph')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_integration_token_and_proof_are_required_and_origin_is_rejected(): void
    {
        $this->getJson('/api/v1/package-offers/starter')->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
        $this->create([], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->create([], ['Origin' => 'https://evil.test'])->assertForbidden();
        $this->create([], ['X-Checkout-Proof' => 'short'])->assertNotFound();
        $this->create([], ['X-Checkout-Proof' => ''])->assertNotFound();
        $this->assertDatabaseCount('package_checkouts', 0);
        Http::assertNothingSent();
    }

    public function test_four_offers_are_integer_php_quantity_one_and_custom_is_absent(): void
    {
        foreach (['starter' => 77700, 'business' => 349900, 'growth' => 649900, 'pickleball' => 250000] as $code => $amount) {
            $this->getJson('/api/v1/package-offers/'.$code, $this->headers())->assertOk()
                ->assertJsonPath('data.amount_minor', $amount)->assertJsonPath('data.quantity', 1)
                ->assertJsonPath('data.currency', 'PHP')->assertJsonPath('data.version', '2026-10-22')->assertJsonPath('data.available', true)
                ->assertJsonPath('data.test_mode', true);
        }
        $this->getJson('/api/v1/package-offers/custom', $this->headers())->assertNotFound();
        $this->create(['package_code' => 'custom'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_create_returns_only_safe_contract_and_private_snapshot_is_encrypted(): void
    {
        $this->fakeProvider();
        $response = $this->create()->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $data = $response->json('data');
        $this->assertSame(['reference', 'status', 'test_mode', 'offer', 'qr_image', 'expires_at', 'fulfillment_status'], array_keys($data));
        $this->assertNull($data['fulfillment_status']);
        $this->assertTrue($data['test_mode']);
        $this->assertTrue(Str::isUlid($data['reference']));
        $this->assertSame('pending', $data['status']);
        $this->assertSame(self::PNG, $data['qr_image']);
        $this->assertSame(['code' => 'starter', 'name' => 'Starter', 'amount_minor' => 77700,
            'currency' => 'PHP', 'quantity' => 1, 'version' => '2026-10-22'], $data['offer']);
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->assertSame('Synthetic business', $checkout->business_name);
        $this->assertSame('buyer@example.test', $checkout->email);
        $this->assertSame(hash('sha256', self::PROOF), $checkout->proof_hash);
        $raw = DB::table('package_checkouts')->first();
        $this->assertNotSame('Synthetic business', $raw->business_name);
        $this->assertNotSame('buyer@example.test', $raw->email);
        foreach (['buyer@example.test', 'Synthetic business', self::PROOF, 'pi_Synthetic', 'pm_Synthetic', 'client_', 'sk_test_', 'whsk_'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
            $this->assertStringNotContainsString($secret, $checkout->toJson());
        }
        Http::assertSentCount(3);
        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.paymongo.com/v1/payment_methods') {
                return false;
            }
            $this->assertSame(['data' => ['attributes' => ['type' => 'qrph', 'expiry_seconds' => 1800]]], $request->data());
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_synthetic123456:')));

            return true;
        });
    }

    public static function injectedFields(): array
    {
        return array_map(fn ($field) => [$field], ['amount_minor', 'amount', 'currency', 'quantity', 'provider_intent_id',
            'payment_method', 'client_key', 'status', 'user_id', 'billing', 'return_url']);
    }

    #[DataProvider('injectedFields')]
    public function test_client_money_quantity_provider_and_unapproved_fields_are_rejected(string $field): void
    {
        $this->create([$field => 1])->assertUnprocessable();
        $this->assertDatabaseCount('package_checkouts', 0);
        Http::assertNothingSent();
    }

    public static function invalidInputs(): array
    {
        return [[['email' => 'broken']], [['email' => ['nested']]], [['business_name' => '']],
            [['business_name' => "Business\nInjected"]], [['operation_id' => 'not-uuid']], [['offer_version' => 'old']]];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_details_or_stale_offer_cannot_create(array $changes): void
    {
        $response = $this->create($changes);
        $changes === ['offer_version' => 'old'] ? $response->assertConflict() : $response->assertUnprocessable();
        $this->assertDatabaseCount('package_checkouts', 0);
        Http::assertNothingSent();
    }

    public function test_replay_reuses_the_snapshot_and_conflicting_details_or_proof_return_409(): void
    {
        $this->fakeProvider();
        $input = $this->input();
        $first = $this->create($input)->assertCreated()->json('data');
        $this->create($input)->assertOk()->assertExactJson(['data' => $first]);
        $this->create([...$input, 'email' => ' BUYER@EXAMPLE.TEST ', 'business_name' => ' Synthetic business '])->assertOk();
        $this->create([...$input, 'business_name' => 'Changed'])->assertConflict();
        $this->create($input, ['X-Checkout-Proof' => str_repeat('b', 64)])->assertConflict();
        $this->create([...$input, 'package_code' => 'business'])->assertConflict();
        $this->assertDatabaseCount('package_checkouts', 1);
        Http::assertSentCount(3);
    }

    public function test_concurrent_retry_observes_the_durable_reservation_without_a_second_provider_post(): void
    {
        $input = $this->input();
        $duplicate = null;
        $this->fakeProvider(function () use ($input, &$duplicate): void {
            $duplicate = $this->create($input)->assertOk()->assertJsonPath('data.status', 'creating')->json('data.reference');
        });
        $reference = $this->create($input)->assertCreated()->json('data.reference');
        $this->assertSame($reference, $duplicate);
        $this->assertDatabaseCount('package_checkouts', 1);
        Http::assertSentCount(3);
    }

    public function test_resolve_and_status_need_proof_and_never_query_the_provider(): void
    {
        $this->fakeProvider();
        $input = $this->input();
        $reference = $this->create($input)->json('data.reference');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake();
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.status', 'pending');
        $this->getJson('/api/v1/package-checkouts/resolve/'.$input['operation_id'], $this->headers())->assertOk()->assertJsonPath('data.reference', $reference);
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers(['X-Checkout-Proof' => str_repeat('b', 64)]))->assertNotFound();
        $this->getJson('/api/v1/package-checkouts/resolve/'.$input['operation_id'], $this->headers(['X-Checkout-Proof' => str_repeat('b', 64)]))->assertNotFound();
        $this->getJson('/api/v1/package-checkouts/resolve/'.Str::uuid(), $this->headers())->assertNotFound();
        Http::assertNothingSent();
    }

    public static function providerStages(): array
    {
        return [['intent'], ['method'], ['attach']];
    }

    #[DataProvider('providerStages')]
    public function test_timeout_at_each_post_becomes_unknown_and_replays_without_retrying(string $stage): void
    {
        $this->fakeProvider(null, $stage);
        $input = $this->input();
        $reference = $this->create($input)->assertCreated()->assertJsonPath('data.status', 'unknown')->json('data.reference');
        $count = count(Http::recorded());
        $this->create($input)->assertOk()->assertJsonPath('data.reference', $reference)->assertJsonPath('data.status', 'unknown');
        $this->getJson('/api/v1/package-checkouts/resolve/'.$input['operation_id'], $this->headers())->assertOk()->assertJsonPath('data.status', 'unknown');
        Http::assertSentCount($count);
        $this->assertDatabaseCount('package_checkouts', 1);
    }

    public function test_abandoned_worker_is_unknown_and_expired_qr_is_hidden_but_receipt_remains(): void
    {
        $this->fakeProvider();
        $reference = $this->create()->json('data.reference');
        $this->travel(31)->minutes();
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()
            ->assertJsonPath('data.status', 'expired')->assertJsonPath('data.qr_image', null);
        $checkout = PackageCheckout::query()->firstOrFail();
        $checkout->forceFill(['status' => 'creating'])->save();
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.status', 'unknown');
        Http::assertSentCount(3);
    }

    public function test_promo_closes_in_manila_timezone_without_destroying_replay_or_existing_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30T23:58:00+08:00'));
        $this->fakeProvider();
        $input = $this->input(['package_code' => 'pickleball']);
        $reference = $this->create($input)->assertCreated()->json('data.reference');
        $this->assertLessThanOrEqual(CarbonImmutable::parse('2026-12-01T00:00:00+08:00'), PackageCheckout::query()->first()->expires_at);
        $this->travelTo(CarbonImmutable::parse('2026-12-01T00:00:01+08:00'));
        $this->getJson('/api/v1/package-offers/pickleball', $this->headers())->assertOk()->assertJsonPath('data.available', false);
        $this->create(['package_code' => 'pickleball'])->assertConflict();
        $this->create($input)->assertOk()->assertJsonPath('data.reference', $reference)->assertJsonPath('data.status', 'expired');
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk();
        foreach (['starter', 'business', 'growth'] as $code) {
            $this->getJson('/api/v1/package-offers/'.$code, $this->headers())->assertOk()->assertJsonPath('data.available', true);
            $evergreen = $this->create(['package_code' => $code])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');
            $this->assertSame(now()->addMinutes(30)->toIso8601String(), $evergreen['expires_at']);
            $this->travel(1)->seconds();
        }
        $this->webhook($this->event(PackageCheckout::query()->findOrFail($reference)))->assertOk();
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.status', 'paid');
        Http::assertSentCount(12);
    }

    public function test_wrong_mode_credentials_fail_before_reservation(): void
    {
        config(['package_checkout.secret_key' => 'sk_live_synthetic123456']);
        $this->create()->assertStatus(503);
        config(['package_checkout.mode' => 'anything']);
        $this->create()->assertStatus(503);
        $this->assertDatabaseCount('package_checkouts', 0);
        Http::assertNothingSent();
    }

    public static function invalidImages(): array
    {
        return [['https://evil.test/qr.png'], ['data:image/svg+xml;base64,PHN2Zz4='], ['data:image/png;base64,YmFk'],
            ['data:image/png;base64,'.str_repeat('A', 350001)]];
    }

    #[DataProvider('invalidImages')]
    public function test_malformed_provider_qr_is_not_exposed_or_treated_as_success(string $image): void
    {
        $this->fakeProvider(null, null, $image);
        $this->create()->assertCreated()->assertJsonPath('data.status', 'unknown')->assertJsonPath('data.qr_image', null);
    }

    public function test_provider_succeeded_read_cannot_mark_paid_and_bad_intent_amount_is_unknown(): void
    {
        $this->fakeProvider(null, null, self::PNG, ['status' => 'succeeded']);
        $this->create()->assertCreated()->assertJsonPath('data.status', 'unknown');
        $this->fakeProvider(null, null, self::PNG, ['amount' => 1]);
        $this->create()->assertCreated()->assertJsonPath('data.status', 'unknown');
        $this->assertDatabaseMissing('package_checkouts', ['status' => 'paid']);
    }

    public function test_snapshot_mutation_and_customer_admin_access_are_denied_but_staff_can_read(): void
    {
        $this->fakeProvider();
        $reference = $this->create()->json('data.reference');
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->seed(RoleSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        $this->assertFalse(Gate::forUser($customer)->allows('view', $checkout));
        $this->actingAs($customer)->get('/admin/package-checkouts/'.$reference)->assertForbidden();
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $this->assertTrue(Gate::forUser($administrator)->allows('view', $checkout));
        $this->assertFalse(Gate::forUser($administrator)->allows('update', $checkout));
        $this->actingAs($administrator)->get('/admin/package-checkouts')->assertOk();
        $this->actingAs($administrator)->get('/admin/package-checkouts/'.$reference)->assertOk()->assertSee('buyer@example.test')->assertSee('Synthetic business');
        $this->expectException(\LogicException::class);
        $checkout->forceFill(['amount_minor' => 1])->save();
    }

    public function test_signed_payment_settles_once_and_later_failures_or_expiry_cannot_reverse_it(): void
    {
        $this->fakeProvider();
        $this->create()->assertCreated();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $this->webhook($event)->assertOk();
        $paidAt = $checkout->fresh()->paid_at;
        $this->webhook($event)->assertOk();
        $this->assertDatabaseCount('package_qrph_events', 1);
        $this->assertSame('paid', $checkout->fresh()->status);
        $this->webhook($this->event($checkout, 'payment.failed'))->assertOk();
        $this->webhook($this->expiredEvent($checkout))->assertOk();
        $this->assertSame('paid', $checkout->fresh()->status);
        $this->assertTrue($paidAt->equalTo($checkout->fresh()->paid_at));
        $this->getJson('/api/v1/package-checkouts/'.$checkout->id, $this->headers())->assertOk()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.qr_image', null);
        Http::assertSentCount(3);
    }

    public function test_local_and_provider_expiry_still_accept_a_late_authentic_payment(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->webhook($this->expiredEvent($checkout))->assertOk();
        $this->assertSame('expired', $checkout->fresh()->status);
        $this->travel(31)->minutes();
        $this->webhook($this->event($checkout))->assertOk();
        $this->assertSame('paid', $checkout->fresh()->status);
        $this->assertDatabaseCount('package_qrph_events', 2);
    }

    public function test_qualified_native_qrph_expiry_hides_the_matching_intent_qr_once(): void
    {
        $this->fakeProvider();
        $this->create()->assertCreated();
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->assertTrue($checkout->expires_at->isFuture());
        $event = $this->nativeExpiredEvent($checkout);
        $this->webhook($event)->assertOk();
        $this->assertSame('expired', $checkout->fresh()->status);
        $this->assertNull($checkout->fresh()->qr_image);
        $this->assertNull($checkout->fresh()->paid_at);
        $this->assertDatabaseHas('package_qrph_events', ['id' => $event['data']['id'],
            'outcome' => 'expired', 'package_checkout_id' => $checkout->id]);
        $this->webhook($event)->assertOk();
        $this->assertDatabaseCount('package_qrph_events', 1);
        Http::assertSentCount(3);
    }

    public static function invalidNativeExpiryEvidence(): array
    {
        return [
            'wrong resource mode' => [['livemode' => true], []],
            'non-boolean mode' => [['livemode' => 0], []],
            'wrong source status' => [['source_status' => 'pending'], []],
            'missing source status' => [['source_status' => null], []],
            'unknown intent' => [['payment_intent_id' => 'pi_UnknownSynthetic'], []],
            'invalid intent' => [['payment_intent_id' => 'cs_WrongResource'], []],
            'missing intent' => [['payment_intent_id' => null], []],
            'wrong resource type' => [[], ['type' => 'payment']],
            'wrong resource id' => [[], ['id' => 'pm_WrongResource']],
        ];
    }

    #[DataProvider('invalidNativeExpiryEvidence')]
    public function test_qualified_native_qrph_expiry_rejects_mismatched_evidence_without_provider_reads(array $changes, array $resourceChanges): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->nativeExpiredEvent($checkout);
        $resource = &$event['data']['attributes']['data'];
        $resource['attributes'] = array_replace($resource['attributes'], $changes);
        $resource = array_replace($resource, $resourceChanges);
        $this->webhook($event)->assertOk();
        $this->assertSame('pending', $checkout->fresh()->status);
        $this->assertSame(self::PNG, $checkout->fresh()->qr_image);
        $this->assertDatabaseHas('package_qrph_events', ['id' => $event['data']['id'],
            'outcome' => 'unmatched', 'package_checkout_id' => null]);
        Http::assertSentCount(3);
    }

    public function test_qualified_native_qrph_expiry_cannot_cross_checkout_mode_or_reverse_paid(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        config(['package_checkout.mode' => 'live', 'package_checkout.secret_key' => 'sk_live_synthetic123456']);
        $event = $this->nativeExpiredEvent($checkout);
        $event['data']['attributes']['livemode'] = true;
        $event['data']['attributes']['data']['attributes']['livemode'] = true;
        $this->webhook($event)->assertOk();
        $this->assertSame('pending', $checkout->fresh()->status);
        $this->assertDatabaseHas('package_qrph_events', ['id' => $event['data']['id'], 'outcome' => 'unmatched']);
        config(['package_checkout.mode' => 'test', 'package_checkout.secret_key' => 'sk_test_synthetic123456']);
        $this->webhook($this->event($checkout))->assertOk();
        $settled = $checkout->fresh();
        $this->webhook($this->nativeExpiredEvent($checkout))->assertOk();
        $this->assertSame('paid', $checkout->fresh()->status);
        $this->assertSame($settled->provider_payment_id, $checkout->fresh()->provider_payment_id);
        $this->assertTrue($settled->paid_at->equalTo($checkout->fresh()->paid_at));
        $this->assertNull($checkout->fresh()->qr_image);
        Http::assertSentCount(3);
    }

    public function test_failed_payment_is_retained_and_valid_later_paid_event_wins(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->webhook($this->event($checkout, 'payment.failed'))->assertOk();
        $this->assertSame('failed', $checkout->fresh()->status);
        $this->webhook($this->event($checkout))->assertOk();
        $this->assertSame('paid', $checkout->fresh()->status);
    }

    public static function badPaymentEvidence(): array
    {
        return [[['amount' => 1]], [['amount' => '77700']], [['currency' => 'USD']], [['livemode' => true]],
            [['source' => ['type' => 'card']]], [['source' => null]], [['status' => 'pending']], [['paid_at' => null]],
            [['paid_at' => '123']], [['paid_at' => 1e12]]];
    }

    #[DataProvider('badPaymentEvidence')]
    public function test_signed_but_mismatched_payment_cannot_settle(array $changes): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $event['data']['attributes']['data']['attributes'] = array_replace($event['data']['attributes']['data']['attributes'], $changes);
        $this->webhook($event)->assertOk();
        $this->assertSame('pending', $checkout->fresh()->status);
        $this->assertDatabaseHas('package_qrph_events', ['outcome' => 'rejected']);
        $this->assertNull($checkout->fresh()->provider_payment_id);
    }

    public function test_raw_signature_tampering_stale_or_future_timestamp_wrong_mode_and_duplicate_header_are_denied(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $this->webhook($event, now()->timestamp - 301)->assertUnauthorized();
        $this->webhook($event, now()->timestamp + 301)->assertUnauthorized();
        $this->webhook($event, null, 'live')->assertUnauthorized();
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = $this->signature($raw);
        $this->rawWebhook($raw.' ', $signature)->assertUnauthorized();
        $this->rawWebhook($raw, $signature.',t='.now()->timestamp)->assertUnauthorized();
        $this->rawWebhook($raw, '')->assertUnauthorized();
        $this->assertDatabaseCount('package_qrph_events', 0);
        $this->assertSame('pending', $checkout->fresh()->status);
    }

    public function test_live_mode_uses_only_li_signature_and_matches_live_records(): void
    {
        config(['package_checkout.mode' => 'live', 'package_checkout.secret_key' => 'sk_live_synthetic123456']);
        $this->fakeProvider();
        $this->getJson('/api/v1/package-offers/starter', $this->headers())->assertOk()->assertJsonPath('data.test_mode', false);
        $this->create()->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.test_mode', false);
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $this->webhook($event, null, 'test')->assertUnauthorized();
        $this->webhook($event, null, 'live')->assertOk();
        $this->assertSame('paid', $checkout->fresh()->status);
    }

    public function test_event_mode_mismatch_is_rejected_even_with_matching_signature(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $event['data']['attributes']['livemode'] = true;
        $this->webhook($event)->assertUnprocessable();
        $this->assertDatabaseCount('package_qrph_events', 0);
    }

    public function test_event_id_reuse_with_changed_payload_is_conflict_and_original_receipt_remains(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $this->webhook($event)->assertOk();
        $event['data']['attributes']['data']['attributes']['amount'] = 1;
        $this->webhook($event)->assertConflict();
        $this->assertDatabaseCount('package_qrph_events', 1);
        $this->assertDatabaseHas('package_qrph_events', ['outcome' => 'paid']);
        $this->assertSame('paid', $checkout->fresh()->status);
    }

    public function test_an_extra_payment_for_the_same_intent_is_flagged_without_overwriting_original(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->webhook($this->event($checkout))->assertOk();
        $original = $checkout->fresh()->provider_payment_id;
        $event = $this->event($checkout);
        $event['data']['attributes']['data']['id'] = 'pay_AnotherPayment';
        $this->webhook($event)->assertOk();
        $this->assertSame($original, $checkout->fresh()->provider_payment_id);
        $this->assertDatabaseHas('package_qrph_events', ['outcome' => 'payment_conflict']);
    }

    public function test_unknown_intent_creation_is_linked_only_by_authoritative_metadata_then_signed_settlement(): void
    {
        $this->fakeProvider(null, 'intent');
        $this->create()->assertCreated()->assertJsonPath('data.status', 'unknown');
        $checkout = PackageCheckout::query()->firstOrFail();
        $this->assertNull($checkout->provider_intent_id);
        $intent = $this->intent($checkout, ['status' => 'succeeded']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.paymongo.com/v1/payment_intents/'.$intent['id'] => Http::response(['data' => $intent])]);
        $event = $this->event($checkout);
        $event['data']['attributes']['data']['attributes']['payment_intent_id'] = $intent['id'];
        $this->webhook($event)->assertOk();
        $this->assertSame($intent['id'], $checkout->fresh()->provider_intent_id);
        $this->assertSame('paid', $checkout->fresh()->status);
        Http::assertSentCount(1);
        $this->webhook($event)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_unknown_intent_does_not_trust_event_metadata_or_wrong_authoritative_amount(): void
    {
        $this->fakeProvider(null, 'intent');
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $intent = $this->intent($checkout, ['amount' => 1]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.paymongo.com/v1/payment_intents/'.$intent['id'] => Http::response(['data' => $intent])]);
        $event = $this->event($checkout);
        $event['data']['attributes']['data']['attributes']['payment_intent_id'] = $intent['id'];
        $event['data']['attributes']['data']['attributes']['metadata'] = ['package_checkout_reference' => $checkout->id];
        $this->webhook($event)->assertStatus(503);
        $this->assertNull($checkout->fresh()->provider_intent_id);
        $this->assertSame('unknown', $checkout->fresh()->status);
        $this->assertDatabaseCount('package_qrph_events', 0);
    }

    public function test_receipts_store_only_hashes_and_safe_outcomes_and_unsupported_events_are_acknowledged(): void
    {
        $this->fakeProvider();
        $this->create();
        $checkout = PackageCheckout::query()->firstOrFail();
        $event = $this->event($checkout);
        $event['data']['attributes']['data']['attributes']['billing'] = ['email' => 'private@example.test', 'name' => 'Private Payer'];
        $event['data']['attributes']['data']['attributes']['client_key'] = 'client_secret';
        $this->webhook($event)->assertOk();
        $stored = json_encode(DB::table('package_qrph_events')->first());
        foreach (['private@example.test', 'Private Payer', 'client_secret', 'pay_', 'pi_'] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
        $event = $this->event($checkout, 'subscription.created');
        $this->webhook($event)->assertOk();
        $this->assertDatabaseHas('package_qrph_events', ['event_type' => 'subscription.created', 'outcome' => 'unmatched']);
    }

    public function test_webhook_and_create_routes_are_rate_limited_and_request_sizes_bounded(): void
    {
        $this->create(['business_name' => str_repeat('x', 5000)])->assertStatus(413);
        for ($i = 0; $i < 9; $i++) {
            $this->create(['email' => 'invalid'])->assertUnprocessable();
        }
        $this->create(['email' => 'invalid'])->assertTooManyRequests();
        $large = str_repeat('x', 500001);
        $this->rawWebhook($large, $this->signature($large))->assertStatus(413);
        for ($i = 0; $i < 119; $i++) {
            $this->rawWebhook('{}', 'bad')->assertUnauthorized();
        }
        $this->rawWebhook('{}', 'bad')->assertTooManyRequests();
    }

    public function test_malformed_signed_json_is_rejected_without_data_mutation(): void
    {
        $this->rawWebhook('{', $this->signature('{'))->assertUnprocessable();
        $this->rawWebhook('{"data":[]}', $this->signature('{"data":[]}'))->assertUnprocessable();
        $this->assertDatabaseCount('package_qrph_events', 0);
    }

    public function test_debug_mode_errors_never_expose_exception_arguments_or_provider_messages(): void
    {
        config(['app.debug' => true]);
        $this->app->bind(PackageCheckouts::class, fn () => throw new \RuntimeException('private@example.test sk_test_private_token'));
        $response = $this->create()->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['message' => 'Checkout temporarily unavailable.'], $response->json());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->assertStringNotContainsString('sk_test_private_token', $response->getContent());
        $this->assertDatabaseCount('package_checkouts', 0);
    }

    public function test_full_name_optional_phone_and_pending_capture_are_private_immutable_snapshots(): void
    {
        $this->fakeProvider();
        $input = $this->input(['full_name' => ' Synthetic Person ', 'phone' => ' +63 (912) 345-6789 ']);
        $reference = $this->create($input)->assertCreated()->json('data.reference');
        $checkout = PackageCheckout::query()->findOrFail($reference);
        $this->assertSame('Synthetic Person', $checkout->full_name);
        $this->assertSame('+63 (912) 345-6789', $checkout->phone);
        $delivery = $this->delivery($reference, 'pending');
        $payload = json_decode($delivery->payload, true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('pending', $payload['phase']);
        $this->assertNull($payload['payment_reference']);
        $this->assertNull($payload['paid_at']);
        $this->assertSame('Synthetic Person', $payload['full_name']);
        $this->assertSame('+63 (912) 345-6789', $payload['phone']);
        $this->assertSame(hash('sha256', $delivery->payload), $delivery->payload_hash);
        $this->assertNotSame($checkout->full_name, DB::table('package_checkouts')->value('full_name'));
        $this->assertNotSame($checkout->phone, DB::table('package_checkouts')->value('phone'));
        $this->assertStringNotContainsString('Synthetic Person', DB::table('package_purchase_deliveries')->value('payload'));
        $response = $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk();
        foreach (['Synthetic Person', '+63', 'buyer@example.test', 'payload_hash'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
            $this->assertStringNotContainsString($private, $delivery->toJson());
        }
        $this->create([...$input, 'full_name' => 'Synthetic Person', 'phone' => '+63 (912) 345-6789'])->assertOk();
        $this->create([...$input, 'phone' => '+639123456780'])->assertConflict();
        $this->assertDatabaseCount('package_purchase_deliveries', 1);
        Bus::assertDispatchedAfterResponse(DeliverPackagePurchase::class, fn ($job) => $job->reference === $reference && $job->phase === 'pending');
        Http::assertSentCount(3);
        $this->expectException(\LogicException::class);
        $checkout->forceFill(['phone' => '+639123456780'])->save();
    }

    public function test_legacy_hash_and_name_alias_replay_survive_without_changing_old_snapshots(): void
    {
        $this->fakeProvider();
        $input = $this->input(['business_name' => 'Legacy <Business>']);
        $reference = $this->create($input)->assertCreated()->json('data.reference');
        // Reproduce a pre-upgrade operation exactly, including its historical hash.
        DB::table('package_checkouts')->where('id', $reference)->update(['request_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'full_name' => null]);
        $this->create($input)->assertOk()->assertJsonPath('data.reference', $reference);
        $this->assertSame('Legacy <Business>', PackageCheckout::query()->findOrFail($reference)->contactName());
        $this->create([...$input, 'phone' => '+639123456789'])->assertConflict();
        $this->create(['full_name' => 'Person', 'business_name' => 'Business'])->assertUnprocessable();
        $this->create(['full_name' => '<Person>'])->assertUnprocessable();
        Http::assertSentCount(3);
    }

    public static function invalidPhones(): array
    {
        return [['abc'], ['123456'], ['1234567890123456'], [['nested']], [123456789], ["0912\n3456789"]];
    }

    #[DataProvider('invalidPhones')]
    public function test_optional_phone_rejects_invalid_types_characters_and_digit_counts(mixed $phone): void
    {
        $this->create(['full_name' => 'Synthetic Person', 'phone' => $phone])->assertUnprocessable();
        $this->assertDatabaseCount('package_checkouts', 0);
        $this->assertDatabaseCount('package_purchase_deliveries', 0);
    }

    public function test_pending_capture_exists_before_provider_work_and_survives_an_unknown_qr_result(): void
    {
        $this->fakeProvider(function (): void {
            $this->assertDatabaseCount('package_purchase_deliveries', 1);
            $this->assertDatabaseHas('package_purchase_deliveries', ['phase' => 'pending', 'status' => 'pending']);
        });
        $this->create(['full_name' => 'Synthetic Person', 'phone' => null])->assertCreated();
        $this->fakeProvider(null, 'intent');
        $reference = $this->create(['full_name' => 'Another Person', 'phone' => ''])->assertCreated()->assertJsonPath('data.status', 'unknown')->json('data.reference');
        $this->assertNull(PackageCheckout::query()->findOrFail($reference)->phone);
        $this->assertSame('pending', $this->delivery($reference, 'pending')->status);
        $this->assertDatabaseCount('package_purchase_deliveries', 2);
    }

    public function test_verified_paid_queues_one_completed_phase_without_network_and_preserves_silent_pending(): void
    {
        $this->fakeProvider();
        $reference = $this->create(['full_name' => 'Synthetic Person', 'phone' => null])->json('data.reference');
        $checkout = PackageCheckout::query()->findOrFail($reference);
        $event = $this->event($checkout);
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->webhook($this->event($checkout))->assertOk();
        $this->assertDatabaseCount('package_purchase_deliveries', 2);
        $payload = json_decode($this->delivery($reference, 'completed')->payload, true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(['reference', 'phase', 'payment_reference', 'full_name', 'email', 'phone', 'package_code', 'package_name', 'amount_minor', 'currency', 'quantity', 'paid_at', 'test_mode'], array_keys($payload));
        $this->assertSame('completed', $payload['phase']);
        $this->assertSame('pay_Synthetic'.$reference, $payload['payment_reference']);
        $this->assertSame('2026-10-22T04:00:00Z', $payload['paid_at']);
        $this->assertSame(77700, $payload['amount_minor']);
        $this->assertNull($payload['phone']);
        $base = fn (array $value) => array_diff_key($value, array_flip(['phase', 'payment_reference', 'paid_at']));
        $this->assertSame($base(json_decode($this->delivery($reference, 'pending')->payload, true, 16, JSON_THROW_ON_ERROR)), $base($payload));
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.fulfillment_status', 'pending');
        Bus::assertDispatchedAfterResponse(DeliverPackagePurchase::class, fn ($job) => $job->reference === $reference && $job->phase === 'completed');
        Http::assertSentCount(3);
    }

    public function test_outbox_failure_rolls_back_paid_and_receipt_then_retry_commits_all_three(): void
    {
        $this->fakeProvider();
        $reference = $this->create()->json('data.reference');
        $event = $this->event(PackageCheckout::query()->findOrFail($reference));
        $eventName = 'eloquent.creating: '.PackagePurchaseDelivery::class;
        Event::listen($eventName, fn () => throw new \RuntimeException('Synthetic outbox failure'));
        try {
            $this->webhook($event)->assertStatus(503);
        } finally {
            Event::forget($eventName);
        }
        $this->assertDatabaseHas('package_checkouts', ['id' => $reference, 'status' => 'pending']);
        $this->assertDatabaseCount('package_qrph_events', 0);
        $this->assertDatabaseCount('package_purchase_deliveries', 1);
        $this->webhook($event)->assertOk();
        $this->assertDatabaseCount('package_purchase_deliveries', 2);
        $this->assertDatabaseHas('package_checkouts', ['id' => $reference, 'status' => 'paid']);
    }

    public function test_capture_failure_rolls_back_the_reservation_before_any_provider_creation(): void
    {
        $this->fakeProvider();
        $eventName = 'eloquent.creating: '.PackagePurchaseDelivery::class;
        Event::listen($eventName, fn () => throw new \RuntimeException('Synthetic capture failure'));
        try {
            $this->create(['full_name' => 'Synthetic Person', 'phone' => null])->assertStatus(503);
        } finally {
            Event::forget($eventName);
        }
        $this->assertDatabaseCount('package_checkouts', 0);
        $this->assertDatabaseCount('package_purchase_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_bridge_delivery_uses_frozen_payload_and_reference_then_stops_after_success(): void
    {
        $reference = $this->paidPurchase();
        $delivery = $this->delivery($reference, 'completed');
        $body = $delivery->payload;
        $this->bridgeConfig();
        $this->fakeBridge();
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Http::assertSent(function ($request) use ($body, $reference): bool {
            return $request->url() === 'https://wp.adzbyte.com/wp-json/adzbyte/v1/package-purchase'
                && $request->body() === $body && $request->hasHeader('Idempotency-Key', $reference)
                && $request->hasHeader('Authorization', 'Bearer '.str_repeat('b', 64));
        });
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        Http::assertSentCount(1);
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.fulfillment_status', 'delivered')->assertJsonPath('data.status', 'paid');
        $this->assertFalse(app(PackagePurchaseDeliveries::class)->retry($reference, 'completed'));
    }

    public function test_bridge_capture_acknowledgement_completes_only_the_pending_phase(): void
    {
        $this->fakeProvider();
        $reference = $this->create(['full_name' => 'Synthetic Person', 'phone' => null])->json('data.reference');
        $this->bridgeConfig();
        $this->fakeBridge(fn () => Http::response(['data' => ['reference' => $reference, 'status' => 'captured', 'entry_id' => 42]]));
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'pending');
        $this->assertSame('delivered', $this->delivery($reference, 'pending')->status);
        $this->assertSame('pending', PackageCheckout::query()->findOrFail($reference)->status);
        $this->assertDatabaseCount('package_purchase_deliveries', 1);
        $this->webhook($this->event(PackageCheckout::query()->findOrFail($reference)))->assertOk();
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        $this->assertSame('pending', $this->delivery($reference, 'completed')->status);
        $this->assertSame('bridge_response_invalid', $this->delivery($reference, 'completed')->last_error_code);
        $this->assertSame('paid', PackageCheckout::query()->findOrFail($reference)->status);
    }

    public function test_transport_unknown_retries_identical_bytes_after_backoff_without_changing_payment(): void
    {
        $reference = $this->paidPurchase();
        $this->bridgeConfig();
        $bodies = [];
        $this->fakeBridge(function ($request) use (&$bodies, $reference) {
            $bodies[] = $request->body();
            if (count($bodies) === 1) {
                throw new ConnectionException('Synthetic private authenticated error');
            }

            return Http::response(['data' => ['reference' => $reference, 'status' => 'delivered', 'entry_id' => 42]]);
        });
        $service = app(PackagePurchaseDeliveries::class);
        $service->deliverReference($reference, 'completed');
        $delivery = $this->delivery($reference, 'completed');
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('transport_unknown', $delivery->last_error_code);
        $service->deliverReference($reference, 'completed');
        $this->assertCount(1, $bodies);
        $this->travel(61)->seconds();
        $service->deliverReference($reference, 'completed');
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame('paid', PackageCheckout::query()->findOrFail($reference)->status);
    }

    public function test_review_response_stops_automatic_delivery_and_is_visible_to_admin(): void
    {
        $reference = $this->paidPurchase();
        $this->bridgeConfig();
        $this->fakeBridge(fn () => Http::response(['data' => ['reference' => $reference, 'status' => 'review', 'entry_id' => 42]], 202));
        $service = app(PackagePurchaseDeliveries::class);
        $service->deliverReference($reference, 'completed');
        $service->deliverReference($reference, 'completed');
        $this->assertSame('needs_review', $this->delivery($reference, 'completed')->status);
        $this->assertSame('bridge_review', $this->delivery($reference, 'completed')->last_error_code);
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.fulfillment_status', 'review');
        Http::assertSentCount(1);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Administrator->value);
        $this->actingAs($admin)->get('/admin/package-checkouts/'.$reference)->assertOk()->assertSee('bridge_review');
        $this->artisan('package-purchases:retry', ['reference' => $reference, 'phase' => 'completed'])->assertSuccessful();
        $this->assertSame('pending', $this->delivery($reference, 'completed')->status);
    }

    public static function bridgeFailures(): array
    {
        return [[401, 'needs_review', 'bridge_auth'], [403, 'needs_review', 'bridge_auth'],
            [409, 'needs_review', 'bridge_conflict'], [400, 'needs_review', 'bridge_rejected'],
            [503, 'pending', 'bridge_unavailable'], [202, 'pending', 'bridge_response_invalid']];
    }

    #[DataProvider('bridgeFailures')]
    public function test_bridge_failures_do_not_reverse_paid_or_expose_raw_messages(int $code, string $state, string $error): void
    {
        $reference = $this->paidPurchase();
        $this->bridgeConfig();
        $this->fakeBridge(fn () => Http::response(['message' => 'private@example.test secret_token'], $code));
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        $delivery = $this->delivery($reference, 'completed');
        $this->assertSame($state, $delivery->status);
        $this->assertSame($error, $delivery->last_error_code);
        $this->assertSame('paid', PackageCheckout::query()->findOrFail($reference)->status);
        $this->assertStringNotContainsString('private@example.test', $delivery->toJson());
    }

    public function test_test_mode_requires_explicit_allowlist_and_disabled_bridge_never_claims(): void
    {
        $reference = $this->paidPurchase();
        $this->fakeBridge();
        $service = app(PackagePurchaseDeliveries::class);
        $service->deliverReference($reference, 'completed');
        $this->assertSame(0, $this->delivery($reference, 'completed')->attempts);
        config(['package_checkout.bridge_enabled' => true, 'package_checkout.bridge_token' => str_repeat('b', 64)]);
        $service->deliverReference($reference, 'completed');
        $this->assertSame('test_delivery_blocked', $this->delivery($reference, 'completed')->last_error_code);
        $service->retry($reference, 'completed');
        config(['package_checkout.bridge_test_enabled' => true, 'package_checkout.bridge_test_emails' => ['someone-else@example.test']]);
        $service->deliverReference($reference, 'completed');
        $this->assertSame('test_delivery_blocked', $this->delivery($reference, 'completed')->last_error_code);
        Http::assertNothingSent();
    }

    public function test_worker_lease_blocks_concurrent_send_and_recovers_an_abandoned_worker(): void
    {
        $reference = $this->paidPurchase();
        $this->bridgeConfig();
        $this->fakeBridge(function () use ($reference) {
            app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');

            return Http::response(['data' => ['reference' => $reference, 'status' => 'delivered']]);
        });
        $delivery = $this->delivery($reference, 'completed');
        $delivery->forceFill(['status' => 'processing', 'claim_token' => (string) Str::uuid(),
            'claimed_at' => now()->subMinutes(6), 'attempts' => 1])->save();
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        Http::assertSentCount(1);
    }

    public function test_worker_exhaustion_stops_and_historical_paid_records_are_not_backfilled(): void
    {
        $reference = $this->paidPurchase();
        $delivery = $this->delivery($reference, 'completed');
        $delivery->forceFill(['attempts' => 9])->save();
        $this->bridgeConfig();
        $this->fakeBridge(fn () => Http::response([], 503));
        app(PackagePurchaseDeliveries::class)->deliverReference($reference, 'completed');
        $this->assertSame('needs_review', $delivery->fresh()->status);
        $this->assertSame(10, $delivery->fresh()->attempts);
        DB::table('package_purchase_deliveries')->where('package_checkout_id', $reference)->delete();
        $this->assertSame(0, app(PackagePurchaseDeliveries::class)->deliver());
        $this->getJson('/api/v1/package-checkouts/'.$reference, $this->headers())->assertOk()->assertJsonPath('data.fulfillment_status', 'review');
        $this->assertDatabaseCount('package_purchase_deliveries', 0);
        $this->artisan('package-purchases:deliver', ['--limit' => 0])->assertExitCode(2);
    }

    private function delivery(string $reference, string $phase): PackagePurchaseDelivery
    {
        return PackagePurchaseDelivery::query()->where('package_checkout_id', $reference)->where('phase', $phase)->firstOrFail();
    }

    private function paidPurchase(): string
    {
        $this->fakeProvider();
        $reference = $this->create(['full_name' => 'Synthetic Person', 'phone' => null])->json('data.reference');
        $this->webhook($this->event(PackageCheckout::query()->findOrFail($reference)))->assertOk();
        // Individual worker tests focus on completed delivery; capture is independently tested.
        $this->delivery($reference, 'pending')->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();

        return $reference;
    }

    private function bridgeConfig(): void
    {
        config(['package_checkout.bridge_enabled' => true, 'package_checkout.bridge_token' => str_repeat('b', 64),
            'package_checkout.bridge_test_enabled' => true, 'package_checkout.bridge_test_emails' => ['buyer@example.test']]);
    }

    private function fakeBridge(?\Closure $callback = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://wp.adzbyte.com/wp-json/adzbyte/v1/package-purchase' => $callback ?? fn ($request) => Http::response([
            'data' => ['reference' => $request['reference'], 'status' => 'delivered', 'entry_id' => 42],
        ])]);
    }

    private function event(PackageCheckout $checkout, string $type = 'payment.paid'): array
    {
        return ['data' => ['id' => 'evt_'.Str::ulid(), 'type' => 'event', 'attributes' => [
            'type' => $type, 'livemode' => $checkout->provider_mode === 'live', 'created_at' => now()->timestamp,
            'data' => ['id' => 'pay_Synthetic'.$checkout->id, 'type' => 'payment', 'attributes' => [
                'payment_intent_id' => $checkout->provider_intent_id, 'amount' => $checkout->amount_minor, 'currency' => 'PHP',
                'status' => $type === 'payment.failed' ? 'failed' : 'paid', 'livemode' => $checkout->provider_mode === 'live',
                'source' => ['type' => 'qrph'], 'paid_at' => now()->timestamp,
            ]],
        ]]];
    }

    private function expiredEvent(PackageCheckout $checkout): array
    {
        $event = $this->event($checkout, 'qrph.expired');
        $event['data']['attributes']['data'] = ['id' => $checkout->provider_method_id, 'type' => 'payment_method',
            'attributes' => ['type' => 'qrph', 'livemode' => $checkout->provider_mode === 'live']];

        return $event;
    }

    private function nativeExpiredEvent(PackageCheckout $checkout): array
    {
        $event = $this->event($checkout, 'qrph.expired');
        $event['data']['attributes']['data'] = ['id' => 'qrph_SyntheticQualified', 'type' => 'qrph',
            'attributes' => ['code_id' => 'qr_SyntheticCode', 'livemode' => $checkout->provider_mode === 'live',
                'organization_id' => 'org_Synthetic', 'created_at' => now()->toIso8601String(),
                'source_id' => 'src_Synthetic', 'source_status' => 'expired', 'payment_intent_id' => $checkout->provider_intent_id]];

        return $event;
    }

    private function signature(string $body, ?int $timestamp = null, ?string $mode = null): string
    {
        $timestamp ??= now()->timestamp;
        $mode ??= config('package_checkout.mode');
        $hash = hash_hmac('sha256', $timestamp.'.'.$body, config('package_checkout.webhook_secret'));

        return 't='.$timestamp.($mode === 'live' ? ',te=,li='.$hash : ',te='.$hash.',li=');
    }

    private function webhook(array $event, ?int $timestamp = null, ?string $mode = null)
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->rawWebhook($body, $this->signature($body, $timestamp, $mode));
    }

    private function rawWebhook(string $body, string $signature)
    {
        return $this->call('POST', '/api/v1/webhooks/package-qrph', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature], $body);
    }

    private function headers(array $extra = []): array
    {
        return array_replace(['Authorization' => 'Bearer '.self::TOKEN, 'X-Checkout-Proof' => self::PROOF], $extra);
    }

    private function input(array $extra = []): array
    {
        $input = array_replace(['package_code' => 'starter', 'offer_version' => '2026-10-22', 'operation_id' => (string) Str::uuid(),
            'business_name' => 'Synthetic business', 'email' => 'buyer@example.test'], $extra);
        if (array_key_exists('full_name', $extra) && ! array_key_exists('business_name', $extra)) {
            unset($input['business_name']);
        }

        return $input;
    }

    private function create(array $extra = [], array $headers = [])
    {
        return $this->postJson('/api/v1/package-checkouts', $this->input($extra), $this->headers($headers));
    }

    private function intent(PackageCheckout $checkout, array $changes = []): array
    {
        return ['id' => 'pi_Synthetic'.$checkout->id, 'type' => 'payment_intent', 'attributes' => array_replace([
            'amount' => $checkout->amount_minor, 'currency' => 'PHP', 'livemode' => $checkout->provider_mode === 'live',
            'payment_method_allowed' => ['qrph'], 'status' => 'awaiting_payment_method',
            'client_key' => 'client_never_exposed', 'metadata' => ['package_checkout_reference' => $checkout->id,
                'operation_id' => $checkout->operation_id, 'offer_version' => $checkout->offer_version],
        ], $changes)];
    }

    private function fakeProvider(?\Closure $duringIntent = null, ?string $timeout = null, string $image = self::PNG, array $changes = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($duringIntent, $timeout, $image, $changes) {
            $checkout = PackageCheckout::query()->latest('created_at')->firstOrFail();
            if ($request->url() === 'https://api.paymongo.com/v1/payment_intents') {
                if ($timeout === 'intent') {
                    throw new ConnectionException('Synthetic secret transport body must not leak');
                }
                $duringIntent?->__invoke();

                return Http::response(['data' => $this->intent($checkout, $changes)]);
            }
            if ($request->url() === 'https://api.paymongo.com/v1/payment_methods') {
                if ($timeout === 'method') {
                    throw new ConnectionException('Synthetic method timeout');
                }

                return Http::response(['data' => ['id' => 'pm_Synthetic'.$checkout->id, 'type' => 'payment_method',
                    'attributes' => ['type' => 'qrph', 'livemode' => $checkout->provider_mode === 'live']]]);
            }
            if (str_ends_with($request->url(), '/attach')) {
                if ($timeout === 'attach') {
                    throw new ConnectionException('Synthetic attach timeout');
                }

                return Http::response(['data' => $this->intent($checkout, ['status' => 'awaiting_next_action',
                    'next_action' => ['code' => ['image_url' => $image]]])]);
            }
            throw new \LogicException('Unexpected fake request.');
        });
    }
}
