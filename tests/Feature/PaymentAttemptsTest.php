<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentAttemptActions;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Orders\CommerceRules;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPaymentOrder;
use Tests\TestCase;

class PaymentAttemptsTest extends TestCase
{
    use CreatesPaymentOrder;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://app.example.test', 'services.paymongo.enabled' => true,
            'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only', 'services.paymongo.methods' => ['qrph']]);
        Http::preventStrayRequests();
        $this->createPaymentOrder();
    }

    public function test_a_reserved_attempt_creates_one_verified_test_session_and_replay_never_creates_another(): void
    {
        $this->fakeProvider();
        $operation = (string) Str::uuid();
        $attempt = $this->start($operation);
        $this->assertSame('active', $attempt->state);
        $this->assertSame('cs_synthetic', $attempt->provider_session_id);
        $this->assertSame('12500', $attempt->amount_minor);
        $this->assertFalse($attempt->livemode);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame($attempt->id, $this->order->fresh()->payment_attempt_id);
        $again = $this->start($operation);
        $this->assertSame($attempt->id, $again->id);
        Http::assertSentCount(3);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->denied(409, fn () => $this->start((string) Str::uuid()));
        Http::assertSentCount(3);
        $this->assertStringNotContainsString('checkout.paymongo.com', $attempt->getRawOriginal('checkout_url'));
        $this->assertStringNotContainsString('line_items', $attempt->getRawOriginal('provider_payload'));
    }

    public function test_a_lost_creation_response_remains_unknown_and_retries_do_not_send_another_post(): void
    {
        $posts = 0;
        Http::fake(['*/capabilities/payment_methods' => Http::response(['qrph']), '*/v2/checkout_sessions' => function () use (&$posts) {
            $posts++;
            throw new ConnectionException('Synthetic lost response');
        }]);
        $operation = (string) Str::uuid();
        $attempt = $this->start($operation);
        $this->assertSame('unknown', $attempt->state);
        $this->assertSame('unknown', $this->start($operation)->state);
        $this->denied(409, fn () => $this->start((string) Str::uuid()));
        $this->assertSame(1, $posts);
        Http::assertSentCount(1);
        $this->assertNull($attempt->checkout_url);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public function test_outer_transaction_rollback_cannot_create_a_provider_session(): void
    {
        $this->fakeProvider();
        DB::beginTransaction();
        try {
            $attempt = $this->start((string) Str::uuid());
            $this->assertSame('creating', $attempt->state);
            Http::assertSentCount(1);
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        Http::assertSentCount(1);
    }

    #[DataProvider('minimalAttributes')]
    public function test_known_session_id_survives_failed_retrieval_without_repeating_creation(array $attributes): void
    {
        Http::fake(['*/capabilities/payment_methods' => Http::response(['qrph']),
            '*/v2/checkout_sessions' => Http::response(['data' => ['id' => 'cs_synthetic', 'type' => 'checkout_session', 'attributes' => $attributes]]),
            '*/v1/checkout_sessions/cs_synthetic' => Http::response([], 503)]);
        $operation = (string) Str::uuid();
        $attempt = $this->start($operation);
        $this->assertSame('unknown', $attempt->state);
        $this->assertSame('cs_synthetic', $attempt->provider_session_id);
        $this->assertSame($attempt->id, $this->start($operation)->id);
        Http::assertSentCount(3);
    }

    public static function minimalAttributes(): array
    {
        return [[[]], [['livemode' => true]]];
    }

    public function test_unverified_and_revoked_actors_are_rechecked_before_replay_or_creation(): void
    {
        $this->fakeProvider();
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $this->denied(403, fn () => $this->start((string) Str::uuid()));
        $this->customer->forceFill(['email_verified_at' => now()])->save();
        $operation = (string) Str::uuid();
        $this->start($operation);
        $this->customer->removeRole('customer');
        $this->denied(403, fn () => $this->start($operation));
        Http::assertSentCount(3);
    }

    public function test_accepted_exclusive_tax_is_sent_exactly_and_oversized_totals_are_never_truncated(): void
    {
        $this->fakeProvider();
        $this->createPaymentOrder(12500, [...CommerceRules::defaults(), 'tax_mode' => 'exclusive', 'tax_basis_points' => 1200]);
        $attempt = $this->start((string) Str::uuid());
        $this->assertSame('14000', $attempt->amount_minor);
        $this->assertSame([12500, 1500], array_column($attempt->provider_payload['data']['attributes']['line_items'], 'amount'));
        $this->assertSame('active', $attempt->state);
        $this->createPaymentOrder(PHP_INT_MAX, [...CommerceRules::defaults(), 'tax_mode' => 'exclusive', 'tax_basis_points' => 10000]);
        $this->denied(422, fn () => $this->start((string) Str::uuid()));
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(4);
    }

    public function test_local_failure_after_provider_creation_keeps_reservation_and_replay_does_not_post_again(): void
    {
        $this->fakeProvider();
        $operation = (string) Str::uuid();
        $original = PaymentAttempt::getEventDispatcher();
        $temporary = clone $original;
        PaymentAttempt::setEventDispatcher($temporary);
        $temporary->listen('eloquent.updating: '.PaymentAttempt::class, function (PaymentAttempt $attempt): void {
            if ($attempt->isDirty('provider_session_id')) {
                throw new \RuntimeException('Synthetic local persistence failure.');
            }
        });
        try {
            $this->start($operation);
            $this->fail('Expected local persistence failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic local persistence failure.', $exception->getMessage());
        } finally {
            PaymentAttempt::setEventDispatcher($original);
        }
        $attempt = $this->start($operation);
        $this->assertSame('creating', $attempt->state);
        $this->assertNull($attempt->checkout_url);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('payment_commands', 1);
    }

    #[DataProvider('providerAmountBounds')]
    public function test_payment_intent_amount_limits_are_enforced_before_creating_a_session(int $amount, bool $allowed): void
    {
        $this->fakeProvider();
        $this->createPaymentOrder($amount);
        if ($allowed) {
            $this->assertSame('active', $this->start((string) Str::uuid())->state);
            Http::assertSentCount(3);
        } else {
            $this->denied(422, fn () => $this->start((string) Str::uuid()));
            $this->assertDatabaseCount('payment_attempts', 0);
            $this->assertSame('unpaid', $this->order->fresh()->payment_status);
            Http::assertSentCount(1);
        }
    }

    public static function providerAmountBounds(): array
    {
        return [[99, false], [100, true], [999999999, true], [1000000000, false]];
    }

    #[DataProvider('mismatches')]
    public function test_provider_mismatch_never_publishes_a_checkout_url_or_confirms_payment(string $mismatch): void
    {
        $this->fakeProvider($mismatch);
        $attempt = $this->start((string) Str::uuid());
        $this->assertSame('unknown', $attempt->state);
        $this->assertNull($attempt->checkout_url);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
    }

    public static function mismatches(): array
    {
        return array_map(fn ($value) => [$value], ['amount', 'currency', 'reference', 'metadata', 'mode', 'fees', 'methods', 'url', 'foreign_session_url', 'missing_payments', 'missing_lines', 'bad_metadata']);
    }

    public function test_owned_approval_freshness_and_test_configuration_are_required_before_provider_creation(): void
    {
        $this->fakeProvider();
        $other = User::factory()->create();
        $other->assignRole('customer');
        $this->denied(404, fn () => app(PaymentAttemptActions::class)->start($other, $this->order->public_id, 2, (string) Str::uuid()));
        $this->denied(403, fn () => app(PaymentAttemptActions::class)->start($this->admin, $this->order->public_id, 2, (string) Str::uuid()));
        $this->denied(409, fn () => app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 1, (string) Str::uuid()));
        $this->travel(31)->days();
        $this->denied(409, fn () => $this->start((string) Str::uuid()));
        $this->travelBack();
        config(['services.paymongo.secret_key' => 'sk_live_never_send']);
        $this->denied(503, fn () => $this->start((string) Str::uuid()));
        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_owned_api_and_native_payment_pages_share_safe_projections_and_real_csrf_guards(): void
    {
        $this->app->instance('env', 'local');
        $this->fakeProvider();
        $path = '/api/v1/orders/'.$this->order->public_id.'/payment';
        $this->actingAs($this->customer)->withSession(['_token' => 'csrf']);
        $this->get($path, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data', null)->assertJsonPath('meta.csrf_token', 'csrf')->assertHeader('Cache-Control', 'no-store, private');
        $input = ['expected_revision' => 2];
        $this->postJson($path, $input, ['Origin' => 'https://app.example.test', 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(419);
        $headers = ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => (string) Str::uuid()];
        $this->postJson($path, [...$input, 'amount_minor' => 1], $headers)->assertUnprocessable();
        $this->postJson($path, ['expected_revision' => '2'], $headers)->assertUnprocessable();
        $this->postJson($path, $input, [...$headers, 'Origin' => 'https://evil.example'])->assertForbidden();
        $this->get('/orders/'.$this->order->public_id.'/payment')->assertOk()->assertSee('Prepare secure payment');
        $response = $this->postJson($path, $input, $headers)->assertCreated()->assertJsonPath('data.state', 'active')->assertJsonPath('data.amount_minor', '12500');
        foreach (['actor_id', 'order_id', 'provider_session_id', 'provider_payload', 'request_hash', 'sk_test_', 'failure_code'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->postJson($path, $input, $headers)->assertCreated()->assertJsonPath('data.public_id', $response->json('data.public_id'));
        $this->get('/orders/'.$this->order->public_id.'/payment')->assertOk()->assertSee('Continue to PayMongo')->assertDontSee('Prepare secure payment');
        $other = User::factory()->create();
        $other->assignRole('customer');
        $this->actingAs($other)->get($path, ['Accept' => 'application/json'])->assertNotFound();
        Http::assertSentCount(3);
    }

    public function test_expired_native_session_redirects_to_the_existing_customer_login(): void
    {
        $this->get('/orders/'.$this->order->public_id.'/payment')->assertRedirect(route('filament.account.auth.login'));
        $this->get('/orders/'.$this->order->public_id)->assertRedirect(route('filament.account.auth.login'));
        $this->get('/checkout')->assertRedirect(route('filament.account.auth.login'));
        $this->getJson('/api/v1/orders/'.$this->order->public_id.'/payment')->assertUnauthorized();
    }

    private function start(string $operation): PaymentAttempt
    {
        return app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 2, $operation);
    }

    private function fakeProvider(?string $mismatch = null): void
    {
        Http::fake(function ($request) use ($mismatch) {
            if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
                return Http::response(['qrph']);
            }
            if ($request->method() === 'POST') {
                $this->assertFalse($request['data']['attributes']['pass_on_fees']);
                $this->assertFalse($request['data']['attributes']['send_email_receipt']);
                $this->assertArrayNotHasKey('billing', $request['data']['attributes']);

                return Http::response(['data' => ['id' => 'cs_synthetic', 'type' => 'checkout_session', 'attributes' => ['livemode' => false]]]);
            }
            $attempt = PaymentAttempt::latest('id')->firstOrFail();
            $payload = $attempt->provider_payload['data']['attributes'];
            $attributes = [...$payload, 'livemode' => false, 'status' => 'active', 'checkout_url' => 'https://checkout.paymongo.com/synthetic#synthetic', 'payments' => []];
            match ($mismatch) {
                'amount' => $attributes['line_items'][0]['amount'] = 1,
                'currency' => $attributes['line_items'][0]['currency'] = 'USD',
                'reference' => $attributes['reference_number'] = 'foreign',
                'metadata' => $attributes['metadata']['order_reference'] = 'foreign',
                'mode' => $attributes['livemode'] = true,
                'fees' => $attributes['pass_on_fees'] = true,
                'methods' => $attributes['payment_method_types'] = ['card'],
                'url' => $attributes['checkout_url'] = 'https://evil.example/cs_synthetic',
                'foreign_session_url' => $attributes['checkout_url'] = 'https://checkout.paymongo.com/cs_foreign',
                'missing_payments' => $attributes['payments'] = null,
                'missing_lines' => $attributes['line_items'] = null,
                'bad_metadata' => $attributes['metadata'] = 'invalid',
                default => null,
            };

            return Http::response(['data' => ['id' => 'cs_synthetic', 'type' => 'checkout_session', 'attributes' => $attributes]]);
        });
    }

    private function denied(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a denied payment operation.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);
        }
    }
}
