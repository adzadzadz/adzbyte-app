<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('package-purchases:deliver --limit=25')->everyMinute()->withoutOverlapping(5);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('api:idempotency:prune')->daily();
Schedule::command('commerce:prune-cart-handoffs')->daily();
Schedule::command('commerce:expire-order-approvals')->everyMinute()->withoutOverlapping(5);
Schedule::command('commerce:recover-order-notifications')->everyFiveMinutes()->withoutOverlapping(5);

Schedule::command('queue:monitor', [
    config('queue.monitor.connection').':'.config('queue.monitor.queue'),
    '--max='.config('queue.monitor.max_jobs'),
])->everyMinute()->withoutOverlapping(5);

Schedule::command('operations:queue-health')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
