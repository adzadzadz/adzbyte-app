<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Payments\PaymentControlActions;
use App\Actions\Payments\PaymentOperations;
use App\Http\Requests\Api\V1\PaymentControlRequest;
use App\Http\Resources\Api\V1\PaymentControlResource;
use App\Http\Resources\Api\V1\PaymentOperationsResource;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentControlController
{
    public function cancel(PaymentControlRequest $request, PaymentControlActions $actions, string $order)
    {
        try {
            $command = $actions->start($request->user(), $order, 'cancel', $request->validated('body'), $request->validated('operation_id'));
        } catch (HttpException $exception) {
            if ($request->is('api/*') || ! in_array($exception->getStatusCode(), [409, 503], true)) {
                throw $exception;
            }

            return redirect()->route('orders.show', ['order' => $order])->with('status', 'Cancellation was not confirmed. Review the current request or contact Admin.')->header('Cache-Control', 'no-store, private');
        }
        if ($request->is('api/*')) {
            return (new PaymentControlResource($command))->response()->setStatusCode(201)->header('Cache-Control', 'no-store, private');
        }

        $cancelled = Order::where('public_id', $order)->where('user_id', $request->user()->id)->where('status', 'cancelled')->exists();

        return redirect()->route('orders.show', ['order' => $order])->with('status', $cancelled
            ? 'Your request was cancelled before payment.' : 'Cancellation is awaiting verification. Contact Admin before trying to pay again.')->header('Cache-Control', 'no-store, private');
    }

    public function management(PaymentControlRequest $request, PaymentControlActions $actions, PaymentOperations $operations, string $payment)
    {
        $operations->read($request->user(), $payment);
        $attempt = PaymentAttempt::where('public_id', $payment)->firstOrFail();
        $actions->start($request->user(), Order::findOrFail($attempt->order_id)->public_id, $request->route('control_action'),
            $request->validated('body'), $request->validated('operation_id'), $payment);

        return (new PaymentOperationsResource($operations->read($request->user(), $payment)))->response()->header('Cache-Control', 'no-store, private');
    }
}
