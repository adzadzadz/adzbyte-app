<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

final class PaymentAttemptResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $attempt = $this->resource;
        $order = $attempt->order;
        $resumable = $attempt->state === 'active' && $order->status === 'open' && $order->payment_status === 'pending'
            && $order->approval_status === 'approved' && $order->approval_expires_at?->isFuture() && $order->payment_attempt_id === $attempt->id;

        return ['public_id' => $attempt->public_id, 'state' => $attempt->state, 'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency, 'checkout_url' => $resumable ? $attempt->checkout_url : null,
            'created_at' => $attempt->created_at->utc()->toIso8601String()];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
