<?php

namespace App\Actions\Payments;

use App\Exceptions\PayMongoFailure;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\PaymentResolution;
use App\Models\PaymentSettlement;
use App\Models\User;
use App\Policies\PaymentAttemptPolicy;
use App\Services\Orders\CanonicalValue;
use App\Services\Payments\PayMongoClient;
use App\Support\Database\RetryingTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class PaymentOperations
{
    public function read(User $actor, string $reference, int $page = 1): array
    {
        abort_unless($page >= 1 && $page <= 10000, 422);

        return RetryingTransaction::run(function () use ($actor, $reference, $page): array {
            [$fresh, $order, $attempt] = $this->context($actor, $reference);
            $events = PaymentEvent::where('payment_attempt_id', $attempt->id)->orderByDesc('id')
                ->paginate(20, ['provider_event_id', 'type', 'state', 'reason', 'processed_at'], 'event_page', $page);

            return ['attempt' => $attempt->only(['public_id', 'state', 'amount_minor', 'currency', 'livemode', 'provider_session_id', 'failure_code', 'created_at']),
                'order' => $order->only(['public_id', 'revision', 'payment_status']),
                'events' => $events,
                'settlement' => PaymentSettlement::where('payment_attempt_id', $attempt->id)->first()?->only(['public_id', 'provider_payment_id', 'amount_minor', 'currency', 'paid_at']),
                'resolutions' => PaymentResolution::where('payment_attempt_id', $attempt->id)->orderByDesc('id')->limit(20)
                    ->get(['public_id', 'original_state', 'original_reason', 'note', 'actor_id', 'created_at']),
                'can_reconcile' => app(PaymentAttemptPolicy::class)->reconcile($fresh, $attempt)
                    && $attempt->provider_session_id !== null && PaymentEvent::where('payment_attempt_id', $attempt->id)
                        ->where('type', 'checkout_session.payment.paid')->whereIn('state', ['rejected', 'needs_review'])
                        ->whereNotIn('id', PaymentResolution::select('payment_event_id'))->exists()];
        });
    }

    public function reconcile(User $actor, string $reference, string $eventId, array $input, string $operationId): PaymentResolution
    {
        Validator::make(['event' => $eventId, 'input' => $input, 'operation_id' => $operationId], [
            'event' => ['required', 'string', 'regex:/^evt_[A-Za-z0-9_]{1,96}$/D'], 'operation_id' => ['required', 'uuid'],
            'input' => ['required', 'array:expected_revision,note'], 'input.expected_revision' => ['required', 'integer:strict', 'min:1'],
            'input.note' => ['required', 'string', 'max:1000', 'regex:/\S/u'],
        ])->validate();
        $operationId = strtolower($operationId);
        $hash = CanonicalValue::hash(['attempt' => $reference, 'event' => $eventId, 'input' => $input]);
        $preflight = RetryingTransaction::run(function () use ($actor, $reference, $eventId, $input, $operationId, $hash): PaymentAttempt|PaymentResolution {
            [$fresh, $order, $attempt] = $this->context($actor, $reference, true);
            $replay = $this->replay($fresh, $attempt, $operationId, $hash);
            if ($replay !== null) {
                return $replay;
            }
            $this->receipt($attempt, $eventId);
            abort_unless($order->revision === $input['expected_revision'] && $attempt->provider_session_id !== null, 409);

            return $attempt;
        });
        if ($preflight instanceof PaymentResolution) {
            return $preflight;
        }
        try {
            // A bounded GET outside locks. No creation/retry or provider mutation.
            $current = app(PayMongoClient::class)->retrieve($preflight->provider_session_id);
        } catch (PayMongoFailure) {
            abort(503, 'Provider verification is unavailable. No reconciliation was applied.');
        }

        return RetryingTransaction::run(function () use ($actor, $reference, $eventId, $input, $operationId, $hash, $current): PaymentResolution {
            [$fresh, $order, $attempt] = $this->context($actor, $reference, true);
            $replay = $this->replay($fresh, $attempt, $operationId, $hash);
            if ($replay !== null) {
                return $replay;
            }
            $event = $this->receipt($attempt, $eventId);
            abort_unless($order->revision === $input['expected_revision'], 409);
            try {
                $settler = app(PaymentSettlementActions::class);
                [$providerPayment, $providerIntent] = $settler->verify($attempt, $current, true);
                // Current paid gross/status can remain unchanged after a refund
                // or dispute. This narrow recovery must not erase those facts.
                abort_unless(($providerPayment['attributes']['disputed'] ?? null) === false
                    && ($providerPayment['attributes']['refunds'] ?? null) === [], 409);
                [$signedPayment, $signedIntent] = $settler->verify($attempt, $event->evidence);
                abort_unless($providerPayment['id'] === $signedPayment['id'] && $providerIntent['id'] === $signedIntent['id'], 409);
                $settler->apply($event, $attempt, $order);
            } catch (PayMongoFailure) {
                abort(409, 'Signed receipt and current provider state do not match this captured payment.');
            }
            $resolution = (new PaymentResolution)->forceFill(['public_id' => (string) Str::ulid(), 'payment_event_id' => $event->id,
                'payment_attempt_id' => $attempt->id, 'actor_id' => $fresh->id, 'operation_id' => $operationId, 'request_hash' => $hash,
                'original_state' => $event->state, 'original_reason' => $event->reason, 'note' => $input['note'],
                'verification' => ['provider_session_id' => $attempt->provider_session_id, 'provider_payment_id' => $providerPayment['id'],
                    'provider_intent_id' => $providerIntent['id'], 'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency,
                    'livemode' => false, 'disputed' => false, 'refunds' => [], 'observed_at' => now()->toISOString()]]);
            $resolution->save();
            (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => $fresh->id, 'operation_id' => $operationId, 'request_hash' => $hash,
                'action' => 'payment_reconciled', 'before' => ['receipt_state' => $event->state, 'receipt_reason' => $event->reason],
                'after' => ['resolution' => $resolution->public_id, 'payment_status' => $order->payment_status]])->save();

            return $resolution;
        });
    }

    private function context(User $actor, string $reference, bool $reconcile = false): array
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $candidate = PaymentAttempt::where('public_id', $reference)->firstOrFail();
        $policy = app(PaymentAttemptPolicy::class);
        abort_unless($reconcile ? $policy->reconcile($fresh, $candidate) : $policy->inspect($fresh, $candidate), 403);
        $order = Order::whereKey($candidate->order_id)->lockForUpdate()->firstOrFail();
        $attempt = PaymentAttempt::whereKey($candidate->id)->lockForUpdate()->firstOrFail();

        return [$fresh, $order, $attempt];
    }

    private function receipt(PaymentAttempt $attempt, string $eventId): PaymentEvent
    {
        $event = PaymentEvent::where('payment_attempt_id', $attempt->id)->where('provider_event_id', $eventId)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($event->state, ['rejected', 'needs_review'], true) && $event->type === 'checkout_session.payment.paid'
            && ! $event->livemode && ! PaymentResolution::where('payment_event_id', $event->id)->exists(), 409);

        return $event;
    }

    private function replay(User $actor, PaymentAttempt $attempt, string $operationId, string $hash): ?PaymentResolution
    {
        $receipt = PaymentResolution::where('actor_id', $actor->id)->where('operation_id', $operationId)->lockForUpdate()->first();
        if ($receipt !== null) {
            abort_unless($receipt->payment_attempt_id === $attempt->id && hash_equals($receipt->request_hash, $hash), 409);
        } else {
            abort_if(OrderEvent::where('actor_id', $actor->id)->where('operation_id', $operationId)->lockForUpdate()->exists(), 409);
        }

        return $receipt;
    }
}
