<?php

namespace Tests\Feature;

use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Payments\PaymentControlActions;
use App\Models\CommercePolicyVersion;
use App\Services\Orders\CommerceRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesPaymentOrder;
use Tests\TestCase;

class UnpaidCancellationTest extends TestCase
{
    use CreatesPaymentOrder, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPaymentOrder();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_unpaid_owned_request_cancels_without_provider_access_and_replays_once(): void
    {
        config(['services.paymongo.enabled' => false]);
        $this->actingAs($this->customer)->get('/orders/'.$this->order->public_id)
            ->assertOk()->assertSee('Approval is valid until')->assertDontSee('Payment remains unavailable at this stage.');
        $key = (string) Str::uuid();
        $input = ['expected_revision' => 2];
        $command = app(PaymentControlActions::class)->start($this->customer, $this->order->public_id, 'cancel', $input, $key);
        $this->assertSame('confirmed', $command->state);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertNull($command->payment_attempt_id);
        $this->assertSame(4, $this->order->fresh()->revision);
        $this->assertSame($command->id, app(PaymentControlActions::class)->start($this->customer, $this->order->public_id, 'cancel', $input, $key)->id);
        $this->assertDatabaseCount('payment_controls', 1);
        $this->get('/orders/'.$this->order->public_id)->assertOk()->assertDontSee('Approval is valid until');
        Http::assertNothingSent();
    }

    public function test_settings_reject_empty_payment_rules_accept_false_and_keep_legacy_omission_compatible(): void
    {
        $content = CommerceRules::defaults();
        $content['payment'] = [];
        try {
            app(CommercePolicyActions::class)->publish($this->admin, $content, (string) Str::uuid());
            $this->fail('Empty payment rule object accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('content.payment', $error->errors());
        }
        $this->assertDatabaseCount('commerce_policy_versions', 1);
        $content['payment'] = ['customer_cancel_before_payment' => false];
        $version = app(CommercePolicyActions::class)->publish($this->admin, $content, (string) Str::uuid());
        $this->assertFalse($version->content['payment']['customer_cancel_before_payment']);
        unset($content['payment']);
        $legacyCompatible = app(CommercePolicyActions::class)->publish($this->admin, $content, (string) Str::uuid());
        $this->assertTrue($legacyCompatible->content['payment']['customer_cancel_before_payment']);
        $this->assertSame(3, CommercePolicyVersion::count());
        $this->assertFalse(CommerceRules::paymentRules(['payment' => []])['customer_cancel_before_payment']);
        $this->assertTrue(CommerceRules::paymentRules([])['customer_cancel_before_payment']);
        Http::assertNothingSent();
    }
}
