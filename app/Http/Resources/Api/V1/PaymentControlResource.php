<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;

final class PaymentControlResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $command = $this->resource;
        $order = Order::findOrFail($command->order_id);

        return ['command' => $command->only(['public_id', 'action', 'state', 'created_at']),
            'order' => $order->only(['public_id', 'revision', 'status', 'payment_status', 'fulfillment_status'])];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
