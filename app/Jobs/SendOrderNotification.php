<?php

namespace App\Jobs;

use App\Models\OrderNotificationDelivery;
use App\Services\Orders\OrderNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(OrderNotifications $notifications): void
    {
        $notifications->deliver($this->deliveryId);
    }

    public function failed(?\Throwable $exception): void
    {
        OrderNotificationDelivery::whereKey($this->deliveryId)->whereNull('sent_at')->whereNull('suppressed_at')->update(['failed_at' => now(), 'last_failed_at' => now(), 'failure_count' => DB::raw('failure_count + 1')]);
    }
}
