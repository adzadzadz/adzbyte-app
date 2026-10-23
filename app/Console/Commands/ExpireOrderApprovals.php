<?php

namespace App\Console\Commands;

use App\Actions\Orders\OrderReviewActions;
use App\Models\Order;
use Illuminate\Console\Command;

final class ExpireOrderApprovals extends Command
{
    protected $signature = 'commerce:expire-order-approvals';

    protected $description = 'Expire elapsed unpaid approvals while preserving accepted requests and carts';

    public function handle(OrderReviewActions $actions): int
    {
        $count = 0;
        Order::where('status', 'open')->where('payment_status', 'unpaid')->where('approval_status', 'approved')
            ->where('approval_expires_at', '<=', now())->select(['id', 'public_id'])->chunkById(100, function ($orders) use ($actions, &$count): void {
                foreach ($orders as $order) {
                    $count += (int) $actions->expire($order->public_id);
                }
            });
        $this->components->info("Expired {$count} unpaid approval(s). Requests and carts were retained.");

        return self::SUCCESS;
    }
}
