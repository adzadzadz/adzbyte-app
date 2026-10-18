<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class OrderResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $accepted = $order->currentRevision;

        return [
            'public_id' => $order->public_id, 'revision' => $order->revision,
            ...$order->only(['status', 'approval_status', 'payment_status', 'fulfillment_status']),
            'created_at' => $order->created_at->utc()->toIso8601String(),
            'approval_expires_at' => $order->approval_expires_at?->utc()->toIso8601String(),
            'accepted' => [
                'version' => $accepted->version, 'accepted_at' => $accepted->accepted_at->utc()->toIso8601String(),
                ...$accepted->only(['currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'brief', 'billing']),
                'policy' => ['public_id' => $accepted->policyVersion->public_id, 'version' => $accepted->policyVersion->version, ...Arr::only($accepted->policy_snapshot, ['tax_mode', 'tax_basis_points', 'document_kind', 'required_brief', 'required_billing', 'approval_days', 'terms', 'privacy', 'retention'])],
                'lines' => $accepted->lines->map(fn ($line): array => [
                    ...Arr::only($line->snapshot, ['public_id', 'name', 'quantity', 'amount_minor', 'currency', 'price_version', 'terms_version']),
                    'terms' => (object) (new ProductTermsResource($line->snapshot['terms']))->resolve($request),
                ])->values()->all(),
            ],
        ];
    }
}
