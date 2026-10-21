<?php

namespace App\Console\Commands;

use App\Services\PackageCheckout\PackagePurchaseDeliveries;
use Illuminate\Console\Command;
use Throwable;

class DeliverPackagePurchases extends Command
{
    protected $signature = 'package-purchases:deliver {--limit=25}';

    protected $description = 'Deliver due checkout captures and paid purchase notifications through the authenticated WordPress bridge';

    public function handle(PackagePurchaseDeliveries $deliveries): int
    {
        $limit = (string) $this->option('limit');
        if (! preg_match('/^[1-9][0-9]{0,2}$/D', $limit) || (int) $limit > 100) {
            $this->error('Limit must be between 1 and 100.');

            return self::INVALID;
        }
        try {
            $count = $deliveries->deliver((int) $limit);
        } catch (Throwable) {
            $this->error('Purchase deliveries are unavailable. Check bridge configuration and delivery records.');

            return self::FAILURE;
        }
        $this->info('Processed '.$count.' purchase deliveries.');

        return self::SUCCESS;
    }
}
