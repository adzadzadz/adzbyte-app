<?php

namespace App\Actions\Payments;

use App\Exceptions\PayMongoFailure;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Services\Payments\CheckoutEvent;
use App\Services\Payments\PayMongoSignature;
use App\Support\Database\RetryingTransaction;
use Illuminate\Database\UniqueConstraintViolationException;

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
                    app(PaymentSettlementActions::class)->apply($event, $attempt, $order);
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

    private function finish(PaymentEvent $event, string $state, ?string $reason = null): PaymentEvent
    {
        $event->forceFill(['state' => $state, 'reason' => $reason, 'processed_at' => now()])->save();

        return $event;
    }
}
