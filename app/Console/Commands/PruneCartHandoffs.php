<?php

namespace App\Console\Commands;

use App\Models\CartHandoff;
use Illuminate\Console\Command;

final class PruneCartHandoffs extends Command
{
    protected $signature = 'commerce:prune-cart-handoffs';

    protected $description = 'Remove transfer rows expired over 24 hours ago; retain carts, items and durable command receipts';

    public function handle(): int
    {
        $deleted = CartHandoff::where('expires_at', '<', now()->subDay())->delete();
        $this->components->info("Pruned {$deleted} expired cart transfer row(s). Saved carts and items were retained.");

        return self::SUCCESS;
    }
}
