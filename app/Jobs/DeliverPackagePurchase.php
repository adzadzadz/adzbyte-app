<?php

namespace App\Jobs;

use App\Services\PackageCheckout\PackagePurchaseDeliveries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverPackagePurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $reference, public string $phase) {}

    public function handle(PackagePurchaseDeliveries $deliveries): void
    {
        $deliveries->deliverReference($this->reference, $this->phase);
    }
}
