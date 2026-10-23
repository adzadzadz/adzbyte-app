<?php

namespace App\Actions\Orders;

use App\Enums\OrderCapability;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderProposal;
use App\Models\User;
use App\Services\Orders\OrderAggregate;
use App\Support\Database\RetryingTransaction;
use App\Support\Records\RecordVisibility;
use Illuminate\Pagination\LengthAwarePaginator;

final class OrderReviewRead
{
    public function read(User $actor, string $reference, bool $staff = false, int $page = 1): array
    {
        abort_unless($page >= 1 && $page <= 10000, 422);

        return RetryingTransaction::run(function () use ($actor, $reference, $staff, $page): array {
            $fresh = $this->actor($actor, $staff ? OrderCapability::View : null);
            app(CommercePolicyActions::class)->current();
            $query = Order::where('public_id', $reference);
            if (! $staff) {
                RecordVisibility::scope($query);
                $query->where('user_id', $fresh->id);
            }
            $order = OrderAggregate::hydrate($query->lockForUpdate()->firstOrFail());
            $messages = RecordVisibility::scope(OrderMessage::where('order_id', $order->id));
            if (! $staff) {
                $messages->where('audience', 'customer');
            }
            $total = (clone $messages)->lockForUpdate()->count();
            $messages = new LengthAwarePaginator($messages->orderByDesc('id')->offset(($page - 1) * 20)->limit(20)->lockForUpdate()->get(), $total, 20, $page);
            $proposal = $order->pending_proposal_id === null ? null : OrderProposal::where('order_id', $order->id)->whereKey($order->pending_proposal_id)->lockForUpdate()->firstOrFail();

            return ['order' => $order, 'messages' => $messages, 'proposal' => $proposal,
                'proposal_current' => $proposal === null ? false : app(OrderReviewActions::class)->proposalCurrent($order, $proposal)];
        });
    }

    public function listing(User $actor, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        abort_unless($page >= 1 && $page <= 10000, 422);

        return RetryingTransaction::run(function () use ($actor, $page) {
            $this->actor($actor, OrderCapability::ViewAny);

            return RecordVisibility::scope(Order::with('currentRevision'))->orderByDesc('id')->paginate(20, ['*'], 'page', $page);
        });
    }

    private function actor(User $actor, ?OrderCapability $capability): User
    {
        $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        RecordVisibility::assertVisible($fresh);
        abort_unless($fresh->hasVerifiedEmail(), 403);
        abort_unless($capability === null ? $fresh->hasRole(UserRole::Customer->value)
            : $fresh->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $fresh->checkPermissionTo($capability->value), 403);

        return $fresh;
    }
}
