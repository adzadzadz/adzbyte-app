<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

final class PaymentOperationsResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $events = $data['events'];

        return ['attempt' => $data['attempt'], 'order' => $data['order'], 'settlement' => $data['settlement'],
            'can_reconcile' => $data['can_reconcile'], 'events' => $events->items(), 'resolutions' => $data['resolutions'],
            'pagination' => ['page' => $events->currentPage(), 'per_page' => 20, 'total' => $events->total(), 'last_page' => $events->lastPage()]];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
