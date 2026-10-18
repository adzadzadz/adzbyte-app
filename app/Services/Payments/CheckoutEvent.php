<?php

namespace App\Services\Payments;

use App\Services\Orders\CanonicalValue;
use Illuminate\Support\Arr;

final class CheckoutEvent
{
    public static function parse(string $body): array
    {
        try {
            $event = json_decode($body, true, 64, JSON_THROW_ON_ERROR)['data'] ?? null;
        } catch (\JsonException) {
            abort(422);
        }
        abort_unless(is_array($event) && ($event['type'] ?? null) === 'event'
            && is_string($event['id'] ?? null) && preg_match('/^evt_[A-Za-z0-9_]{1,96}$/D', $event['id'])
            && is_array($event['attributes'] ?? null), 422);
        $attributes = $event['attributes'];
        abort_unless(is_string($attributes['type'] ?? null) && preg_match('/^[a-z_]+(?:\.[a-z_]+){1,4}$/D', $attributes['type']) && strlen($attributes['type']) <= 100
            && ($attributes['livemode'] ?? null) === false && is_int($attributes['created_at'] ?? null)
            && $attributes['created_at'] > 0 && $attributes['created_at'] <= now()->timestamp + 300
            && is_array($attributes['data'] ?? null), 422);
        $session = $attributes['data'];
        $evidence = [];
        if ($attributes['type'] === 'checkout_session.payment.paid') {
            $values = is_array($session['attributes'] ?? null) ? $session['attributes'] : [];
            $payments = $values['payments'] ?? null;
            $lines = $values['line_items'] ?? null;
            abort_unless(is_array($payments) && array_is_list($payments) && count($payments) <= 1000
                && is_array($lines) && array_is_list($lines) && count($lines) <= 999, 422);
            foreach ([...$payments, ...$lines] as $entry) {
                abort_unless(is_array($entry), 422);
            }
            $intent = is_array($values['payment_intent'] ?? null) ? $values['payment_intent'] : [];
            $metadata = is_array($values['metadata'] ?? null) ? $values['metadata'] : [];
            $evidence = ['data' => [
                ...Arr::only($session, ['id', 'type']), 'attributes' => [
                    ...Arr::only($values, ['livemode', 'status', 'reference_number', 'pass_on_fees', 'payment_method_types']),
                    'metadata' => Arr::only($metadata, ['attempt_reference', 'order_reference', 'accepted_version']),
                    'line_items' => array_map(fn ($line) => Arr::only($line, ['name', 'amount', 'currency', 'quantity']), $lines),
                    'payment_intent' => [...Arr::only($intent, ['id', 'type']), 'attributes' => Arr::only(is_array($intent['attributes'] ?? null) ? $intent['attributes'] : [], ['amount', 'currency', 'status', 'livemode'])],
                    'payments' => array_map(function ($payment) {
                        $values = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];

                        return [...Arr::only($payment, ['id', 'type']), 'attributes' => [
                            ...Arr::only($values, ['amount', 'currency', 'status', 'livemode', 'payment_intent_id', 'paid_at']),
                            'source' => Arr::only(is_array($values['source'] ?? null) ? $values['source'] : [], ['type']),
                        ]];
                    }, $payments),
                ],
            ]];
        }

        return ['provider_event_id' => $event['id'], 'type' => $attributes['type'], 'livemode' => false, 'provider_created_at' => $attributes['created_at'],
            'payload_hash' => CanonicalValue::hash(Arr::only($attributes, ['type', 'livemode', 'created_at', 'data'])), 'evidence' => $evidence];
    }
}
