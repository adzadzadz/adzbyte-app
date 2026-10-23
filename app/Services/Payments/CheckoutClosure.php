<?php

namespace App\Services\Payments;

use App\Exceptions\PayMongoFailure;
use App\Models\PaymentAttempt;

final class CheckoutClosure
{
    public static function verify(PaymentAttempt $attempt, string $sessionId, array $response): array
    {
        if ($attempt->provider_session_id !== null && $attempt->provider_session_id !== $sessionId) {
            throw new PayMongoFailure('session_mismatch');
        }
        $candidate = clone $attempt;
        $candidate->provider_session_id = $sessionId;
        $attributes = CheckoutSession::verify($candidate, $response);
        foreach ($attributes['payments'] as $payment) {
            $values = is_array($payment) ? ($payment['attributes'] ?? []) : [];
            // Failed attempts can remain on a safely closable session. Any
            // paid, pending, processing or ambiguous payment must block closure.
            if (($payment['type'] ?? null) !== 'payment' || ! is_string($payment['id'] ?? null)
                || ! preg_match('/^pay_[A-Za-z0-9]{1,96}$/D', $payment['id'])
                || ($values['status'] ?? null) !== 'failed' || ($values['livemode'] ?? null) !== false
                || ! is_int($values['amount'] ?? null) || (string) $values['amount'] !== $attempt->amount_minor
                || ($values['currency'] ?? null) !== $attempt->currency
                || (array_key_exists('disputed', $values) && $values['disputed'] !== false)
                || (array_key_exists('refunds', $values) && $values['refunds'] !== [])) {
                throw new PayMongoFailure('payment_requires_reconciliation');
            }
        }
        $intent = $attributes['payment_intent'] ?? null;
        if ($intent !== null) {
            $values = is_array($intent) ? ($intent['attributes'] ?? []) : [];
            if (($intent['type'] ?? null) !== 'payment_intent' || ! is_string($intent['id'] ?? null)
                || ! preg_match('/^pi_[A-Za-z0-9]{1,97}$/D', $intent['id'])
                || ($values['livemode'] ?? null) !== false || ! is_int($values['amount'] ?? null)
                || (string) $values['amount'] !== $attempt->amount_minor || ($values['currency'] ?? null) !== $attempt->currency
                || ! in_array($values['status'] ?? null, $attributes['status'] === 'expired' ? ['cancelled'] : ['awaiting_payment_method', 'awaiting_next_action', 'cancelled'], true)) {
                throw new PayMongoFailure('payment_requires_reconciliation');
            }
        }

        return $attributes;
    }
}
