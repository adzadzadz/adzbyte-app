<?php

namespace Tests\Unit;

use App\Services\Orders\CanonicalValue;
use App\Services\Orders\CommerceRules;
use App\Services\Orders\OrderLifecycle;
use App\Services\Orders\OrderTotals;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderRulesTest extends TestCase
{
    public function test_exact_totals_round_half_up_and_never_overflow_machine_integers(): void
    {
        foreach ([['12500', 'unconfigured', 0, '0', '12500'], ['12500', 'inclusive', 1200, '1339', '12500'],
            ['299', 'exclusive', 1200, '36', '335'], ['1', 'exclusive', 5000, '1', '2'], ['1', 'inclusive', 10000, '1', '1'],
            ['0', 'inclusive', 1200, '0', '0'], ['18446744073709551614', 'exclusive', 1200, '2213609288845146194', '20660353362554697808'],
        ] as [$subtotal, $mode, $rate, $tax, $total]) {
            $this->assertSame(['currency' => 'PHP', 'subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $total], OrderTotals::calculate($subtotal, $mode, $rate));
        }
        foreach (['-1', '1.2', '01', '1e3', str_repeat('9', 31)] as $invalid) {
            try {
                OrderTotals::calculate($invalid, 'exclusive', 1200);
                $this->fail('Invalid money accepted');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_canonical_input_hash_ignores_object_key_order_but_preserves_list_order_and_types(): void
    {
        $this->assertSame(CanonicalValue::hash(['b' => ['y' => 2, 'x' => 1], 'a' => true]), CanonicalValue::hash(['a' => true, 'b' => ['x' => 1, 'y' => 2]]));
        $this->assertNotSame(CanonicalValue::hash(['a' => [1, 2]]), CanonicalValue::hash(['a' => [2, 1]]));
        $this->assertNotSame(CanonicalValue::hash(['a' => true]), CanonicalValue::hash(['a' => '1']));
        $this->assertSame('unconfigured', CommerceRules::validate(CommerceRules::defaults())['tax_mode']);
    }

    public function test_approval_correction_rejection_and_expiry_never_enable_payment_or_fulfillment(): void
    {
        $state = OrderLifecycle::INITIAL;
        foreach (['clarification', 'pending', 'approved', 'expired', 'pending', 'rejected'] as $approval) {
            $next = [...$state, 'approval_status' => $approval];
            OrderLifecycle::validate($next, $state);
            $state = $next;
            $this->assertSame('unpaid', $state['payment_status']);
            $this->assertSame('not_started', $state['fulfillment_status']);
        }
        foreach ([['approval_status' => 'approved'], ['payment_status' => 'paid'], ['payment_status' => 'pending'], ['fulfillment_status' => 'in_progress'], ['status' => 'completed']] as $invalid) {
            try {
                OrderLifecycle::validate([...$state, ...$invalid], $state);
                $this->fail('Invalid transition accepted');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $cancelled = [...OrderLifecycle::INITIAL, 'status' => 'cancelled', 'fulfillment_status' => 'cancelled'];
        OrderLifecycle::validate($cancelled, OrderLifecycle::INITIAL);
        $this->assertSame('unpaid', $cancelled['payment_status']);
        try {
            OrderLifecycle::validate(OrderLifecycle::INITIAL, $cancelled);
            $this->fail('Cancelled order reopened');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
