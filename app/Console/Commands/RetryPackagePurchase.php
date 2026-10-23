<?php

namespace App\Console\Commands;

use App\Services\PackageCheckout\PackagePurchaseDeliveries;
use Illuminate\Console\Command;

class RetryPackagePurchase extends Command
{
    protected $signature = 'package-purchases:retry {reference} {phase : pending or completed}';

    protected $description = 'Queue an operator-reviewed purchase delivery again without changing its payload';

    public function handle(PackagePurchaseDeliveries $deliveries): int
    {
        if (! $deliveries->retry((string) $this->argument('reference'), (string) $this->argument('phase'))) {
            $this->error('No purchase delivery awaiting review matches that reference.');

            return self::FAILURE;
        }
        $this->info('Purchase delivery queued with its original receipt reference.');

        return self::SUCCESS;
    }
}
