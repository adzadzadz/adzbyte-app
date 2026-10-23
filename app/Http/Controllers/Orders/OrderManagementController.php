<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderReviewActions;
use App\Actions\Orders\OrderReviewRead;
use App\Http\Requests\Api\V1\OrderReviewRequest;
use App\Http\Resources\Api\V1\OrderReviewResource;
use Illuminate\Support\Facades\Validator;

final class OrderManagementController
{
    public function index(OrderReviewRequest $request, OrderReviewRead $read)
    {
        $records = $read->listing($request->user(), (int) $request->validated('query.page', 1));

        return response()->json(['data' => $records->getCollection()->map(fn ($record) => $record->only(['public_id', 'revision', 'status', 'approval_status', 'payment_status', 'fulfillment_status'])),
            'meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token(), 'pagination' => ['page' => $records->currentPage(), 'per_page' => 20, 'total' => $records->total(), 'last_page' => $records->lastPage()]]])->header('Cache-Control', 'no-store, private');
    }

    public function show(OrderReviewRequest $request, OrderReviewRead $read, string $order)
    {
        return (new OrderReviewResource($read->read($request->user(), $order, true, (int) $request->validated('query.page', 1))))->response()->header('Cache-Control', 'no-store, private');
    }

    public function command(OrderReviewRequest $request, OrderReviewActions $actions, OrderReviewRead $read, string $order)
    {
        $read->read($request->user(), $order, true);
        $actions->staff($request->user(), $order, $request->route('command'), $request->validated('body'), $request->validated('operation_id'));

        return (new OrderReviewResource($read->read($request->user(), $order, true)))->response()->header('Cache-Control', 'no-store, private');
    }

    public function settings(OrderReviewRequest $request, CommercePolicyActions $actions)
    {
        abort_unless($request->query() === [], 422);
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            $policy = $actions->read($request->user());
        } else {
            $body = $request->validated('body');
            Validator::make(['body' => $body], ['body' => ['required', 'array:expected_version,content'], 'body.expected_version' => ['required', 'integer:strict', 'min:1'], 'body.content' => ['required', 'array']])->validate();
            $policy = $actions->publish($request->user(), $body['content'], $request->validated('operation_id'), $body['expected_version']);
        }

        return response()->json(['data' => ['public_id' => $policy->public_id, 'version' => $policy->version, 'content' => $policy->content],
            'meta' => ['api_version' => 'v1', 'csrf_token' => $request->session()->token()]])->header('Cache-Control', 'no-store, private');
    }
}
