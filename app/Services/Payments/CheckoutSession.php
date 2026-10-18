<?php

namespace App\Services\Payments;

use App\Exceptions\PayMongoFailure;
use App\Models\OrderRevision;
use App\Models\PaymentAttempt;
use App\Services\Orders\CanonicalValue;
use Illuminate\Support\Arr;

final class CheckoutSession
{
    public static function validateRequest(OrderRevision $accepted): void
    {
        // Hosted v2 defers its Payment Intent. Reject predictable limits before
        // reserving an attempt, including the separately itemized exclusive tax.
        abort_unless(strlen($accepted->total_minor) <= 9 && (int) $accepted->total_minor >= 100, 422,
            'PayMongo supports totals from PHP 1.00 to PHP 9,999,999.99.');
        $lineCount = $accepted->lines->count() + ($accepted->policy_snapshot['tax_mode'] === 'exclusive' && $accepted->tax_minor !== '0' ? 1 : 0);
        abort_unless($lineCount >= 1 && $lineCount <= 999, 422, 'PayMongo supports up to 999 checkout line items including itemized tax.');
    }

    public static function verify(PaymentAttempt $attempt, array $response): array
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $expected = $attempt->provider_payload['data']['attributes'];
        $url = $attributes['checkout_url'] ?? null;
        $parts = is_string($url) ? parse_url($url) : false;
        $lines = $attributes['line_items'] ?? null;
        if (($data['id'] ?? null) !== $attempt->provider_session_id || ($data['type'] ?? null) !== 'checkout_session'
            || ($attributes['livemode'] ?? null) !== $attempt->livemode
            || ! in_array($attributes['status'] ?? null, ['active', 'expired'], true)
            || ($attributes['reference_number'] ?? null) !== $attempt->public_id
            || ($attributes['pass_on_fees'] ?? null) !== false
            || ! is_array($lines) || ! array_is_list($lines)
            || ! is_array($attributes['metadata'] ?? null)
            || ! is_array($attributes['payment_method_types'] ?? null)
            || ! is_array($attributes['payments'] ?? null) || ! array_is_list($attributes['payments'])
            || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'checkout.paymongo.com'
            // The actual v2 hosted URL omits cs_; older documented URLs include it.
            || ! in_array($parts['path'] ?? null, ['/'.$attempt->provider_session_id, '/'.substr($attempt->provider_session_id, 3)], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new PayMongoFailure('session_mismatch');
        }
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new PayMongoFailure('session_mismatch');
            }
        }
        if (! hash_equals(CanonicalValue::hash($expected['line_items']), CanonicalValue::hash(array_map(fn ($line) => Arr::only($line, ['name', 'amount', 'currency', 'quantity']), $lines)))
            || ! hash_equals(CanonicalValue::hash($expected['metadata']), CanonicalValue::hash(Arr::only($attributes['metadata'], array_keys($expected['metadata']))))
            || ! hash_equals(CanonicalValue::hash($expected['payment_method_types']), CanonicalValue::hash($attributes['payment_method_types']))) {
            throw new PayMongoFailure('session_mismatch');
        }

        return $attributes;
    }
}
