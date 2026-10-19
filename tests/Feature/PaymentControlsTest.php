<?php

namespace Tests\Feature;

use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Payments\PaymentAttemptActions;
use App\Actions\Payments\PaymentControlActions;
use App\Actions\Payments\PaymentEventActions;
use App\Enums\PaymentCapability;
use App\Filament\Pages\CommerceSettings;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\PaymentAttempt;
use App\Models\PaymentControl;
use App\Models\User;
use App\Services\Orders\CommerceRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\TestCase;

class PaymentControlsTest extends TestCase
{
    use CreatesPaymentAttempt, RefreshDatabase;

    private array $remote;

    private int $posts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPaymentAttempt();
        $this->admin->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $this->remote = ['data' => PaymentEventFixture::payload($this->attempt)['data']['attributes']['data']];
        $this->remote['data']['attributes']['payments'] = [];
        $this->remote['data']['attributes']['payment_intent'] = null;
        $this->provider();
    }

    public function test_customer_cancels_only_after_verified_provider_expiry_and_replay_never_resends(): void
    {
        $key = (string) Str::uuid();
        $command = $this->start('cancel', $key);
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('expired', $this->attempt->fresh()->state);
        $this->assertNull($this->attempt->fresh()->checkout_url);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('cancelled', $this->order->fresh()->fulfillment_status);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->payment_attempt_id);
        $this->assertSame(5, $this->order->fresh()->revision);
        $this->assertSame('expired', $command->proof['status']);
        $this->assertStringNotContainsString('cs_signed', $command->getRawOriginal('proof'));
        $this->assertSame($command->id, $this->start('cancel', $key)->id);
        $this->assertSame(1, $this->posts);
        Http::assertSentCount(3);
        $this->denied(409, fn () => $this->start('cancel'));
        $this->assertDatabaseCount('payment_controls', 1);
        $this->assertDatabaseCount('order_events', 5);
    }

    public function test_admin_expiry_preserves_order_and_allows_one_fresh_attempt_after_closure(): void
    {
        $command = $this->start('expire');
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('open', $this->order->fresh()->status);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
                return Http::response(['qrph']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'cs_successor', 'type' => 'checkout_session']]);
            }
            $attempt = PaymentAttempt::latest('id')->firstOrFail();

            return Http::response(['data' => ['id' => 'cs_successor', 'type' => 'checkout_session', 'attributes' => [
                ...$attempt->provider_payload['data']['attributes'], 'status' => 'active', 'livemode' => false, 'payments' => [], 'checkout_url' => 'https://checkout.paymongo.com/successor',
            ]]]);
        });
        $next = app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 5, (string) Str::uuid());
        $this->assertSame('active', $next->state);
        $this->assertNotSame($this->attempt->id, $next->id);
        $this->assertSame('expired', $this->attempt->fresh()->state);
        $this->assertSame($next->id, $this->order->fresh()->payment_attempt_id);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('payment_settlements', 0);
    }

    public function test_ambiguous_expiry_keeps_pointer_and_hides_payment_then_admin_get_only_verification_recovers(): void
    {
        $this->provider(expire: false);
        $key = (string) Str::uuid();
        $command = $this->start('cancel', $key);
        $this->assertSame('unknown', $command->state);
        $this->protected();
        $this->assertSame($command->id, $this->start('cancel', $key)->id);
        $this->assertSame(1, $this->posts);
        $this->remote['data']['attributes']['status'] = 'expired';
        $check = $this->start('verify', input: ['expected_revision' => 4, 'note' => 'Verify prior unknown cancellation', 'control' => $command->public_id]);
        $this->assertSame('confirmed', $check->state);
        $this->assertSame($command->id, $check->target_id);
        $this->assertSame('unknown', $command->fresh()->state);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(1, $this->posts);
        $this->assertDatabaseCount('payment_controls', 2);
    }

    public function test_provider_success_local_failure_preserves_reservation_for_get_only_recovery(): void
    {
        PaymentControl::saving(function ($command): void {
            if ($command->state === 'confirmed') {
                throw new \RuntimeException('Synthetic local commit failure');
            }
        });
        $key = (string) Str::uuid();
        try {
            $this->start('expire', $key);
            $this->fail('Local commit failure swallowed.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic local commit failure', $error->getMessage());
        }
        $this->protected();
        $command = PaymentControl::sole();
        $this->assertSame('pending', $command->state);
        $this->assertSame('pending', $this->start('expire', $key)->state);
        $this->assertSame(1, $this->posts);
        PaymentControl::flushEventListeners();
        PaymentControl::clearBootedModels();
        $check = $this->start('verify', input: ['expected_revision' => 4, 'note' => 'Read back successful provider expiry', 'control' => $command->public_id]);
        $this->assertSame('confirmed', $check->state);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertSame(1, $this->posts);
    }

    public function test_explicit_admin_closure_can_complete_original_customer_cancellation_without_replaying_its_post(): void
    {
        $this->provider(expire: false);
        $original = $this->start('cancel');
        $this->assertSame('unknown', $original->state);
        $this->provider();
        $completion = $this->start('expire', input: ['expected_revision' => 4, 'note' => 'Explicit new Admin closure for the customer cancellation', 'control' => $original->public_id]);
        $this->assertSame('confirmed', $completion->state);
        $this->assertSame($original->id, $completion->target_id);
        $this->assertSame('unknown', $original->fresh()->state);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(2, $this->posts);
        $this->assertDatabaseCount('payment_controls', 2);
        $this->assertSame('expired', $this->attempt->fresh()->state);
    }

    public function test_already_expired_session_is_verified_without_an_expiry_post(): void
    {
        $this->remote['data']['attributes']['status'] = 'expired';
        $command = $this->start('expire');
        $this->assertSame('confirmed', $command->state);
        $this->assertSame(0, $this->posts);
        Http::assertSentCount(1);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
    }

    public function test_failed_payment_history_does_not_prevent_verified_closure_and_cancelled_intent_proof(): void
    {
        $resource = PaymentEventFixture::payload($this->attempt)['data']['attributes']['data']['attributes'];
        $resource['payments'][0]['attributes']['status'] = 'failed';
        $resource['payment_intent']['attributes']['status'] = 'awaiting_payment_method';
        $this->remote['data']['attributes'] = $resource;
        $command = $this->start('cancel');
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('cancelled', $command->proof['intent_status']);
        $this->assertSame(1, $command->proof['failed_payment_count']);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertDatabaseCount('payment_settlements', 0);
    }

    public function test_unavailable_initial_get_records_unknown_without_expiry_post_or_replay_retry(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::failedConnection());
        $key = (string) Str::uuid();
        $command = $this->start('expire', $key);
        $this->assertSame('unknown', $command->state);
        $this->assertSame('transport_unknown', $command->failure_code);
        $this->assertSame($command->id, $this->start('expire', $key)->id);
        Http::assertSentCount(1);
        $this->protected();
    }

    public function test_paid_webhook_winning_while_expiry_is_in_flight_cannot_be_cancelled_or_duplicated(): void
    {
        $this->provider(onPost: function (): void {
            $payload = PaymentEventFixture::payload($this->attempt, 'evt_closure_winner');
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
            $this->remote = ['data' => $payload['data']['attributes']['data']];
        }, expire: false);
        $command = $this->start('cancel');
        $this->assertSame('blocked', $command->state);
        $this->assertSame('open', $this->order->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame('paid', $this->attempt->fresh()->state);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertSame(5, $this->order->fresh()->revision);
    }

    #[DataProvider('incompatible')]
    public function test_paid_processing_live_or_mismatched_readbacks_block_before_expiry_post(string $path, mixed $value): void
    {
        Arr::set($this->remote, $path, $value);
        $command = $this->start('expire');
        $this->assertSame('blocked', $command->state);
        $this->assertSame(0, $this->posts);
        $this->protected();
    }

    public static function incompatible(): array
    {
        return [
            ['data.attributes.livemode', true], ['data.attributes.reference_number', 'foreign'], ['data.attributes.line_items.0.amount', 1],
            ['data.attributes.payment_intent', ['id' => 'pi_processing', 'type' => 'payment_intent', 'attributes' => ['amount' => 12500, 'currency' => 'PHP', 'livemode' => false, 'status' => 'processing']]],
            ['data.attributes.payments', [['id' => 'pay_pending', 'type' => 'payment', 'attributes' => ['amount' => 12500, 'currency' => 'PHP', 'livemode' => false, 'status' => 'pending']]]],
            ['data.attributes.payments', [['id' => 'pay_paid', 'type' => 'payment', 'attributes' => ['amount' => 12500, 'currency' => 'PHP', 'livemode' => false, 'status' => 'paid']]]],
        ];
    }

    public function test_revocation_during_get_prevents_provider_post_and_order_transition(): void
    {
        $this->provider(onGet: function (): void {
            $this->admin->revokePermissionTo(PaymentCapability::Expire->value);
        });
        $this->denied(403, fn () => $this->start('expire'));
        $this->assertSame(0, $this->posts);
        $this->protected();
        $this->assertSame('authorization_changed', PaymentControl::sole()->failure_code);
    }

    public function test_unknown_creation_recovers_only_matching_unused_session_and_never_creates_another(): void
    {
        // Fault fixture: provider created the session but the identity commit
        // failed. This is not an application mutation path.
        DB::table('payment_attempts')->where('id', $this->attempt->id)->update(['state' => 'unknown', 'provider_session_id' => null, 'checkout_url' => null]);
        $command = $this->start('recover', input: ['expected_revision' => 3, 'note' => 'Verify recovered provider identity', 'session' => 'cs_signed']);
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('active', $this->attempt->fresh()->state);
        $this->assertSame('cs_signed', $this->attempt->fresh()->provider_session_id);
        $this->assertSame('https://checkout.paymongo.com/signed#private', $this->attempt->fresh()->checkout_url);
        $this->assertSame(0, $this->posts);
        Http::assertSentCount(1);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_settlements', 0);
    }

    public function test_foreign_recovery_proof_stays_unbound_and_a_later_verified_candidate_can_recover(): void
    {
        DB::table('payment_attempts')->where('id', $this->attempt->id)->update(['state' => 'unknown', 'provider_session_id' => null, 'checkout_url' => null]);
        $this->remote['data']['attributes']['reference_number'] = 'foreign';
        $bad = $this->start('recover', input: ['expected_revision' => 3, 'note' => 'Candidate must be verified', 'session' => 'cs_signed']);
        $this->assertSame('blocked', $bad->state);
        $this->assertNull($this->attempt->fresh()->provider_session_id);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->remote['data']['attributes']['reference_number'] = $this->attempt->public_id;
        $good = $this->start('recover', input: ['expected_revision' => 4, 'note' => 'Verified exact captured candidate', 'session' => 'cs_signed']);
        $this->assertSame('confirmed', $good->state);
        $this->assertSame('active', $this->attempt->fresh()->state);
        $this->assertSame(0, $this->posts);
        $this->assertSame('blocked', $bad->fresh()->state);
    }

    public function test_revocation_after_remote_expiry_requires_authorized_get_only_recovery(): void
    {
        $reads = 0;
        $this->provider(onGet: function () use (&$reads): void {
            if (++$reads === 2) {
                $this->admin->revokePermissionTo(PaymentCapability::Expire->value);
            }
        });
        $this->denied(403, fn () => $this->start('expire'));
        $this->assertSame(1, $this->posts);
        $this->assertSame('expired', $this->remote['data']['attributes']['status']);
        $this->protected();
        $original = PaymentControl::sole();
        $this->assertSame('blocked', $original->state);
        $check = $this->start('verify', input: ['expected_revision' => 4, 'note' => 'Authorized verification after permission change', 'control' => $original->public_id]);
        $this->assertSame('confirmed', $check->state);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertSame(1, $this->posts);
    }

    public function test_a_concurrent_verification_winning_during_readback_cannot_be_overwritten(): void
    {
        $reads = 0;
        $this->provider(onGet: function () use (&$reads): void {
            if (++$reads === 2) {
                $original = PaymentControl::sole();
                $this->start('verify', input: ['expected_revision' => 4, 'note' => 'Concurrent authorized verification', 'control' => $original->public_id]);
            }
        });
        $original = $this->start('expire');
        $this->assertSame('blocked', $original->state);
        $this->assertSame('request_changed', $original->failure_code);
        $this->assertSame(1, PaymentControl::where('state', 'confirmed')->count());
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertSame(6, $this->order->fresh()->revision);
        $this->assertSame('expired', $this->attempt->fresh()->state);
        $this->assertSame(1, $this->posts);
        $this->assertDatabaseCount('payment_settlements', 0);
    }

    public function test_captured_cancellation_setting_survives_later_settings_change_and_new_setting_denies_new_order(): void
    {
        $content = CommerceRules::defaults();
        $content['payment']['customer_cancel_before_payment'] = false;
        app(CommercePolicyActions::class)->publish($this->admin, $content, (string) Str::uuid());
        $this->assertSame('confirmed', $this->start('cancel')->state);
        $this->createPaymentOrder();
        $this->denied(409, fn () => app(PaymentControlActions::class)->start($this->customer, $this->order->public_id, 'cancel', ['expected_revision' => 2], (string) Str::uuid()));
        $this->assertSame('open', $this->order->fresh()->status);
        $this->assertTrue(CommerceRules::paymentRules([])['customer_cancel_before_payment']);
    }

    public function test_exact_permissions_revision_uuid_history_and_pointer_guards(): void
    {
        $this->admin->revokePermissionTo(PaymentCapability::Expire->value);
        $this->denied(403, fn () => $this->start('expire'));
        $this->admin->givePermissionTo(PaymentCapability::Expire->value);
        $prior = $this->order->events()->where('action', 'approve')->sole()->operation_id;
        $this->denied(409, fn () => $this->start('expire', $prior));
        $this->denied(409, fn () => $this->start('expire', input: ['expected_revision' => 1, 'note' => 'Stale']));
        Http::assertNothingSent();
        try {
            $this->order->fresh()->forceFill(['payment_status' => 'unpaid', 'payment_attempt_id' => null, 'revision' => 4])->save();
            $this->fail('Unverified pointer retirement accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('payment', $error->errors());
        }
        $command = $this->start('expire');
        foreach (['edit', 'delete'] as $action) {
            try {
                $action === 'edit' ? $command->forceFill(['note' => 'Rewrite'])->save() : $command->delete();
                $this->fail('Completed control history changed.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('payment', $error->errors());
            }
        }
    }

    public function test_customer_api_enforces_real_csrf_origin_owner_strict_input_and_safe_cancel_replay(): void
    {
        config(['app.url' => 'https://app.example.test']);
        $this->withSession(['_token' => 'csrf'])->actingAs($this->customer);
        $this->app->instance('env', 'local');
        $path = '/api/v1/orders/'.$this->order->public_id.'/cancel';
        $input = ['expected_revision' => 3];
        $headers = ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => (string) Str::uuid()];
        $this->postJson($path, $input, Arr::except($headers, 'X-CSRF-TOKEN'))->assertStatus(419);
        $this->postJson($path, $input, [...$headers, 'Origin' => 'https://evil.example'])->assertForbidden();
        $this->postJson($path, [...$input, 'user_id' => 1], $headers)->assertUnprocessable();
        $this->postJson($path, ['expected_revision' => '3'], $headers)->assertUnprocessable();
        $this->postJson($path.'?action=expire', $input, $headers)->assertUnprocessable();
        $other = User::factory()->create();
        $other->assignRole('customer');
        $this->actingAs($other)->postJson($path, $input, $headers)->assertNotFound();
        Http::assertNothingSent();
        $response = $this->actingAs($this->customer)->postJson($path, $input, $headers);
        $response->assertCreated()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.order.status', 'cancelled')
            ->assertJsonPath('data.command.state', 'confirmed')->assertDontSee('provider_session_id')->assertDontSee('proof')->assertDontSee('actor_id')->assertDontSee('request_hash');
        $id = $response->json('data.command.public_id');
        $this->postJson($path, $input, $headers)->assertCreated()->assertJsonPath('data.command.public_id', $id);
        $this->postJson($path, [...$input, 'note' => 'Changed command'], $headers)->assertConflict();
        $this->get('/orders/'.$this->order->public_id)->assertOk()->assertSee('Your request is cancelled')->assertDontSee('Cancel this request before payment')->assertDontSee('Payment options');
        $this->assertSame(1, $this->posts);
    }

    public function test_uncertain_native_cancellation_does_not_show_success_or_a_payable_continuation(): void
    {
        $this->provider(expire: false);
        config(['app.url' => 'https://app.example.test']);
        $this->withSession(['_token' => 'csrf'])->actingAs($this->customer);
        $this->post('/orders/'.$this->order->public_id.'/cancel', ['expected_revision' => '3', 'operation_id' => (string) Str::uuid(), '_token' => 'csrf'], ['Origin' => 'https://app.example.test'])
            ->assertRedirect('/orders/'.$this->order->public_id)->assertSessionHas('status', 'Cancellation is awaiting verification. Contact Admin before trying to pay again.');
        $this->get('/orders/'.$this->order->public_id)->assertOk()->assertDontSee('Cancel this request before payment');
        $this->get('/orders/'.$this->order->public_id.'/payment')->assertOk()->assertSee('being closed or verified')->assertDontSee('Continue to PayMongo');
        $this->protected();
    }

    public function test_native_admin_expiry_uses_shared_action_and_preserves_safe_command_history(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $page = Livewire::test(ViewPayment::class, ['record' => $this->attempt->public_id]);
        $page->assertSuccessful()->assertActionVisible('expire')->assertActionHidden('recover');
        $page->callAction('expire', ['note' => '<script>synthetic closure note</script>'])->assertHasNoActionErrors()
            ->assertActionHidden('expire')->assertSee('expired')->assertSee('&lt;script&gt;synthetic closure note&lt;/script&gt;', false)
            ->assertDontSee('<script>synthetic closure note</script>', false);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertSame(1, $this->posts);
    }

    public function test_management_control_api_capabilities_and_empty_false_legacy_settings_rules(): void
    {
        config(['app.url' => 'https://app.example.test']);
        $this->withSession(['_token' => 'csrf'])->actingAs($this->admin);
        $headers = ['Origin' => 'https://app.example.test', 'X-CSRF-TOKEN' => 'csrf', 'Idempotency-Key' => (string) Str::uuid()];
        $path = '/api/v1/management/payments/'.$this->attempt->public_id.'/expire';
        $body = ['expected_revision' => 3, 'note' => 'Authorized HTTP expiry'];
        $this->postJson($path, [...$body, 'session' => 'cs_foreign'], $headers)->assertUnprocessable();
        $this->admin->revokePermissionTo(PaymentCapability::Expire->value);
        $this->postJson($path, $body, $headers)->assertForbidden();
        Http::assertNothingSent();
        $this->admin->givePermissionTo(PaymentCapability::Expire->value);
        $this->postJson($path, $body, $headers)->assertOk()->assertJsonPath('data.controls.0.state', 'confirmed')->assertJsonPath('data.can_expire', false)
            ->assertDontSee('proof')->assertDontSee('checkout_url')->assertDontSee('provider_payload');
        $settings = '/api/v1/management/commerce-settings';
        $content = CommerceRules::defaults();
        $version = app(CommercePolicyActions::class)->current()->version;
        $content['payment'] = [];
        $this->putJson($settings, ['expected_version' => $version, 'content' => $content], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame($version, app(CommercePolicyActions::class)->current()->version);
        $content['payment'] = ['customer_cancel_before_payment' => false];
        $this->putJson($settings, ['expected_version' => $version, 'content' => $content], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertOk()
            ->assertJsonPath('data.content.payment.customer_cancel_before_payment', false);
        unset($content['payment']);
        $this->putJson($settings, ['expected_version' => $version + 1, 'content' => $content], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertOk()
            ->assertJsonPath('data.content.payment.customer_cancel_before_payment', true);
    }

    public function test_native_cancellation_setting_is_configurable_and_does_not_rewrite_accepted_policy(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $content = app(CommercePolicyActions::class)->current()->content;
        $content['payment']['customer_cancel_before_payment'] = false;
        Livewire::test(CommerceSettings::class)->fillForm($content)->call('save')->assertHasNoFormErrors();
        $this->assertFalse(app(CommercePolicyActions::class)->current()->content['payment']['customer_cancel_before_payment']);
        $this->assertTrue($this->order->fresh()->currentRevision->policy_snapshot['payment']['customer_cancel_before_payment']);
    }

    private function start(string $action, ?string $key = null, ?array $input = null): PaymentControl
    {
        return app(PaymentControlActions::class)->start($action === 'cancel' ? $this->customer : $this->admin, $this->order->public_id, $action,
            $input ?? ['expected_revision' => 3, 'note' => 'Synthetic '.$action.' request'], $key ?? (string) Str::uuid(), $action === 'cancel' ? null : $this->attempt->public_id);
    }

    private function provider(?callable $onGet = null, ?callable $onPost = null, bool $expire = true): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($onGet, $onPost, $expire) {
            if ($request->method() === 'POST') {
                $this->posts++;
                $this->assertSame('https://api.paymongo.com/v1/checkout_sessions/cs_signed/expire', $request->url());
                $onPost?->__invoke();
                if ($expire) {
                    $this->remote['data']['attributes']['status'] = 'expired';
                    if ($this->remote['data']['attributes']['payment_intent'] !== null) {
                        $this->remote['data']['attributes']['payment_intent']['attributes']['status'] = 'cancelled';
                    }
                }

                return Http::response($this->remote, $expire ? 200 : 400);
            }
            $onGet?->__invoke();

            return Http::response($this->remote);
        });
    }

    private function protected(): void
    {
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame('open', $this->order->fresh()->status);
        $this->assertSame($this->attempt->id, $this->order->fresh()->payment_attempt_id);
        $this->assertSame('closing', $this->attempt->fresh()->state);
        $this->assertNull($this->attempt->fresh()->checkout_url);
    }

    private function denied(int $code, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected denial '.$code);
        } catch (HttpException $error) {
            $this->assertSame($code, $error->getStatusCode());
        }
    }
}
