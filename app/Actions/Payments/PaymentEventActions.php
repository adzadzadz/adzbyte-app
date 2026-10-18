<?php

namespace App\Actions\Payments;

use App\Exceptions\PayMongoFailure;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\PaymentSettlement;
use App\Services\Payments\CheckoutEvent;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PayMongoSignature;
use App\Support\Database\RetryingTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class PaymentEventActions
{
    public function receive(string $body, string $signature): PaymentEvent
    {
        abort_if(strlen($body) > 8388608, 413);
        app(PayMongoSignature::class)->verify($body, $signature);
        $input = CheckoutEvent::parse($body);
        $resource = $input['evidence']['data'] ?? [];
        $sessionId = $resource['id'] ?? null;
        $attemptReference = $resource['attributes']['metadata']['attempt_reference'] ?? null;
        $candidate = is_string($sessionId) ? PaymentAttempt::where('provider_session_id', $sessionId)->first() : null;
        $candidate ??= is_string($attemptReference) ? PaymentAttempt::where('public_id', $attemptReference)->first() : null;

        try {
            return RetryingTransaction::run(function () use ($input, $candidate): PaymentEvent {
                $order = $candidate === null ? null : Order::whereKey($candidate->order_id)->lockForUpdate()->firstOrFail();
                $attempt = $candidate === null ? null : PaymentAttempt::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                $event = PaymentEvent::where('provider_event_id', $input['provider_event_id'])->lockForUpdate()->first();
                if ($event !== null) {
                    abort_unless(hash_equals($event->payload_hash, $input['payload_hash']), 409);

                    return $event;
                }
                $event = (new PaymentEvent)->forceFill([...$input, 'payment_attempt_id' => $attempt?->id, 'state' => 'received']);
                $event->save();
                if ($input['type'] !== 'checkout_session.payment.paid') {
                    return $this->finish($event, 'ignored', 'unsupported_event');
                }
                if ($attempt === null) {
                    return $this->finish($event, 'needs_review', 'unknown_attempt');
                }
                try {
                    $this->settle($event, $attempt, $order);
                } catch (PayMongoFailure $exception) {
                    return $this->finish($event, 'rejected', $exception->reason);
                }

                return $this->finish($event, 'applied');
            });
        } catch (UniqueConstraintViolationException) {
            // Native unique constraints arbitrate simultaneous first delivery.
            // Read the committed winner under a current-read lock, not a stale
            // MySQL repeatable-read snapshot. Other uniqueness conflicts deny.
            return RetryingTransaction::run(function () use ($input): PaymentEvent {
                $event = PaymentEvent::where('provider_event_id', $input['provider_event_id'])->lockForUpdate()->first();
                abort_unless($event !== null && hash_equals($event->payload_hash, $input['payload_hash']), 409);

                return $event;
            });
        }
    }

    private function settle(PaymentEvent $event, PaymentAttempt $attempt, Order $order): void
    {
        $evidence = $event->evidence;
        $sessionId = $evidence['data']['id'] ?? null;
        if (! is_string($sessionId) || ! preg_match('/^cs_[A-Za-z0-9]+$/D', $sessionId)
            || ($attempt->provider_session_id !== null && $attempt->provider_session_id !== $sessionId)) {
            throw new PayMongoFailure('session_mismatch');
        }
        // A signed provider outcome can recover a creation whose known identity
        // failed to commit. Verify against a transient copy before binding it.
        $verification = clone $attempt;
        $verification->provider_session_id = $sessionId;
        $attributes = CheckoutSession::verify($verification, $evidence, verifyHostedUrl: false);
        $paid = array_values(array_filter($attributes['payments'], fn ($p) => is_array($p) && ($p['attributes']['status'] ?? null) === 'paid'));
        $intent = $attributes['payment_intent'] ?? [];
        $payment = count($paid) === 1 ? $paid[0] : [];
        $values = $payment['attributes'] ?? [];
        if ($attributes['status'] !== 'active' || ($payment['type'] ?? null) !== 'payment'
            || ! is_string($payment['id'] ?? null) || ! preg_match('/^pay_[A-Za-z0-9]{1,96}$/D', $payment['id'])
            || ! is_int($values['amount'] ?? null) || (string) $values['amount'] !== $attempt->amount_minor
            || ($values['currency'] ?? null) !== $attempt->currency || ($values['livemode'] ?? null) !== false
            || ! is_int($values['paid_at'] ?? null) || $values['paid_at'] <= 0 || $values['paid_at'] > now()->timestamp + 300
            || ! in_array($values['source']['type'] ?? null, $attempt->provider_payload['data']['attributes']['payment_method_types'], true)
            || ($intent['type'] ?? null) !== 'payment_intent' || ! is_string($intent['id'] ?? null)
            || ! preg_match('/^pi_[A-Za-z0-9]{1,97}$/D', $intent['id']) || ($values['payment_intent_id'] ?? null) !== $intent['id']
            || ($intent['attributes']['status'] ?? null) !== 'succeeded' || ($intent['attributes']['livemode'] ?? null) !== false
            || ! is_int($intent['attributes']['amount'] ?? null) || (string) $intent['attributes']['amount'] !== $attempt->amount_minor
            || ($intent['attributes']['currency'] ?? null) !== $attempt->currency) {
            throw new PayMongoFailure('payment_mismatch');
        }
        $existing = PaymentSettlement::where('payment_attempt_id', $attempt->id)->lockForUpdate()->first();
        if ($existing !== null) {
            if ($existing->provider_payment_id !== $payment['id'] || $existing->provider_intent_id !== $intent['id']) {
                throw new PayMongoFailure('settlement_conflict');
            }

            return;
        }
        if ($order->status !== 'open' || $order->payment_status !== 'pending' || $order->payment_attempt_id !== $attempt->id
            || ! in_array($attempt->state, ['creating', 'unknown', 'active'], true)) {
            throw new PayMongoFailure('order_requires_reconciliation');
        }
        $settlement = (new PaymentSettlement)->forceFill(['public_id' => (string) Str::ulid(), 'order_id' => $order->id, 'payment_attempt_id' => $attempt->id,
            'payment_event_id' => $event->id, 'provider_payment_id' => $payment['id'], 'provider_intent_id' => $intent['id'],
            'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency, 'livemode' => false, 'paid_at' => date('Y-m-d H:i:s', $values['paid_at'])]);
        $settlement->save();
        $attempt->forceFill(['provider_session_id' => $sessionId, 'state' => 'paid', 'checkout_url' => null, 'failure_code' => null])->save();
        $before = $order->only(['revision', 'payment_status']);
        $order->forceFill(['payment_status' => 'paid', 'revision' => $order->revision + 1])->save();
        (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => null,
            'operation_id' => (string) Uuid::uuid5('d8d44ef1-611f-41a1-b937-dff8aa3aa0cf', $attempt->public_id.':paid'),
            'request_hash' => $event->payload_hash, 'action' => 'payment_confirmed', 'before' => $before,
            'after' => ['revision' => $order->revision, 'payment_status' => 'paid', 'settlement' => $settlement->public_id]])->save();
    }

    private function finish(PaymentEvent $event, string $state, ?string $reason = null): PaymentEvent
    {
        $event->forceFill(['state' => $state, 'reason' => $reason, 'processed_at' => now()])->save();

        return $event;
    }
}
