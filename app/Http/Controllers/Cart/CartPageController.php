<?php

namespace App\Http\Controllers\Cart;

use App\Actions\Carts\CartActions;
use App\Enums\UserRole;
use App\Http\Resources\Api\V1\CartMergeResource;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Carts\CartSession;
use App\Services\Carts\CommerceOrigins;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CartPageController
{
    public function show(Request $request, CartActions $actions, CartSession $context): Response
    {
        $cart = null;
        $owned = false;
        try {
            $actor = $request->user()?->fresh();
            if ($actor?->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value)) {
                if ($context->hasGuest($request->session())) {
                    $guest = $context->claimContext($request->session());
                    $preview = $actions->mergePreview($actor, $guest['principal'], $guest['reference']);

                    return response()->view('cart.merge', ['preview' => (new CartMergeResource($preview))->resolve($request)])
                        ->header('Cache-Control', 'no-store, private');
                }
                if (! $context->hasContext($request->session()) && ($saved = $actions->owned($actor)) !== null) {
                    $context->saveOwned($request->session(), $actor, $saved['public_id']);
                }
            }
            $snapshot = $actions->read($context->principal($request->session()), $context->reference($request->session()));
            $owned = $context->principal($request->session())->actor() !== null;
            $cart = (new CartResource($snapshot))->resolve($request);
        } catch (ModelNotFoundException|HttpException $exception) {
            if ($exception instanceof HttpException && $exception->getStatusCode() !== 404) {
                throw $exception;
            }
            if (isset($actor) && $actor->hasVerifiedEmail() && $actor->hasRole(UserRole::Customer->value) && $context->hasGuest($request->session())) {
                $context->afterAuthentication($request->session(), $actor, $actions);
                if ($context->hasContext($request->session()) && ! $context->hasGuest($request->session())) {
                    $snapshot = $actions->read($context->principal($request->session()), $context->reference($request->session()));
                    $cart = (new CartResource($snapshot))->resolve($request);
                    $owned = true;
                }
            }
        }

        return response()->view('cart.show', ['cart' => $cart, 'shop' => CommerceOrigins::storefront().'/shop', 'owned' => $owned])
            ->header('Cache-Control', 'no-store, private');
    }

    public function remove(Request $request, CartActions $actions, CartSession $context, string $product): RedirectResponse
    {
        $input = validator(['payload' => $request->except('_token', '_method')], [
            'payload' => ['required', 'array:operation_id'], 'payload.operation_id' => ['required', 'uuid'],
        ])->validate()['payload'];
        $actions->remove($context->principal($request->session()), $context->reference($request->session()), $product, $input['operation_id']);

        return redirect()->route('cart.show')->with('status', 'Service removed from your cart.')->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, CartActions $actions, CartSession $context): RedirectResponse
    {
        $input = validator(['payload' => $request->except('_token', '_method')], [
            'payload' => ['required', 'array:operation_id,versions'], 'payload.operation_id' => ['required', 'uuid'],
            'payload.versions' => ['required', 'array'], 'payload.versions.*' => ['required', 'array:price,terms'],
            'payload.versions.*.price' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'payload.versions.*.terms' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ])->validate()['payload'];
        $versions = array_map(fn (array $offer): array => ['price' => (int) $offer['price'], 'terms' => (int) $offer['terms']], $input['versions']);
        $actions->confirm($context->principal($request->session()), $context->reference($request->session()), $versions, $input['operation_id']);

        return redirect()->route('cart.show')->with('status', 'Current prices and terms confirmed.')->header('Cache-Control', 'no-store, private');
    }
}
