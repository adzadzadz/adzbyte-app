<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Carts\CartHandoffActions;
use App\Http\Requests\Api\V1\ConsumeCartHandoffRequest;
use App\Services\Carts\CartSession;
use Illuminate\Http\JsonResponse;

final class ConsumeCartHandoffController
{
    public function store(ConsumeCartHandoffRequest $request, CartHandoffActions $actions, CartSession $context): JsonResponse
    {
        $grant = $actions->consume($request->validated('body.token'));
        $context->open($request->session(), $grant);

        return response()->json(['data' => ['redirect' => '/cart'], 'meta' => ['api_version' => 'v1']])->header('Cache-Control', 'no-store, private');
    }
}
