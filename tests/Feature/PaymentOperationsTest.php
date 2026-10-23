<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentEventActions;
use App\Actions\Payments\PaymentOperations;
use App\Enums\PaymentCapability;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\PaymentEvent;
use App\Models\PaymentResolution;
use App\Models\User;
use App\Services\Payments\CheckoutEvent;
use App\Services\Payments\PayMongoSignature;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\TestCase;

class PaymentOperationsTest extends TestCase
{
    use CreatesPaymentAttempt, RefreshDatabase;

    private PaymentEvent $receipt;

    private array $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPaymentAttempt();
        $this->admin->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $payload = PaymentEventFixture::payload($this->attempt);
        $this->provider = ['data' => $payload['data']['attributes']['data']];
        unset($payload['data']['attributes']['data']['attributes']['pass_on_fees']);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        app(PayMongoSignature::class)->verify($body, PaymentEventFixture::signature($body));
        // Historical receipt from the pre-correction fee-flag check. Preserve it
        // exactly; reconciliation may never rewrite a terminal event's evidence.
        $this->receipt = (new PaymentEvent)->forceFill([...CheckoutEvent::parse($body), 'payment_attempt_id' => $this->attempt->id, 'state' => 'received']);
        $this->receipt->save();
        $this->receipt->forceFill(['state' => 'rejected', 'reason' => 'session_mismatch', 'processed_at' => now()])->save();
        $this->fakeProvider();
    }

    public function test_matching_signed_receipt_and_fresh_get_settle_once_preserving_original_and_audited_replay(): void
    {
        $original = $this->receipt->fresh()->getAttributes();
        $key = (string) Str::uuid();
        $resolution = $this->reconcile($key);
        $this->assertSame($original, $this->receipt->fresh()->getAttributes());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(4, $this->order->fresh()->revision);
        $this->assertSame('rejected', $resolution->original_state);
        $this->assertSame($this->admin->id, $resolution->actor_id);
        $this->assertSame('pay_signed', $resolution->verification['provider_payment_id']);
        $this->assertStringNotContainsString('pay_signed', $resolution->getRawOriginal('verification'));
        $this->assertSame($resolution->id, $this->reconcile($key)->id);
        $this->assertDatabaseCount('payment_resolutions', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('order_events', 5);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_signed');
        $this->denied(409, fn () => $this->reconcile($key, ['expected_revision' => 3, 'note' => 'Changed input']));
        $this->denied(409, fn () => $this->reconcile());
    }

    #[DataProvider('mismatches')]
    public function test_current_provider_mismatch_cannot_override_signed_or_captured_evidence(string $path, mixed $value): void
    {
        Arr::set($this->provider, $path, $value);
        $this->fakeProvider();
        $this->denied(409, fn () => $this->reconcile());
        $this->assertUnchanged();
    }

    public static function mismatches(): array
    {
        return [['data.attributes.livemode', true], ['data.attributes.pass_on_fees', true], ['data.attributes.status', 'expired'],
            ['data.attributes.payments.0.attributes.amount', 1], ['data.attributes.payments.0.attributes.status', 'failed'],
            ['data.attributes.payments.0.attributes.refunds', [['id' => 'ref_synthetic', 'attributes' => ['amount' => 12500, 'status' => 'succeeded']]]],
            ['data.attributes.payments.0.attributes.refunds', [['id' => 'ref_pending', 'attributes' => ['amount' => 100, 'status' => 'pending']]]],
            ['data.attributes.payments.0.attributes.refunds', null], ['data.attributes.payments.0.attributes.disputed', true], ['data.attributes.payments.0.attributes.disputed', null],
            ['data.attributes.payment_intent.attributes.status', 'processing'], ['data.attributes.payments.0.id', 'pay_other'],
            ['data.attributes.payments.0.attributes.currency', 'USD'], ['data.attributes.reference_number', 'other']];
    }

    public function test_provider_success_cannot_override_an_invalid_original_signed_receipt(): void
    {
        $bad = PaymentEventFixture::payload($this->attempt, 'evt_invalid');
        $bad['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] = 1;
        $body = json_encode($bad, JSON_THROW_ON_ERROR);
        $event = app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
        $this->denied(409, fn () => app(PaymentOperations::class)->reconcile($this->admin, $this->attempt->public_id, $event->provider_event_id, $this->input(), (string) Str::uuid()));
        $this->assertUnchanged();
    }

    public function test_stale_revision_and_missing_capability_deny_before_provider_access_and_recheck_after_get(): void
    {
        $this->denied(409, fn () => $this->reconcile(null, ['expected_revision' => 2, 'note' => 'Stale']));
        Http::assertNothingSent();
        $this->admin->revokePermissionTo(PaymentCapability::Reconcile->value);
        $this->denied(403, fn () => $this->reconcile());
        Http::assertNothingSent();
        $this->admin->givePermissionTo(PaymentCapability::Reconcile->value);
        $this->fakeProvider(function (): void {
            $this->admin->revokePermissionTo(PaymentCapability::Reconcile->value);
        });
        $this->denied(403, fn () => $this->reconcile());
        $this->assertUnchanged();
    }

    public function test_prior_admin_order_command_uuid_is_rejected_before_provider_access(): void
    {
        $key = $this->order->events()->where('action', 'approve')->sole()->operation_id;
        $this->denied(409, fn () => $this->reconcile($key));
        Http::assertNothingSent();
        $this->assertUnchanged();
    }

    public function test_provider_failure_and_local_commit_failure_preserve_recovery_then_retry_safely(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => Http::response(['errors' => [['detail' => 'PRIVATE PROVIDER MESSAGE']]], 503));
        $this->denied(503, fn () => $this->reconcile());
        $this->assertUnchanged();
        $this->fakeProvider();
        PaymentResolution::creating(fn () => throw new \RuntimeException('Synthetic local failure'));
        $key = (string) Str::uuid();
        try {
            $this->reconcile($key);
            $this->fail('Local failure was swallowed.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic local failure', $error->getMessage());
        }
        $this->assertUnchanged();
        PaymentResolution::flushEventListeners();
        PaymentResolution::clearBootedModels();
        $this->reconcile($key);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('payment_resolutions', 1);
    }

    public function test_webhook_winning_during_get_cannot_be_overwritten_or_duplicate_settlement(): void
    {
        $this->fakeProvider(function (): void {
            $body = json_encode(PaymentEventFixture::payload($this->attempt, 'evt_concurrent_winner'), JSON_THROW_ON_ERROR);
            app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
        });
        $this->denied(409, fn () => $this->reconcile());
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('payment_resolutions', 0);
        $this->assertSame(4, $this->order->fresh()->revision);
        $this->assertSame('rejected', $this->receipt->fresh()->state);
    }

    public function test_customer_unverified_and_ungranted_super_admin_cannot_read_or_reconcile(): void
    {
        foreach ([$this->customer, User::factory()->create(['email_verified_at' => null]), User::factory()->create()] as $index => $actor) {
            if ($index === 1) {
                $actor->assignRole('administrator');
            }
            if ($index === 2) {
                $actor->assignRole('super_admin');
            }
            if ($index !== 2) {
                $actor->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
            }
            $this->denied(403, fn () => app(PaymentOperations::class)->read($actor, $this->attempt->public_id));
            $this->denied(403, fn () => app(PaymentOperations::class)->reconcile($actor, $this->attempt->public_id, $this->receipt->provider_event_id, $this->input(), (string) Str::uuid()));
        }
        Http::assertNothingSent();
    }

    public function test_management_api_is_strict_private_and_scoped_and_replays_original_command(): void
    {
        config(['app.url' => 'https://app.example.test']);
        $this->withSession(['_token' => 'csrf'])->actingAs($this->admin);
        $path = '/api/v1/management/payments/'.$this->attempt->public_id;
        $this->get($path, ['Accept' => 'application/json'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.events.0.state', 'rejected')->assertJsonPath('meta.csrf_token', 'csrf')
            ->assertDontSee('synthetic_client_key')->assertDontSee('private-buyer')->assertDontSee('checkout_url')->assertDontSee('payload_hash')->assertDontSee('provider_payload');
        $body = ['event' => $this->receipt->provider_event_id, ...$this->input()];
        $key = (string) Str::uuid();
        $headers = ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => $key];
        $this->postJson($path.'/reconcile', [...$body, 'user_id' => 9], $headers)->assertUnprocessable();
        $this->postJson($path.'/reconcile', [...$body, 'expected_revision' => '3'], $headers)->assertUnprocessable();
        $this->get($path.'?extra=1', ['Accept' => 'application/json'])->assertUnprocessable();
        $this->app->instance('env', 'local');
        $this->postJson($path.'/reconcile', $body, Arr::except($headers, 'X-CSRF-TOKEN'))->assertStatus(419);
        $this->postJson($path.'/reconcile', $body, [...$headers, 'Origin' => 'https://evil.example'])->assertForbidden();
        $this->postJson($path.'/reconcile', $body, $headers)->assertOk()->assertJsonPath('data.order.payment_status', 'paid')->assertJsonCount(1, 'data.resolutions');
        $this->postJson($path.'/reconcile', $body, $headers)->assertOk()->assertJsonCount(1, 'data.resolutions');
        $this->actingAs($this->customer)->get($path, ['Accept' => 'application/json'])->assertForbidden();
        $this->postJson($path.'/reconcile', $body, $headers)->assertForbidden();
        $this->assertDatabaseCount('payment_settlements', 1);
    }

    public function test_native_admin_uses_shared_action_escapes_notes_and_reauthorizes_hydration(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $url = PaymentResource::getUrl('view', ['record' => $this->attempt]);
        $this->assertStringEndsWith('/admin/payments/'.$this->attempt->public_id, $url);
        $this->get($url)->assertOk();
        Livewire::test(ListPayments::class)->assertSuccessful()->assertCanSeeTableRecords([$this->attempt]);
        $page = Livewire::test(ViewPayment::class, ['record' => $this->attempt->public_id]);
        $page->assertSuccessful()->assertSee($this->receipt->provider_event_id)->assertSee('No verified settlement');
        $page->callAction('reconcile', ['event' => $this->receipt->provider_event_id, 'note' => '<script>synthetic</script>'])
            ->assertHasNoActionErrors()->assertActionHidden('reconcile')->assertSee('Verified settlement')->assertSee('&lt;script&gt;synthetic&lt;/script&gt;', false)->assertDontSee('<script>synthetic</script>', false);
        $this->admin->revokePermissionTo(PaymentCapability::View->value);
        $page->call('$refresh')->assertForbidden();
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($this->attempt));
        $this->assertFalse(PaymentResource::canDelete($this->attempt));
    }

    public function test_resolution_history_is_immutable_and_receipt_from_other_attempt_is_not_selectable(): void
    {
        $this->denied(404, fn () => app(PaymentOperations::class)->reconcile($this->admin, $this->attempt->public_id, 'evt_foreign', $this->input(), (string) Str::uuid()));
        $resolution = $this->reconcile();
        foreach (['edit', 'delete'] as $action) {
            try {
                $action === 'edit' ? $resolution->forceFill(['note' => 'Rewrite'])->save() : $resolution->delete();
                $this->fail('Audit history changed.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('history', $error->errors());
            }
        }
    }

    private function input(): array
    {
        return ['expected_revision' => 3, 'note' => 'Verified preserved signed receipt against current provider state.'];
    }

    private function reconcile(?string $key = null, ?array $input = null): PaymentResolution
    {
        return app(PaymentOperations::class)->reconcile($this->admin, $this->attempt->public_id, $this->receipt->provider_event_id, $input ?? $this->input(), $key ?? (string) Str::uuid());
    }

    private function fakeProvider(?callable $during = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function () use ($during) {
            $during?->__invoke();

            return Http::response($this->provider);
        });
    }

    private function assertUnchanged(): void
    {
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('active', $this->attempt->fresh()->state);
        $this->assertSame('rejected', $this->receipt->fresh()->state);
        $this->assertDatabaseCount('payment_settlements', 0);
        $this->assertDatabaseCount('payment_resolutions', 0);
    }

    private function denied(int $status, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected denial '.$status);
        } catch (ModelNotFoundException $error) {
            $this->assertSame(404, $status);
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }
}
