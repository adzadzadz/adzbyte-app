<?php

namespace App\Http\Controllers\Cart;

use App\Actions\Carts\CartActions;
use App\Http\Requests\Api\V1\CartMergeRequest;
use App\Http\Resources\Api\V1\CartMergeResource;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Carts\CartSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CartMergeController
{
    public function show(CartMergeRequest $request, CartActions $actions, CartSession $context): JsonResponse
    {
        $guest = $context->claimContext($request->session());

        return (new CartMergeResource($actions->mergePreview($request->user(), $guest['principal'], $guest['reference'])))->response()->header('Cache-Control', 'no-store, private');
    }

    public function store(CartMergeRequest $request, CartActions $actions, CartSession $context): JsonResponse|RedirectResponse
    {
        $operation = $request->validated('operation_id');
        $guest = $context->claimContext($request->session(), $operation);
        try {
            $snapshot = $actions->attach($request->user(), $guest['principal'], $guest['reference'], $operation, $request->validated('body.fingerprint'), $request->validated('body.choices'));
        } catch (HttpException $exception) {
            if ($request->is('api/*') || $exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return redirect()->route('cart.show')->with('status', 'Your selections changed. Review them again before saving.')->header('Cache-Control', 'no-store, private');
        }
        $context->saveOwned($request->session(), $request->user(), $snapshot['public_id'], $guest['context'], $operation);

        return $request->is('api/*') ? (new CartResource($snapshot))->response()->header('Cache-Control', 'no-store, private')
            : redirect()->route('cart.show')->with('status', 'Your selections are saved in your account cart.')->header('Cache-Control', 'no-store, private');
    }
}
