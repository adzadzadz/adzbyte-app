<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Payments\PaymentOperations;
use App\Http\Requests\Api\V1\PaymentOperationsRequest;
use App\Http\Resources\Api\V1\PaymentOperationsResource;

final class PaymentOperationsController
{
    public function show(PaymentOperationsRequest $request, PaymentOperations $operations, string $payment)
    {
        return (new PaymentOperationsResource($operations->read($request->user(), $payment, (int) $request->validated('query.event_page', 1))))
            ->response()->header('Cache-Control', 'no-store, private');
    }

    public function reconcile(PaymentOperationsRequest $request, PaymentOperations $operations, string $payment)
    {
        $operations->reconcile($request->user(), $payment, $request->validated('body.event'),
            ['expected_revision' => $request->validated('body.expected_revision'), 'note' => $request->validated('body.note')], $request->validated('operation_id'));

        return (new PaymentOperationsResource($operations->read($request->user(), $payment)))
            ->response()->header('Cache-Control', 'no-store, private');
    }
}
