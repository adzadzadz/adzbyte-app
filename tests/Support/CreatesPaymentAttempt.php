<?php

namespace Tests\Support;

use App\Actions\Payments\PaymentAttemptActions;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait CreatesPaymentAttempt
{
    use CreatesPaymentOrder;

    private PaymentAttempt $attempt;

    private function createPaymentAttempt(): void
    {
        $this->createPaymentOrder();
        config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only',
            'services.paymongo.methods' => ['qrph'], 'services.paymongo.webhook_secret' => PaymentEventFixture::SECRET]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
                return Http::response(['qrph']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'cs_signed', 'type' => 'checkout_session']]);
            }
            $attempt = PaymentAttempt::latest('id')->firstOrFail();

            return Http::response(['data' => ['id' => 'cs_signed', 'type' => 'checkout_session', 'attributes' => [
                ...$attempt->provider_payload['data']['attributes'], 'livemode' => false, 'status' => 'active', 'payments' => [], 'checkout_url' => 'https://checkout.paymongo.com/signed#synthetic',
            ]]]);
        });
        $this->attempt = app(PaymentAttemptActions::class)->start($this->customer, $this->order->public_id, 2, (string) Str::uuid());
    }
}
