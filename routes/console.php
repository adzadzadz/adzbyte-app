<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('api:idempotency:prune')->daily();

Schedule::command('queue:monitor', [
    config('queue.monitor.connection').':'.config('queue.monitor.queue'),
    '--max='.config('queue.monitor.max_jobs'),
])->everyMinute()->withoutOverlapping(5);

Schedule::command('operations:queue-health')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
