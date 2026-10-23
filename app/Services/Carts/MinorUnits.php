<?php

declare(strict_types=1);

namespace App\Services\Carts;

/** Nonnegative decimal addition without native integer or floating-point totals. */
final class MinorUnits
{
    public static function formatPhp(string $minor): string
    {
        $value = str_pad(self::add($minor, '0'), 3, '0', STR_PAD_LEFT);

        return '₱'.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', substr($value, 0, -2)).'.'.substr($value, -2);
    }

    public static function add(string $left, string $right): string
    {
        if (! preg_match('/^(0|[1-9][0-9]*)$/D', $left) || ! preg_match('/^(0|[1-9][0-9]*)$/D', $right)) {
            throw new \InvalidArgumentException('Expected canonical nonnegative minor units.');
        }
        $result = '';
        $carry = 0;
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
            $digit = ($i >= 0 ? (int) $left[$i] : 0) + ($j >= 0 ? (int) $right[$j] : 0) + $carry;
            $result = ($digit % 10).$result;
            $carry = intdiv($digit, 10);
        }

        return $result;
    }
}
