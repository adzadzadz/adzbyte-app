<?php

namespace App\Services\Orders;

use Closure;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OrderTransaction
{
    public static function run(Closure $callback): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (Throwable $exception) {
                if ($attempt >= 4 || DB::transactionLevel() > 0 || ! app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                    throw $exception;
                }
                usleep(10000 * (2 ** $attempt));
            }
        }
    }
}
