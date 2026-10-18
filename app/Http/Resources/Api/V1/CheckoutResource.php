<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Orders\OrderTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class CheckoutResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $cart = (new CartResource($review['cart']))->resolve($request);
        $policy = $review['policy'];

        // Internal cart totals include hidden draft offers. Public review totals
        // must use only the allowlisted projection, even when submission is blocked.
        return [
            'fingerprint' => $review['fingerprint'], 'cart' => $cart,
            'totals' => OrderTotals::calculate($cart['subtotal_minor'], $policy->content['tax_mode'], $policy->content['tax_basis_points']),
            'existing_order' => $review['existing_order'],
            'policy' => ['public_id' => $policy->public_id, 'version' => $policy->version, ...Arr::only($policy->content, [
                'tax_mode', 'tax_basis_points', 'document_kind', 'required_brief', 'required_billing', 'approval_days', 'terms', 'privacy', 'retention',
            ])],
        ];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
