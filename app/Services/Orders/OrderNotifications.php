<?php

namespace App\Services\Orders;

use App\Enums\OrderCapability;
use App\Enums\UserRole;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderNotificationDelivery;
use App\Models\User;
use App\Notifications\OrderReviewUpdated;

final class OrderNotifications
{
    public static function record(OrderEvent $event): void
    {
        $customerActions = ['submitted', 'reply', 'resubmit', 'accept_proposal'];
        $adminActions = ['clarify', 'message', 'approve', 'reject', 'propose'];
        if (! in_array($event->action, [...$customerActions, ...$adminActions, 'approval_expired'], true)) {
            return;
        }
        $order = Order::findOrFail($event->order_id);
        $targets = [];
        if (in_array($event->action, [...$adminActions, 'approval_expired'], true)) {
            $owner = User::findOrFail($order->user_id);
            if ($owner->hasVerifiedEmail() && $owner->hasRole(UserRole::Customer->value)) {
                $targets[$owner->id] = 'customer';
            }
        }
        if (in_array($event->action, [...$customerActions, 'approval_expired'], true)) {
            foreach (User::role([UserRole::Administrator->value, UserRole::SuperAdmin->value])->whereNotNull('email_verified_at')->get() as $admin) {
                if (self::reviewer($admin)) {
                    $targets[$admin->id] = 'admin';
                }
            }
        }
        foreach ($targets as $userId => $audience) {
            if ($userId === $event->actor_id) {
                continue;
            }
            $delivery = (new OrderNotificationDelivery)->forceFill(['event_id' => $event->id, 'user_id' => $userId, 'audience' => $audience, 'queued_at' => now()]);
            $delivery->save();
            SendOrderNotification::dispatch($delivery->id)->afterCommit();
        }
    }

    public function deliver(int $id): void
    {
        OrderTransaction::run(function () use ($id): void {
            $delivery = OrderNotificationDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($delivery->sent_at !== null || $delivery->failed_at !== null || $delivery->suppressed_at !== null) {
                return;
            }
            $event = OrderEvent::findOrFail($delivery->event_id);
            $order = Order::findOrFail($event->order_id);
            $user = User::findOrFail($delivery->user_id);
            if (! $user->hasVerifiedEmail() || ($delivery->audience === 'admin' ? ! self::reviewer($user)
                : $user->id !== $order->user_id || ! $user->hasRole(UserRole::Customer->value))) {
                $delivery->forceFill(['suppressed_at' => now()])->save();

                return;
            }
            // Generic account link only. SMTP is at-least-once if the process
            // dies after provider acceptance but before this receipt commits.
            $user->notifyNow(new OrderReviewUpdated($order->public_id, $delivery->audience));
            $delivery->forceFill(['sent_at' => now()])->save();
        });
    }

    public function recover(bool $retryFailed = false): int
    {
        if ($retryFailed) {
            OrderNotificationDelivery::whereNull('sent_at')->whereNull('suppressed_at')->whereNotNull('failed_at')->update(['failed_at' => null, 'queued_at' => null]);
        }
        $count = 0;
        OrderNotificationDelivery::whereNull('sent_at')->whereNull('failed_at')->whereNull('suppressed_at')
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(5)))
            ->select('id')->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    OrderTransaction::run(function () use ($row, &$count): void {
                        $delivery = OrderNotificationDelivery::whereKey($row->id)->lockForUpdate()->firstOrFail();
                        if ($delivery->sent_at !== null || $delivery->failed_at !== null || $delivery->suppressed_at !== null || $delivery->queued_at?->greaterThan(now()->subMinutes(5))) {
                            return;
                        }
                        $delivery->forceFill(['queued_at' => now()])->save();
                        SendOrderNotification::dispatch($delivery->id)->afterCommit();
                        $count++;
                    });
                }
            });

        return $count;
    }

    private static function reviewer(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value])
            && $user->checkPermissionTo(OrderCapability::ViewAny->value) && $user->checkPermissionTo(OrderCapability::View->value);
    }
}
