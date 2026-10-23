<?php

namespace App\Actions\Payments;

use App\Actions\Orders\CommercePolicyActions;
use App\Enums\UserRole;
use App\Exceptions\PayMongoFailure;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentControl;
use App\Models\User;
use App\Policies\PaymentAttemptPolicy;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderAggregate;
use App\Services\Payments\CheckoutClosure;
use App\Services\Payments\PayMongoClient;
use App\Support\Database\RetryingTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentControlActions
{
    public function canCancel(Order $order): bool
    {
        if ($order->status !== 'open' || ! in_array($order->payment_status, ['unpaid', 'pending'], true)
            || ! CommerceRules::paymentRules($order->currentRevision->policy_snapshot)['customer_cancel_before_payment']) {
            return false;
        }

        return $order->payment_attempt_id === null || PaymentAttempt::whereKey($order->payment_attempt_id)
            ->where('order_id', $order->id)->whereIn('state', ['active', 'unknown'])->whereNotNull('provider_session_id')->exists();
    }

    public function start(User $actor, string $reference, string $action, array $input, string $operationId, ?string $attemptReference = null): PaymentControl
    {
        abort_unless(in_array($action, ['cancel', 'expire', 'recover', 'verify'], true), 422);
        $keys = ['expected_revision', 'note', ...$action === 'recover' ? ['session'] : [], ...in_array($action, ['verify', 'expire'], true) ? ['control'] : []];
        Validator::make(['input' => $input, 'operation_id' => $operationId], [
            'operation_id' => ['required', 'uuid'], 'input' => ['required', 'array:'.implode(',', $keys)],
            'input.expected_revision' => ['required', 'integer:strict', 'min:1'],
            'input.note' => [$action === 'cancel' ? 'sometimes' : 'required', 'string', 'max:1000', 'regex:/\S/u'],
            ...$action === 'recover' ? ['input.session' => ['required', 'string', 'regex:/^cs_[A-Za-z0-9]{1,97}$/D']] : [],
            ...in_array($action, ['verify', 'expire'], true) ? ['input.control' => [$action === 'verify' ? 'required' : 'sometimes', 'ulid']] : [],
        ])->validate();
        $operationId = strtolower($operationId);
        $hash = CanonicalValue::hash(['order' => $reference, 'attempt' => $attemptReference, 'action' => $action, 'input' => $input]);

        $command = RetryingTransaction::run(function () use ($actor, $reference, $action, $input, $operationId, $hash, $attemptReference): PaymentControl {
            [$fresh, $order, $attempt] = $this->context($actor, $reference, $action);
            $existing = PaymentControl::where('actor_id', $fresh->id)->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->order_id === $order->id && hash_equals($existing->request_hash, $hash), 409);

                return $existing;
            }
            abort_if(OrderEvent::where('actor_id', $fresh->id)->where('operation_id', $operationId)->lockForUpdate()->exists(), 409);
            abort_unless($order->revision === $input['expected_revision'] && $order->status === 'open'
                && in_array($order->payment_status, ['unpaid', 'pending'], true), 409);
            if ($attemptReference !== null) {
                abort_unless($attempt !== null && $attempt->public_id === $attemptReference, 409);
            }
            $target = null;
            $session = $attempt?->provider_session_id;
            if ($action === 'cancel') {
                $rules = CommerceRules::paymentRules(OrderAggregate::hydrate($order)->currentRevision->policy_snapshot);
                abort_unless($rules['customer_cancel_before_payment'], 409);
                abort_if($attempt?->state === 'closing', 409);
            } else {
                abort_unless($order->payment_status === 'pending' && $attempt !== null, 409);
            }
            if ($action === 'verify' || ($action === 'expire' && isset($input['control']))) {
                $target = PaymentControl::where('public_id', $input['control'])->where('order_id', $order->id)
                    ->where('payment_attempt_id', $attempt->id)->lockForUpdate()->firstOrFail();
                abort_unless($target->action !== 'verify' && $target->state !== 'confirmed', 409);
                abort_if($action === 'expire' && ! in_array($this->effective($target), ['cancel', 'expire'], true), 409);
                $session = $target->provider_session_id;
            }
            if ($action === 'recover') {
                abort_unless($attempt->provider_session_id === null && in_array($attempt->state, ['unknown', 'closing'], true), 409);
                $session = $input['session'];
            }
            if ($attempt !== null) {
                abort_unless(is_string($session) && in_array($attempt->state, ['active', 'unknown', 'closing'], true), 409);
                app(PayMongoClient::class)->ready();
            }
            $before = $order->only(['revision', 'status', 'payment_status']);
            $command = (new PaymentControl)->forceFill(['public_id' => (string) Str::ulid(), 'order_id' => $order->id,
                'payment_attempt_id' => $attempt?->id, 'target_id' => $target?->id, 'actor_id' => $fresh->id,
                'operation_id' => $operationId, 'request_hash' => $hash, 'action' => $action, 'provider_session_id' => $session,
                'reserved_revision' => $order->revision + 1, 'state' => 'pending', 'note' => $input['note'] ?? 'Customer requested cancellation before payment.']);
            $command->save();
            if ($attempt !== null) {
                $attempt->forceFill(['state' => 'closing', 'checkout_url' => null])->save();
            }
            $order->forceFill(['revision' => $order->revision + 1])->save();
            $this->event($command, $order, $fresh, 'requested', $before);
            if ($attempt === null) {
                // No payable provider attempt exists; cancellation is local.
                $this->finish($command, null);
            } else {
                DB::afterCommit(fn () => $this->dispatch($command));
            }

            return $command;
        });

        return $command->refresh();
    }

    private function dispatch(PaymentControl $command): void
    {
        try {
            $attempt = PaymentAttempt::findOrFail($command->payment_attempt_id);
            $provider = app(PayMongoClient::class);
            $attributes = CheckoutClosure::verify($attempt, $command->provider_session_id, $provider->retrieve($command->provider_session_id));
            $effective = $this->effective($command);
            if ($command->action !== 'verify' && in_array($effective, ['cancel', 'expire'], true) && $attributes['status'] === 'active') {
                $this->authorizeDispatch($command);
                try {
                    // Exactly one dispatch for this durable command. A failed
                    // response is never a reason to resend this POST.
                    $provider->expire($command->provider_session_id);
                } catch (PayMongoFailure) {
                    // Always reconcile by GET, including an already-expired400
                    // or provider-success/transport-failure ambiguity.
                }
                $attributes = CheckoutClosure::verify($attempt, $command->provider_session_id, $provider->retrieve($command->provider_session_id));
            }
            if ($effective !== 'recover' && $attributes['status'] !== 'expired') {
                $this->outcome($command, 'unknown', 'expiry_unconfirmed');

                return;
            }
            if ($effective === 'recover' && $attributes['status'] === 'active' && $attributes['payments'] !== []) {
                $this->outcome($command, 'blocked', 'payment_requires_reconciliation');

                return;
            }
            $this->finish($command, $attributes);
        } catch (PayMongoFailure $exception) {
            $this->outcome($command, in_array($exception->reason, ['transport_unknown', 'provider_response', 'invalid_response'], true) ? 'unknown' : 'blocked', $exception->reason);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [403, 409], true)) {
                throw $exception;
            }
            $this->outcome($command, 'blocked', $exception->getStatusCode() === 403 ? 'authorization_changed' : 'request_changed');
            if ($exception->getStatusCode() === 403) {
                throw $exception;
            }
        }
    }

    private function finish(PaymentControl $original, ?array $attributes): void
    {
        RetryingTransaction::run(function () use ($original, $attributes): void {
            $actor = User::findOrFail($original->actor_id);
            [$fresh, $order, $attempt] = $this->context($actor, Order::findOrFail($original->order_id)->public_id, $original->action);
            $command = PaymentControl::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($command->state === 'confirmed') {
                return;
            }
            abort_unless($command->state === 'pending' && $order->status === 'open' && $order->revision === $command->reserved_revision
                && $order->payment_attempt_id === $command->payment_attempt_id && in_array($order->payment_status, ['unpaid', 'pending'], true), 409);
            $effective = $this->effective($command);
            $before = $order->only(['revision', 'status', 'payment_status']);
            $proof = null;
            if ($attempt !== null) {
                abort_unless($attributes !== null && $attempt->state === 'closing', 409);
                $proof = ['session_id' => $command->provider_session_id, 'status' => $attributes['status'], 'livemode' => false,
                    'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency,
                    'failed_payment_count' => count($attributes['payments']), 'intent_status' => $attributes['payment_intent']['attributes']['status'] ?? null,
                    'verified_at' => now()->toISOString()];
                if ($effective === 'recover' && $attributes['status'] === 'active') {
                    $attempt->forceFill(['provider_session_id' => $command->provider_session_id, 'state' => 'active', 'checkout_url' => $attributes['checkout_url'], 'failure_code' => null])->save();
                } else {
                    abort_unless($attributes['status'] === 'expired', 409);
                    $attempt->forceFill(['provider_session_id' => $command->provider_session_id, 'state' => 'expired', 'checkout_url' => null, 'failure_code' => null])->save();
                    $order->forceFill(['payment_status' => 'unpaid', 'payment_attempt_id' => null]);
                }
            }
            $command->forceFill(['state' => 'confirmed', 'failure_code' => null, 'proof' => $proof])->save();
            if ($effective === 'cancel') {
                $order->forceFill(['status' => 'cancelled', 'fulfillment_status' => 'cancelled']);
            }
            $order->forceFill(['revision' => $order->revision + 1])->save();
            $this->event($command, $order, $fresh, 'confirmed', $before);
        });
    }

    private function authorizeDispatch(PaymentControl $original): void
    {
        RetryingTransaction::run(function () use ($original): void {
            $actor = User::findOrFail($original->actor_id);
            [, $order, $attempt] = $this->context($actor, Order::findOrFail($original->order_id)->public_id, $original->action);
            $command = PaymentControl::whereKey($original->id)->lockForUpdate()->firstOrFail();
            abort_unless($command->state === 'pending' && $order->status === 'open' && $order->payment_status === 'pending'
                && $order->revision === $command->reserved_revision && $attempt?->id === $command->payment_attempt_id
                && $attempt->state === 'closing', 409);
        });
    }

    private function outcome(PaymentControl $original, string $state, string $reason): void
    {
        // This records only a failed/unknown transport outcome; no entitlement,
        // order state, payable URL or financial transition is granted here.
        RetryingTransaction::run(function () use ($original, $state, $reason): void {
            $order = Order::whereKey($original->order_id)->lockForUpdate()->firstOrFail();
            $command = PaymentControl::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($command->state !== 'pending') {
                return;
            }
            $command->forceFill(['state' => $state, 'failure_code' => $reason])->save();
            $this->event($command, $order, null, $state, ['command_state' => 'pending']);
        });
    }

    private function context(User $actor, string $reference, string $action): array
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if ($action === 'cancel') {
            abort_unless($fresh->hasVerifiedEmail() && $fresh->hasRole(UserRole::Customer->value), 403);
        } else {
            $ability = $action === 'verify' ? 'reconcile' : $action;
            abort_unless(app(PaymentAttemptPolicy::class)->{$ability}($fresh, new PaymentAttempt), 403);
        }
        app(CommercePolicyActions::class)->current();
        $query = Order::where('public_id', $reference);
        if ($action === 'cancel') {
            $query->where('user_id', $fresh->id);
        }
        $order = $query->lockForUpdate()->firstOrFail();
        $attempt = $order->payment_attempt_id === null ? null : PaymentAttempt::whereKey($order->payment_attempt_id)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();

        return [$fresh, $order, $attempt];
    }

    private function effective(PaymentControl $command): string
    {
        while ($command->target_id !== null) {
            $target = PaymentControl::findOrFail($command->target_id);
            abort_unless($target->id < $command->id && $target->order_id === $command->order_id && $target->payment_attempt_id === $command->payment_attempt_id, 409);
            $command = $target;
        }

        return $command->action;
    }

    private function event(PaymentControl $command, Order $order, ?User $actor, string $state, array $before): void
    {
        (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => $actor?->id,
            'operation_id' => $state === 'requested' ? $command->operation_id : (string) Uuid::uuid5('8095391d-9ee6-46e2-b5a8-17a1e5d9fa68', $command->public_id.':'.$state),
            'request_hash' => $command->request_hash, 'action' => 'payment_'.$command->action.'_'.$state, 'before' => $before,
            'after' => ['control' => $command->public_id, 'target' => $command->target_id, 'revision' => $order->revision, 'status' => $order->status,
                'payment_status' => $order->payment_status, 'command_state' => $command->state]])->save();
    }
}
