<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Carts\CartActions;
use App\Http\Requests\Api\V1\StorefrontCartRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Http\JsonResponse;

final class StorefrontCartController
{
    public function store(StorefrontCartRequest $request, CartActions $actions): JsonResponse
    {
        return $this->respond($request, $actions->create($request->principal(), $request->operationId()), 201);
    }

    public function resolve(StorefrontCartRequest $request, CartActions $actions): JsonResponse
    {
        return $this->respond($request, $actions->resolveCreation($request->principal(), $request->validated('query.creation_operation_id')));
    }

    public function show(StorefrontCartRequest $request, CartActions $actions, string $cart): JsonResponse
    {
        return $this->respond($request, $actions->read($request->principal(), $cart));
    }

    public function add(StorefrontCartRequest $request, CartActions $actions, string $cart): JsonResponse
    {
        return $this->respond($request, $actions->add($request->principal(), $cart, $request->validated('body.product'), $request->operationId()));
    }

    public function remove(StorefrontCartRequest $request, CartActions $actions, string $cart, string $product): JsonResponse
    {
        return $this->respond($request, $actions->remove($request->principal(), $cart, $product, $request->operationId()));
    }

    public function confirm(StorefrontCartRequest $request, CartActions $actions, string $cart): JsonResponse
    {
        return $this->respond($request, $actions->confirm($request->principal(), $cart, $request->validated('body.versions'), $request->operationId()));
    }

    private function respond(StorefrontCartRequest $request, array $snapshot, int $status = 200): JsonResponse
    {
        return (new CartResource($snapshot))->response()->setStatusCode($status)->header('Cache-Control', 'no-store, private');
    }
}
