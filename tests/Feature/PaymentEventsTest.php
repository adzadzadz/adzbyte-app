<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentAttemptActions;
use App\Actions\Payments\PaymentEventActions;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\PaymentSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\TestCase;

class PaymentEventsTest extends TestCase
{
    use CreatesPaymentAttempt, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPaymentAttempt();
    }

    public function test_signed_payment_settles_once_with_safe_evidence_and_canonical_duplicate_replays(): void
    {
        $payload = PaymentEventFixture::payload($this->attempt);
        $event = $this->receive($payload);
        $this->assertSame('applied', $event->state);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame('not_started', $this->order->fresh()->fulfillment_status);
        $this->assertSame(4, $this->order->fresh()->revision);
        $this->assertSame('paid', $this->attempt->fresh()->state);
        $this->assertNull($this->attempt->fresh()->checkout_url);
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->assertSame($event->id, app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body))->id);
        $payload['data']['id'] = 'evt_late_duplicate';
        $this->assertSame('applied', $this->receive($payload)->state);
        $this->assertDatabaseCount('payment_events', 2);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('order_events', 4);
        $settlement = PaymentSettlement::sole();
        $this->assertSame('12500', $settlement->amount_minor);
        foreach (['private-buyer', 'synthetic_client_key', 'checkout_url', 'billing'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($event->evidence));
            $this->assertStringNotContainsString($private, $event->getRawOriginal('evidence'));
        }
        $this->actingAs($this->customer)->get('/orders/'.$this->order->public_id.'/payment')->assertOk()->assertSee('Payment confirmed.')->assertDontSee('Continue to PayMongo');
    }

    #[DataProvider('badSignatures')]
    public function test_invalid_signatures_never_persist_or_mutate(string $case): void
    {
        $body = json_encode(PaymentEventFixture::payload($this->attempt), JSON_THROW_ON_ERROR);
        $valid = PaymentEventFixture::signature($body);
        $header = match ($case) {
            'missing' => '',
            'bad_mac' => 't='.now()->timestamp.',te='.str_repeat('0', 64).',li=',
            'wrong_mode' => str_replace('te=', 'li=', str_replace(',li=', ',te=', $valid)),
            'old' => PaymentEventFixture::signature($body, now()->subSeconds(301)->timestamp),
            'future' => PaymentEventFixture::signature($body, now()->addSeconds(301)->timestamp),
            'duplicate' => $valid.',t='.now()->timestamp,
            'unknown_field' => $valid.',extra=value',
            'tampered' => PaymentEventFixture::signature($body.' '),
        };
        $this->denied(401, fn () => app(PaymentEventActions::class)->receive($body, $header));
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseCount('payment_settlements', 0);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public static function badSignatures(): array
    {
        return array_map(fn ($case) => [$case], ['missing', 'bad_mac', 'wrong_mode', 'old', 'future', 'duplicate', 'unknown_field', 'tampered']);
    }

    #[DataProvider('mismatches')]
    public function test_signed_commercial_mismatches_are_recorded_without_confirming_payment(string $path, mixed $value): void
    {
        $payload = PaymentEventFixture::payload($this->attempt);
        Arr::set($payload, 'data.attributes.data.'.$path, $value);
        $event = $this->receive($payload);
        $this->assertSame('rejected', $event->state);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_settlements', 0);
    }

    public static function mismatches(): array
    {
        return [['id', 'cs_foreign'], ['attributes.livemode', true], ['attributes.reference_number', 'foreign'], ['attributes.metadata.order_reference', 'foreign'],
            ['attributes.line_items.0.amount', 1], ['attributes.pass_on_fees', true], ['attributes.payment_method_types', ['card']],
            ['attributes.payments.0.attributes.amount', 1], ['attributes.payments.0.attributes.amount', '12500'], ['attributes.payments.0.attributes.currency', 'USD'],
            ['attributes.payments.0.attributes.livemode', true], ['attributes.payments.0.attributes.status', 'failed'], ['attributes.payments.0.attributes.source.type', 'card'],
            ['attributes.payments.0.attributes.payment_intent_id', 'pi_foreign'], ['attributes.payment_intent.attributes.status', 'processing'], ['attributes.payment_intent.attributes.amount', 1]];
    }

    public function test_unknown_and_unrelated_events_remain_nonfinancial_and_late_failures_cannot_regress_paid(): void
    {
        $unknown = PaymentEventFixture::payload($this->attempt, 'evt_unknown');
        $unknown['data']['attributes']['data']['id'] = 'cs_unknown';
        $unknown['data']['attributes']['data']['attributes']['metadata']['attempt_reference'] = 'unknown';
        $this->assertSame('needs_review', $this->receive($unknown)->state);
        $failed = PaymentEventFixture::payload($this->attempt, 'evt_failed');
        $failed['data']['attributes']['type'] = 'payment.failed';
        $this->assertSame('ignored', $this->receive($failed)->state);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->receive(PaymentEventFixture::payload($this->attempt));
        $failed['data']['id'] = 'evt_delayed_failure';
        $this->assertSame('ignored', $this->receive($failed)->state);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_settlements', 1);
    }

    public function test_same_event_id_with_changed_semantics_conflicts(): void
    {
        $payload = PaymentEventFixture::payload($this->attempt);
        $this->receive($payload);
        $payload['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] = 1;
        $this->denied(409, fn () => $this->receive($payload));
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
    }

    public function test_verified_callback_during_initial_readback_cannot_be_overwritten_by_the_dispatcher(): void
    {
        $this->createPaymentOrder();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
                return Http::response(['qrph']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'cs_racing', 'type' => 'checkout_session']]);
            }
            $attempt = PaymentAttempt::latest('id')->firstOrFail();
            $payload = PaymentEventFixture::payload($attempt, 'evt_racing');
            $payload['data']['attributes']['data']['id'] = 'cs_racing';
            $this->assertSame('applied', $this->receive($payload)->state);

            return Http::response(['data' => $payload['data']['attributes']['data']]);
        });
        $attempt = app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 2, (string) Str::uuid());
        $this->assertSame('paid', $attempt->state);
        $this->assertNull($attempt->checkout_url);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_settlements', 1);
    }

    public function test_processed_event_and_settlement_history_cannot_be_edited_or_deleted(): void
    {
        $event = $this->receive(PaymentEventFixture::payload($this->attempt));
        $settlement = PaymentSettlement::sole();
        foreach ([fn () => $event->forceFill(['reason' => 'rewritten'])->save(), fn () => $event->delete(),
            fn () => $settlement->forceFill(['amount_minor' => '1'])->save(), fn () => $settlement->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('Financial history mutation succeeded.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertNull($event->fresh()->reason);
        $this->assertSame('12500', $settlement->fresh()->amount_minor);
    }

    public function test_actual_callback_fee_flag_omission_cannot_hide_a_customer_surcharge(): void
    {
        $payload = PaymentEventFixture::payload($this->attempt, 'evt_surcharge');
        unset($payload['data']['attributes']['data']['attributes']['pass_on_fees']);
        $payload['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] += 500;
        $this->assertSame('rejected', $this->receive($payload)->state);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $payload['data']['id'] = 'evt_actual_fee_omission';
        $payload['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] -= 500;
        $this->assertSame('applied', $this->receive($payload)->state);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    public function test_a_signed_outcome_recovers_provider_success_before_local_identity_commit(): void
    {
        // Explicit disposable crash-state fixture; O2.1 separately proves the
        // actual post-commit failure leaves this reservation without rePOSTing.
        DB::table('payment_attempts')->where('id', $this->attempt->id)->update(['state' => 'creating', 'provider_session_id' => null, 'checkout_url' => null]);
        $this->assertSame('applied', $this->receive(PaymentEventFixture::payload($this->attempt))->state);
        $this->assertSame('cs_signed', $this->attempt->fresh()->provider_session_id);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    public function test_local_failure_rolls_back_receipt_and_settlement_then_provider_retry_recovers_once(): void
    {
        $original = PaymentSettlement::getEventDispatcher();
        $temporary = clone $original;
        PaymentSettlement::setEventDispatcher($temporary);
        $temporary->listen('eloquent.created: '.PaymentSettlement::class, fn () => throw new \RuntimeException('Synthetic local failure'));
        try {
            $this->receive(PaymentEventFixture::payload($this->attempt));
            $this->fail('Expected local failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic local failure', $exception->getMessage());
        } finally {
            PaymentSettlement::setEventDispatcher($original);
        }
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseCount('payment_settlements', 0);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('applied', $this->receive(PaymentEventFixture::payload($this->attempt))->state);
        $this->assertDatabaseCount('payment_settlements', 1);
    }

    public function test_route_uses_signature_instead_of_customer_auth_csrf_or_redirect_and_rejects_bad_envelopes(): void
    {
        $this->app->instance('env', 'local');
        $path = '/api/v1/webhooks/paymongo';
        $body = json_encode(PaymentEventFixture::payload($this->attempt), JSON_THROW_ON_ERROR);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => PaymentEventFixture::signature($body)];
        $this->call('POST', $path, server: $headers, content: $body)->assertOk()->assertExactJson(['received' => true])->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($path)->assertMethodNotAllowed();
        $this->call('POST', $path.'?owner=1', server: $headers, content: $body)->assertUnprocessable();
        foreach (['{', 'null', '[]', '{"data":{}}'] as $malformed) {
            $this->denied(422, fn () => app(PaymentEventActions::class)->receive($malformed, PaymentEventFixture::signature($malformed)));
        }
        config(['services.paymongo.mode' => 'live']);
        $this->denied(503, fn () => app(PaymentEventActions::class)->receive($body, $headers['HTTP_PAYMONGO_SIGNATURE']));
    }

    private function receive(array $payload): PaymentEvent
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
    }

    private function denied(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected denial.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
