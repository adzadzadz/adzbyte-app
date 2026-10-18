<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\OrderActions;
use App\Actions\Payments\PaymentAttemptActions;
use App\Http\Requests\Api\V1\PaymentAttemptRequest;
use App\Http\Resources\Api\V1\PaymentAttemptResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentAttemptController
{
    public function show(PaymentAttemptRequest $request, PaymentAttemptActions $actions, OrderActions $orders, string $order)
    {
        $attempt = $actions->read($request->user(), $order);
        if ($request->is('api/*')) {
            return $attempt === null ? response()->json(['data' => null, 'meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]])->header('Cache-Control', 'no-store, private')
                : (new PaymentAttemptResource($attempt))->response()->header('Cache-Control', 'no-store, private');
        }

        return response()->view('orders.payment', ['order' => $orders->read($request->user(), $order),
            'payment' => $attempt === null ? null : (new PaymentAttemptResource($attempt))->resolve($request)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(PaymentAttemptRequest $request, PaymentAttemptActions $actions, string $order)
    {
        try {
            $attempt = $actions->start($request->user(), $order, $request->validated('body.expected_revision'), $request->validated('operation_id'));
        } catch (HttpException $exception) {
            if ($request->is('api/*') || ! in_array($exception->getStatusCode(), [409, 503], true)) {
                throw $exception;
            }

            return redirect()->route('orders.payment.show', ['order' => $order])->with('status', 'Review the latest request and payment status. Contact Admin if you need help.')->header('Cache-Control', 'no-store, private');
        }
        if ($request->is('api/*')) {
            return (new PaymentAttemptResource($attempt))->response()->setStatusCode(201)->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('orders.payment.show', ['order' => $order])->header('Cache-Control', 'no-store, private');
    }
}
