<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Carts\CartHandoffActions;
use App\Http\Requests\Api\V1\StorefrontCartRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

final class StorefrontHandoffController
{
    public function store(StorefrontCartRequest $request, CartHandoffActions $actions, string $cart): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 403);

        return response()->json([
            'data' => $actions->mint($request->principal(), $cart, $token, $request->operationId()),
            'meta' => ['api_version' => 'v1'],
        ], 201)->header('Cache-Control', 'no-store, private');
    }
}
