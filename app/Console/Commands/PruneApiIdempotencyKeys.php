<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyKey;
use Illuminate\Console\Command;

final class PruneApiIdempotencyKeys extends Command
{
    /** @var string */
    protected $signature = 'api:idempotency:prune';

    /** @var string */
    protected $description = 'Delete expired API idempotency records';

    public function handle(): int
    {
        $deleted = ApiIdempotencyKey::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $this->components->info("Pruned {$deleted} expired API idempotency record(s).");

        return self::SUCCESS;
    }
}
