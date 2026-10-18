<?php

namespace App\Services\Orders;

use Illuminate\Validation\ValidationException;

final class OrderLifecycle
{
    public const INITIAL = ['status' => 'open', 'approval_status' => 'pending', 'payment_status' => 'unpaid', 'fulfillment_status' => 'not_started'];

    public const TRANSITIONS = [
        'status' => ['open' => ['cancelled', 'completed'], 'cancelled' => [], 'completed' => []],
        'approval_status' => ['pending' => ['clarification', 'approved', 'rejected'], 'clarification' => ['pending', 'rejected'], 'approved' => ['clarification', 'expired'], 'rejected' => [], 'expired' => ['pending', 'clarification']],
        // Reserved provider states; O2 must introduce verified provider transitions.
        'payment_status' => ['unpaid' => [], 'pending' => [], 'paid' => [], 'partially_refunded' => [], 'refunded' => [], 'disputed' => []],
        // O3 adds fulfillment commands after paid state is available.
        'fulfillment_status' => ['not_started' => ['cancelled'], 'in_progress' => [], 'waiting_customer' => [], 'completed' => [], 'cancelled' => []],
    ];

    public static function validate(array $after, ?array $before = null): void
    {
        foreach (self::TRANSITIONS as $axis => $states) {
            if (! isset($after[$axis], $states[$after[$axis]]) || ($before !== null && $before[$axis] !== $after[$axis] && ! in_array($after[$axis], $states[$before[$axis]] ?? [], true))) {
                self::invalid();
            }
        }
        if ($before === null && $after !== self::INITIAL) {
            self::invalid();
        }
        if ($before !== null && $before['status'] === 'cancelled' && $before !== $after) {
            self::invalid();
        }
        $paid = in_array($after['payment_status'], ['paid', 'partially_refunded'], true);
        $started = in_array($after['fulfillment_status'], ['in_progress', 'waiting_customer', 'completed'], true);
        if (($after['payment_status'] !== 'unpaid' && $after['approval_status'] !== 'approved')
            || ($started && (! $paid || $after['approval_status'] !== 'approved'))
            || ($after['status'] === 'completed' && $after['fulfillment_status'] !== 'completed')
            || ($after['fulfillment_status'] === 'completed' && $after['status'] !== 'completed')
            || ($after['status'] === 'cancelled' && (! in_array($after['payment_status'], ['unpaid', 'refunded'], true) || $after['fulfillment_status'] !== 'cancelled'))
            || ($after['status'] === 'open' && $after['fulfillment_status'] === 'cancelled')) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['state' => 'This commerce transition is not allowed.']);
    }
}
