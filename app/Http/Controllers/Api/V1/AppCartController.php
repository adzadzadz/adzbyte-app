<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Carts\CartActions;
use App\Http\Requests\Api\V1\AppCartRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Http\JsonResponse;

final class AppCartController
{
    public function show(AppCartRequest $request, CartActions $actions): JsonResponse
    {
        return $this->respond($actions->read($request->principal(), $request->reference()));
    }

    public function remove(AppCartRequest $request, CartActions $actions, string $product): JsonResponse
    {
        return $this->respond($actions->remove($request->principal(), $request->reference(), $product, $request->operationId()));
    }

    public function confirm(AppCartRequest $request, CartActions $actions): JsonResponse
    {
        return $this->respond($actions->confirm($request->principal(), $request->reference(), $request->validated('body.versions'), $request->operationId()));
    }

    private function respond(array $snapshot): JsonResponse
    {
        return (new CartResource($snapshot))->response()->header('Cache-Control', 'no-store, private');
    }
}
