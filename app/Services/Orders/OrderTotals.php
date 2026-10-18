<?php

namespace App\Services\Orders;

use App\Services\Carts\MinorUnits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class OrderTotals
{
    public static function calculate(string $subtotal, string $mode, int $basisPoints): array
    {
        Validator::make(['subtotal' => $subtotal, 'mode' => $mode, 'rate' => $basisPoints], [
            'subtotal' => ['required', 'regex:/^(0|[1-9][0-9]{0,28})$/D'],
            'mode' => ['required', Rule::in(['unconfigured', 'exclusive', 'inclusive'])],
            'rate' => ['required', 'integer:strict', 'between:0,10000'],
        ])->validate();
        if ($mode === 'unconfigured') {
            abort_unless($basisPoints === 0, 422);
        }
        $tax = $mode === 'unconfigured' ? '0' : self::roundedRatio($subtotal, $basisPoints, $mode === 'inclusive' ? 10000 + $basisPoints : 10000);

        return ['currency' => 'PHP', 'subtotal_minor' => $subtotal, 'tax_minor' => $tax,
            'total_minor' => $mode === 'exclusive' ? MinorUnits::add($subtotal, $tax) : $subtotal];
    }

    /** Integer decimal arithmetic; aggregate amounts can exceed PHP_INT_MAX. */
    private static function roundedRatio(string $value, int $factor, int $divisor): string
    {
        $carry = 0;
        $product = '';
        for ($i = strlen($value) - 1; $i >= 0; $i--) {
            $digit = ((int) $value[$i]) * $factor + $carry;
            $product = ($digit % 10).$product;
            $carry = intdiv($digit, 10);
        }
        $product = ($carry > 0 ? (string) $carry : '').$product;
        $quotient = '';
        $remainder = 0;
        foreach (str_split($product) as $digit) {
            $part = $remainder * 10 + (int) $digit;
            $quotient .= intdiv($part, $divisor);
            $remainder = $part % $divisor;
        }
        $quotient = ltrim($quotient, '0') ?: '0';

        return $remainder * 2 >= $divisor ? MinorUnits::add($quotient, '1') : $quotient;
    }
}
