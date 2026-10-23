<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class CheckQueueHealth extends Command
{
    /** @var string */
    protected $signature = 'operations:queue-health';

    /** @var string */
    protected $description = 'Fail when queued work is stale or failed jobs need attention';

    public function handle(): int
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            return $this->reportFailure('Queue persistence tables are missing.', [
                'jobs_table_present' => Schema::hasTable('jobs'),
                'failed_jobs_table_present' => Schema::hasTable('failed_jobs'),
            ]);
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $staleAfter = max(60, (int) config('queue.monitor.stale_after_seconds', 300));
        $staleJobs = DB::table('jobs')
            ->where('available_at', '<=', now()->timestamp)
            ->where('created_at', '<=', now()->subSeconds($staleAfter)->timestamp)
            ->count();

        if ($failedJobs > 0 || $staleJobs > 0) {
            return $this->reportFailure('Queue health requires attention.', [
                'failed_jobs' => $failedJobs,
                'stale_jobs' => $staleJobs,
                'stale_after_seconds' => $staleAfter,
            ]);
        }

        $this->components->info('Queue health is good.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, bool|int>  $context
     */
    private function reportFailure(string $message, array $context): int
    {
        Log::critical($message, $context);
        $this->components->error($message);

        return self::FAILURE;
    }
}
