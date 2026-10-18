<?php

namespace App\Console\Commands;

use App\Models\OrderNotificationDelivery;
use App\Services\Orders\OrderNotifications;
use Illuminate\Console\Command;

final class RecoverOrderNotifications extends Command
{
    protected $signature = 'commerce:recover-order-notifications {--retry-failed : Explicitly retry exhausted notifications, retaining failure history}';

    protected $description = 'Redispatch pending review notifications; retain successful and exhausted delivery receipts';

    public function handle(OrderNotifications $notifications): int
    {
        $count = $notifications->recover((bool) $this->option('retry-failed'));
        $this->components->info("Recovered {$count} pending review notification(s).");
        $failed = OrderNotificationDelivery::whereNotNull('failed_at')->whereNull('sent_at')->count();
        if ($failed > 0) {
            $this->components->warn("{$failed} exhausted delivery(s) need operator review before --retry-failed.");
        }

        return self::SUCCESS;
    }
}
