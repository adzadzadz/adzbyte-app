<?php

namespace Tests\Support;

use App\Models\PaymentAttempt;

final class PaymentEventFixture
{
    public const SECRET = 'whsec_synthetic_payment_events_only';

    public static function payload(PaymentAttempt $attempt, string $eventId = 'evt_synthetic'): array
    {
        return ['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false, 'created_at' => now()->timestamp,
            'data' => ['id' => 'cs_signed', 'type' => 'checkout_session', 'attributes' => [
                ...$attempt->provider_payload['data']['attributes'], 'status' => 'active', 'livemode' => false,
                'checkout_url' => 'https://checkout.paymongo.com/signed#private', 'client_key' => 'synthetic_client_key_do_not_store',
                'billing' => ['name' => 'Synthetic private buyer', 'email' => 'private-buyer@example.test'],
                'payment_intent' => ['id' => 'pi_signed', 'type' => 'payment_intent', 'attributes' => ['amount' => (int) $attempt->amount_minor, 'currency' => 'PHP', 'livemode' => false, 'status' => 'succeeded']],
                'payments' => [['id' => 'pay_signed', 'type' => 'payment', 'attributes' => ['amount' => (int) $attempt->amount_minor, 'currency' => 'PHP', 'livemode' => false,
                    'status' => 'paid', 'payment_intent_id' => 'pi_signed', 'paid_at' => now()->timestamp, 'source' => ['type' => 'qrph'],
                    'billing' => ['email' => 'private-buyer@example.test'], 'refunds' => []]]],
            ]],
        ]]];
    }

    public static function signature(string $body, ?int $timestamp = null): string
    {
        $timestamp ??= now()->timestamp;

        return 't='.$timestamp.',te='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET).',li=';
    }
}
