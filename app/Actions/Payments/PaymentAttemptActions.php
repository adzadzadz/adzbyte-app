<?php

namespace App\Actions\Payments;

use App\Actions\Orders\CommercePolicyActions;
use App\Enums\UserRole;
use App\Exceptions\PayMongoFailure;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentCommand;
use App\Models\User;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\OrderAggregate;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PayMongoClient;
use App\Support\Database\RetryingTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class PaymentAttemptActions
{
    public function __construct(private PayMongoClient $provider) {}

    public function start(User $actor, string $reference, mixed $expectedRevision, string $operationId): PaymentAttempt
    {
        Validator::make(['expected_revision' => $expectedRevision, 'operation_id' => $operationId], [
            'expected_revision' => ['required', 'integer:strict', 'min:1'], 'operation_id' => ['required', 'uuid'],
        ])->validate();
        $operationId = strtolower($operationId);
        $hash = CanonicalValue::hash([$reference, $expectedRevision]);
        $this->provider->ready();
        $existing = RetryingTransaction::run(function () use ($actor, $reference, $expectedRevision, $operationId, $hash): ?PaymentAttempt {
            [$fresh, $order] = $this->owned($actor, $reference);
            $receipt = $this->replay($fresh, $order, $operationId, $hash);
            if ($receipt !== null) {
                return $receipt;
            }
            $this->eligible($order, $expectedRevision);

            return null;
        });
        if ($existing !== null) {
            return $existing;
        }
        try {
            $methods = $this->provider->methods();
        } catch (PayMongoFailure) {
            abort(503, 'Payment methods could not be verified. Please try again later.');
        }
        abort_if($methods === [], 503, 'No configured payment method is available.');

        $attempt = RetryingTransaction::run(function () use ($actor, $reference, $expectedRevision, $operationId, $hash, $methods): PaymentAttempt {
            [$fresh, $order] = $this->owned($actor, $reference);
            $receipt = $this->replay($fresh, $order, $operationId, $hash);
            if ($receipt !== null) {
                return $receipt;
            }
            $this->eligible($order, $expectedRevision);
            $accepted = OrderAggregate::hydrate($order)->currentRevision;
            CheckoutSession::validateRequest($accepted);
            $lines = $accepted->lines->map(fn ($line) => ['name' => $line->snapshot['name'], 'amount' => (int) $line->snapshot['amount_minor'], 'currency' => 'PHP', 'quantity' => 1])->all();
            if ($accepted->policy_snapshot['tax_mode'] === 'exclusive' && $accepted->tax_minor !== '0') {
                $lines[] = ['name' => 'Tax on accepted service request', 'amount' => (int) $accepted->tax_minor, 'currency' => 'PHP', 'quantity' => 1];
            }
            $publicId = (string) Str::ulid();
            $return = rtrim(config('app.url'), '/').'/orders/'.$order->public_id;
            $payload = ['data' => ['attributes' => ['line_items' => $lines, 'payment_method_types' => $methods,
                'success_url' => $return, 'cancel_url' => $return, 'reference_number' => $publicId,
                'send_email_receipt' => false, 'pass_on_fees' => false,
                'metadata' => ['attempt_reference' => $publicId, 'order_reference' => $order->public_id, 'accepted_version' => (string) $accepted->version]]]];
            $attempt = (new PaymentAttempt)->forceFill(['public_id' => $publicId, 'order_id' => $order->id, 'order_revision_id' => $accepted->id,
                'actor_id' => $fresh->id, 'amount_minor' => $accepted->total_minor, 'currency' => 'PHP', 'livemode' => false,
                'state' => 'creating', 'provider_payload' => $payload]);
            $attempt->save();
            (new PaymentCommand)->forceFill(['actor_id' => $fresh->id, 'operation_id' => $operationId, 'payment_attempt_id' => $attempt->id, 'request_hash' => $hash])->save();
            $before = $order->only(['revision', 'payment_status']);
            $order->forceFill(['payment_status' => 'pending', 'payment_attempt_id' => $attempt->id, 'revision' => $order->revision + 1])->save();
            (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => $fresh->id, 'operation_id' => $operationId,
                'request_hash' => $hash, 'action' => 'payment_started', 'before' => $before,
                'after' => ['revision' => $order->revision, 'payment_status' => 'pending', 'attempt' => $publicId]])->save();

            DB::afterCommit(fn () => $this->dispatch($attempt));

            return $attempt;
        });

        return $attempt->refresh();
    }

    private function dispatch(PaymentAttempt $attempt): void
    {
        try {
            $id = $this->provider->create($attempt->provider_payload, $attempt->public_id);
            $this->update($attempt, ['provider_session_id' => $id]);
            $attempt->refresh();
            $attributes = CheckoutSession::verify($attempt, $this->provider->retrieve($id));
            // Paid evidence is handled only by the verified webhook phase.
            if (($attributes['payments'] ?? []) !== []) {
                throw new PayMongoFailure('payment_requires_reconciliation');
            }
            $this->update($attempt, ['state' => $attributes['status'] === 'active' ? 'active' : 'unknown',
                'checkout_url' => $attributes['status'] === 'active' ? $attributes['checkout_url'] : null]);
        } catch (PayMongoFailure $exception) {
            $this->update($attempt, ['state' => 'unknown', 'failure_code' => $exception->reason]);
        }

    }

    public function read(User $actor, string $reference): ?PaymentAttempt
    {
        return RetryingTransaction::run(function () use ($actor, $reference): ?PaymentAttempt {
            [, $order] = $this->owned($actor, $reference);

            return $order->payment_attempt_id === null ? null : PaymentAttempt::whereKey($order->payment_attempt_id)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
        });
    }

    private function owned(User $actor, string $reference): array
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless($fresh->hasVerifiedEmail() && $fresh->hasRole(UserRole::Customer->value), 403);
        app(CommercePolicyActions::class)->current();
        $order = Order::where('public_id', $reference)->where('user_id', $fresh->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($fresh)->authorize('create', [PaymentAttempt::class, $order]);

        return [$fresh, $order];
    }

    private function eligible(Order $order, int $expected): void
    {
        abort_unless($order->revision === $expected && $order->status === 'open' && $order->payment_status === 'unpaid'
            && $order->approval_status === 'approved' && $order->approval_expires_at?->isFuture()
            && $order->pending_proposal_id === null && $order->payment_attempt_id === null, 409, 'Review the current request and payment status.');
    }

    private function replay(User $actor, Order $order, string $operation, string $hash): ?PaymentAttempt
    {
        $receipt = PaymentCommand::where('actor_id', $actor->id)->where('operation_id', $operation)->lockForUpdate()->first();
        if ($receipt === null) {
            abort_if(OrderEvent::where('actor_id', $actor->id)->where('operation_id', $operation)->exists(), 409);

            return null;
        }
        abort_unless(hash_equals($receipt->request_hash, $hash), 409);

        return PaymentAttempt::whereKey($receipt->payment_attempt_id)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
    }

    private function update(PaymentAttempt $attempt, array $attributes): void
    {
        RetryingTransaction::run(function () use ($attempt, $attributes): void {
            Order::whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail()->forceFill($attributes)->save();
        });
    }
}
