<?php

namespace App\Actions\Orders;

use App\Enums\OrderCapability;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderMessage;
use App\Models\OrderProposal;
use App\Models\OrderRevision;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\MinorUnits;
use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderAggregate;
use App\Services\Orders\OrderLifecycle;
use App\Services\Orders\OrderTotals;
use App\Support\Database\RetryingTransaction;
use App\Support\Records\RecordVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OrderReviewActions
{
    public const STAFF = ['approve' => OrderCapability::Approve, 'reject' => OrderCapability::Reject,
        'clarify' => OrderCapability::Clarify, 'message' => OrderCapability::Message,
        'note' => OrderCapability::Note, 'propose' => OrderCapability::Propose];

    /** Caller holds the shared policy/order aggregate locks. */
    public function proposalCurrent(Order $order, OrderProposal $proposal): bool
    {
        try {
            return hash_equals($proposal->content_hash, CanonicalValue::hash($this->quote($order)));
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return false;
        }
    }

    public function staff(User $actor, string $reference, string $action, array $input, string $operationId): Order
    {
        abort_unless(isset(self::STAFF[$action]), 422);

        return $this->command($actor, $reference, $action, $input, $operationId, true);
    }

    public function customer(User $actor, string $reference, string $action, array $input, string $operationId): Order
    {
        abort_unless(in_array($action, ['reply', 'resubmit', 'accept_proposal'], true), 422);

        return $this->command($actor, $reference, $action, $input, $operationId, false);
    }

    public function conversation(User $actor, string $reference): Collection
    {
        return RetryingTransaction::run(function () use ($actor, $reference): Collection {
            $fresh = $this->actor($actor, null);
            $order = Order::where('user_id', $fresh->id)->where('public_id', $reference)->lockForUpdate()->firstOrFail();

            return OrderMessage::where('order_id', $order->id)->where('audience', 'customer')->orderBy('id')->lockForUpdate()->get();
        });
    }

    public function staffConversation(User $actor, string $reference): Collection
    {
        return RetryingTransaction::run(function () use ($actor, $reference): Collection {
            $this->actor($actor, OrderCapability::View);
            $order = Order::where('public_id', $reference)->lockForUpdate()->firstOrFail();

            return OrderMessage::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        });
    }

    /** Scheduled/system operation; no generic status setter or payment transition. */
    public function expire(string $reference): bool
    {
        return RetryingTransaction::run(function () use ($reference): bool {
            $order = Order::where('public_id', $reference)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'open' || $order->payment_status !== 'unpaid' || $order->approval_status !== 'approved'
                || $order->approval_expires_at->isFuture()) {
                return false;
            }
            $before = $this->state($order);
            // Stable per captured approval generation, shared by every worker.
            $operation = (string) Uuid::uuid5('8e8a1ed4-82e4-4b2e-9a23-daa90914c80a', $order->public_id.':'.$order->revision.':'.$order->approval_expires_at->toISOString());
            $order->forceFill(['approval_status' => 'expired', 'approval_expires_at' => null, 'revision' => $order->revision + 1])->save();
            $this->event($order, null, $operation, CanonicalValue::hash($before), 'approval_expired', $before);

            return true;
        });
    }

    private function command(User $actor, string $reference, string $action, array $input, string $operationId, bool $staff): Order
    {
        Validator::make(['operation_id' => $operationId], ['operation_id' => ['required', 'uuid']])->validate();
        $operationId = strtolower($operationId);
        $this->validateInput($action, $input);

        return RetryingTransaction::run(function () use ($actor, $reference, $action, $input, $operationId, $staff): Order {
            $fresh = $this->actor($actor, $staff ? self::STAFF[$action] : null);
            // Global policy lock precedes the order, matching checkout's lock order.
            app(CommercePolicyActions::class)->current();
            $query = Order::where('public_id', $reference);
            if (! $staff) {
                $query->where('user_id', $fresh->id);
            }
            $order = $query->lockForUpdate()->firstOrFail();
            RecordVisibility::assertVisible($order);
            $hash = CanonicalValue::hash(['order' => $reference, 'action' => $action, 'input' => $input]);
            $receipt = OrderEvent::where('actor_id', $fresh->id)->where('operation_id', $operationId)->lockForUpdate()->first();
            if ($receipt !== null) {
                abort_unless($receipt->order_id === $order->id && hash_equals($receipt->request_hash, $hash), 409);

                return OrderAggregate::hydrate($order);
            }
            abort_unless($order->revision === $input['expected_revision'] && $order->status === 'open' && $order->payment_status === 'unpaid', 409);
            OrderAggregate::hydrate($order);
            $before = $this->state($order);
            $proposal = null;
            switch ($action) {
                case 'approve':
                    abort_unless($order->approval_status === 'pending' && $order->pending_proposal_id === null, 409);
                    $order->approval_status = 'approved';
                    $order->approval_expires_at = now()->addDays($order->currentRevision->policy_snapshot['approval_days']);
                    break;
                case 'reject':
                    abort_unless(in_array($order->approval_status, ['pending', 'clarification'], true), 409);
                    $order->approval_status = 'rejected';
                    $order->approval_expires_at = null;
                    $order->pending_proposal_id = null;
                    break;
                case 'clarify':
                case 'propose':
                    abort_unless(in_array($order->approval_status, ['pending', 'clarification', 'approved', 'expired'], true), 409);
                    if ($action === 'propose') {
                        $quote = $this->quote($order);
                        $proposal = (new OrderProposal)->forceFill(['order_id' => $order->id, 'policy_version_id' => $quote['policy_version_id'],
                            'base_revision' => $order->revision, 'content' => $quote]);
                        $proposal->save();
                        $order->pending_proposal_id = $proposal->id;
                    }
                    $order->approval_status = 'clarification';
                    $order->approval_expires_at = null;
                    break;
                case 'resubmit':
                case 'accept_proposal':
                    abort_unless(in_array($order->approval_status, ['clarification', 'expired'], true), 409);
                    if ($action === 'accept_proposal') {
                        $proposal = OrderProposal::where('order_id', $order->id)->whereKey($order->pending_proposal_id)->where('public_id', $input['proposal'])->lockForUpdate()->first();
                        abort_unless($proposal !== null && hash_equals($proposal->content_hash, CanonicalValue::hash($this->quote($order))), 409);
                        $quote = $proposal->content;
                    } else {
                        abort_unless($order->pending_proposal_id === null, 409);
                        $quote = $this->acceptedQuote($order);
                    }
                    CommerceRules::submission(['brief' => $input['brief'], 'billing' => $input['billing'], 'accept_terms' => $input['accept_terms'], 'fingerprint' => str_repeat('0', 64)], $quote['policy_snapshot']);
                    $this->accept($order, $quote, $input);
                    $order->approval_status = 'pending';
                    $order->approval_expires_at = null;
                    $order->pending_proposal_id = null;
                    break;
                case 'reply':
                    abort_unless($order->approval_status !== 'rejected', 409);
                    break;
                case 'message':
                case 'note':
                    break;
            }
            $order->revision++;
            $order->save();
            $event = $this->event($order, $fresh, $operationId, $hash, $action, $before, $proposal?->public_id);
            if (isset($input['message'])) {
                (new OrderMessage)->forceFill(['order_id' => $order->id, 'event_id' => $event->id, 'actor_id' => $fresh->id,
                    'audience' => $action === 'note' ? 'internal' : 'customer', 'sender' => $staff ? 'admin' : 'customer', 'body' => $input['message']])->save();
            }

            return OrderAggregate::hydrate($order);
        });
    }

    private function actor(User $actor, ?OrderCapability $capability): User
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        RecordVisibility::assertVisible($fresh);
        abort_unless($fresh->hasVerifiedEmail(), 403);
        if ($capability === null) {
            abort_unless($fresh->hasRole(UserRole::Customer->value), 403);
        } else {
            abort_unless($fresh->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $fresh->checkPermissionTo($capability->value), 403);
        }

        return $fresh;
    }

    private function validateInput(string $action, array $input): void
    {
        $keys = ['expected_revision'];
        $rules = ['input.expected_revision' => ['required', 'integer:strict', 'min:1']];
        if (in_array($action, ['reject', 'clarify', 'propose', 'message', 'note', 'reply'], true)) {
            $keys[] = 'message';
            $rules['input.message'] = ['required', 'string', 'max:10000', 'regex:/\S/u'];
        }
        if (in_array($action, ['resubmit', 'accept_proposal'], true)) {
            $keys = [...$keys, 'brief', 'billing', 'accept_terms'];
            $rules += ['input.brief' => ['present', 'array'], 'input.billing' => ['present', 'array'], 'input.accept_terms' => ['required']];
            abort_unless(($input['accept_terms'] ?? null) === true, 422);
            if ($action === 'accept_proposal') {
                $keys[] = 'proposal';
                $rules['input.proposal'] = ['required', 'ulid'];
            }
        }
        Validator::make(['input' => $input], ['input' => ['required', 'array:'.implode(',', $keys)], ...$rules])->validate();
    }

    private function acceptedQuote(Order $order): array
    {
        $accepted = $order->currentRevision;

        return [...$accepted->only(['policy_version_id', 'policy_snapshot', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor']),
            'lines' => $accepted->lines->map(fn ($line) => $line->only(['product_id', 'price_id', 'terms_id', 'snapshot']))->all()];
    }

    private function quote(Order $order): array
    {
        $policy = app(CommercePolicyActions::class)->current();
        $ids = $order->currentRevision->lines->pluck('product_id')->sort()->values()->all();
        $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        abort_unless($products->count() === count($ids), 409);
        $subtotal = '0';
        $lines = [];
        $categories = [];
        foreach ($products as $product) {
            $price = $product->prices()->orderByDesc('version')->lockForUpdate()->first();
            $terms = $product->termsVersions()->orderByDesc('version')->lockForUpdate()->first();
            abort_unless($product->status === ProductStatus::Published && $product->is_available && $price !== null && $price->currency === 'PHP'
                && $price->amount_minor > 0 && $terms !== null && $terms->content !== [], 409);
            if ($product->category_id !== null) {
                $category = ProductCategory::whereKey($product->category_id)->lockForUpdate()->firstOrFail();
                abort_if($category->limit_one_per_cart && isset($categories[$category->id]), 409);
                $categories[$category->id] = true;
            }
            $subtotal = MinorUnits::add($subtotal, (string) $price->amount_minor);
            $lines[] = ['product_id' => $product->id, 'price_id' => $price->id, 'terms_id' => $terms->id,
                'snapshot' => ['public_id' => $product->public_id, 'name' => $product->name, 'quantity' => 1, 'amount_minor' => (string) $price->amount_minor,
                    'currency' => 'PHP', 'price_version' => $price->version, 'terms_version' => $terms->version, 'terms' => $terms->content]];
        }

        return ['policy_version_id' => $policy->id, 'policy_snapshot' => $policy->content,
            ...OrderTotals::calculate($subtotal, $policy->content['tax_mode'], $policy->content['tax_basis_points']), 'lines' => $lines];
    }

    private function accept(Order $order, array $quote, array $input): void
    {
        $revision = (new OrderRevision)->forceFill(['order_id' => $order->id, 'version' => $order->currentRevision->version + 1,
            ...array_intersect_key($quote, array_flip(['policy_version_id', 'policy_snapshot', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor'])),
            'brief' => $input['brief'], 'billing' => $input['billing'], 'accepted_at' => now()]);
        $revision->save();
        foreach ($quote['lines'] as $line) {
            (new OrderLine)->forceFill(['order_revision_id' => $revision->id, ...$line])->save();
        }
    }

    private function state(Order $order): array
    {
        return $order->only([...array_keys(OrderLifecycle::INITIAL), 'revision', 'approval_expires_at', 'pending_proposal_id']);
    }

    private function event(Order $order, ?User $actor, string $operation, string $hash, string $action, array $before, ?string $proposal = null): OrderEvent
    {
        $event = (new OrderEvent)->forceFill(['order_id' => $order->id, 'actor_id' => $actor?->id, 'operation_id' => $operation,
            'request_hash' => $hash, 'action' => $action, 'before' => $before, 'after' => [...$this->state($order), 'proposal' => $proposal]]);
        $event->save();

        return $event;
    }
}
