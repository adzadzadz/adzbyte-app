<?php

namespace App\Http\Controllers\Cart;

use App\Actions\Carts\CartActions;
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
        try {
            $snapshot = $actions->read($context->principal($request->session()), $context->reference($request->session()));
            $cart = (new CartResource($snapshot))->resolve($request);
        } catch (ModelNotFoundException) {
            // Missing/rotated guest authority never selects another cart.
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
        }

        return response()->view('cart.show', ['cart' => $cart, 'shop' => CommerceOrigins::storefront().'/shop'])
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
