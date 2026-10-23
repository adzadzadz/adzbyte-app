<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\OrderReviewActions;
use App\Actions\Orders\OrderReviewRead;
use App\Http\Requests\Api\V1\OrderReviewRequest;
use App\Http\Resources\Api\V1\OrderReviewResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OrderReviewController
{
    public function show(OrderReviewRequest $request, OrderReviewRead $read, string $order)
    {
        $review = $read->read($request->user(), $order, false, (int) $request->validated('query.page', 1));
        $resource = new OrderReviewResource($review);
        if ($request->is('api/*')) {
            return $resource->response()->header('Cache-Control', 'no-store, private');
        }
        $data = $resource->resolve($request);
        $old = $request->session()->get('commerce.review_input_owner') === [$request->user()->id, $order] ? $request->old() : [];

        return response()->view('orders.show', ['order' => $data['order'], 'review' => $data, 'corrections' => $old ?? []])->header('Cache-Control', 'no-store, private');
    }

    public function command(OrderReviewRequest $request, OrderReviewActions $actions, OrderReviewRead $read, string $order)
    {
        try {
            $actions->customer($request->user(), $order, $request->route('command'), $request->validated('body'), $request->validated('operation_id'));
        } catch (HttpException $exception) {
            if ($request->is('api/*') || $exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return redirect()->route('orders.show', ['order' => $order])->withInput($request->except('_token', 'accept_terms'))
                ->with('status', 'This request changed. Review the latest details and accept again if needed.')->header('Cache-Control', 'no-store, private');
        }
        if ($request->is('api/*')) {
            return (new OrderReviewResource($read->read($request->user(), $order)))->response()->header('Cache-Control', 'no-store, private');
        }
        $request->session()->forget('commerce.review_input_owner');

        return redirect()->route('orders.show', ['order' => $order])->with('status', 'Your update was saved.')->header('Cache-Control', 'no-store, private');
    }
}
