<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\Api\V1\CheckoutResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\Carts\CartSession;
use App\Services\Orders\CheckoutDraft;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CheckoutController
{
    public function show(CheckoutRequest $request, OrderActions $actions, CartSession $cart): JsonResponse|Response|RedirectResponse
    {
        if ($cart->hasGuest($request->session())) {
            abort_if($request->is('api/*'), 409);

            return redirect()->route('cart.show')->with('status', 'Save your new selections before reviewing your request.')->header('Cache-Control', 'no-store, private');
        }
        try {
            $review = $actions->review($request->user());
        } catch (ModelNotFoundException $exception) {
            if ($request->is('api/*')) {
                throw $exception;
            }

            return redirect()->route('cart.show')->header('Cache-Control', 'no-store, private');
        }
        if ($request->is('api/*')) {
            return (new CheckoutResource($review))->response()->header('Cache-Control', 'no-store, private');
        }
        if ($review['existing_order'] !== null) {
            app(CheckoutDraft::class)->forget($request->session());

            return redirect()->route('orders.show', ['order' => $review['existing_order']])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('orders.checkout', ['review' => (new CheckoutResource($review))->resolve($request),
            'draft' => app(CheckoutDraft::class)->restore($request->session(), $request->user(), $review['policy']->content['retention']['draft_days'], $request->old() ?? [])])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(CheckoutRequest $request, OrderActions $actions, CartSession $cart): JsonResponse|RedirectResponse
    {
        if (! $request->is('api/*')) {
            $policy = app(CommercePolicyActions::class)->current();
            app(CheckoutDraft::class)->remember($request->session(), $request->user(), [...$request->validated('body'), 'operation_id' => $request->validated('operation_id')], $policy->content['retention']['draft_days']);
        }
        if ($cart->hasGuest($request->session())) {
            abort_if($request->is('api/*'), 409);

            return redirect()->route('cart.show')->with('status', 'Save your new selections before submitting your request.')->header('Cache-Control', 'no-store, private');
        }
        try {
            $order = $actions->submit($request->user(), $request->validated('body'), $request->validated('operation_id'));
        } catch (HttpException $exception) {
            if ($request->is('api/*') || $exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return redirect()->route('checkout.show')->withInput($request->except('_token', 'accept_terms'))
                ->with('status', 'Your review changed or this selection already has a request. Review it before submitting again.')
                ->header('Cache-Control', 'no-store, private');
        }
        if ($request->is('api/*')) {
            return (new OrderResource($order))->response()->setStatusCode(201)->header('Cache-Control', 'no-store, private');
        }

        app(CheckoutDraft::class)->forget($request->session());

        return redirect()->route('orders.show', ['order' => $order->public_id])->with('status', 'Your request was sent to Admin for review.')
            ->header('Cache-Control', 'no-store, private');
    }

    public function order(CheckoutRequest $request, OrderActions $actions, string $order): JsonResponse|Response
    {
        $record = $actions->read($request->user(), $order);
        if ($request->is('api/*')) {
            return (new OrderResource($record))->response()->header('Cache-Control', 'no-store, private');
        }

        return response()->view('orders.show', ['order' => (new OrderResource($record))->resolve($request)])->header('Cache-Control', 'no-store, private');
    }

    public function index(CheckoutRequest $request, OrderActions $actions): JsonResponse|Response
    {
        $records = $actions->listing($request->user(), (int) $request->validated('query.per_page', 20), (int) $request->validated('query.page', 1));
        $data = $records->getCollection()->map(fn ($order): array => [
            'public_id' => $order->public_id, 'created_at' => $order->created_at->utc()->toIso8601String(),
            ...$order->only(['status', 'approval_status', 'payment_status', 'fulfillment_status']),
            'currency' => 'PHP', 'total_minor' => $order->currentRevision->total_minor,
        ])->all();
        $pagination = ['page' => $records->currentPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'last_page' => $records->lastPage()];
        if ($request->is('api/*')) {
            return response()->json(['data' => $data, 'meta' => ['api_version' => 'v1', 'pagination' => $pagination]])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('orders.index', ['orders' => $data, 'pagination' => $pagination])->header('Cache-Control', 'no-store, private');
    }
}
