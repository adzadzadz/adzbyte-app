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
            'can_expire' => $data['can_expire'], 'can_recover' => $data['can_recover'], 'can_verify_control' => $data['can_verify_control'],
            'controls' => $data['controls']->map(fn ($control) => [...$control->only(['public_id', 'action', 'state', 'failure_code', 'note', 'actor_id', 'created_at']), 'target' => $control->target?->public_id])->all(),
            'pagination' => ['page' => $events->currentPage(), 'per_page' => 20, 'total' => $events->total(), 'last_page' => $events->lastPage()]];
    }

    public function with(Request $request): array
    {
        return ['meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]];
    }
}
