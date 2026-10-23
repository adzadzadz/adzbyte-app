<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\PaymentEventActions;
use Illuminate\Http\Request;

final class PayMongoWebhookController
{
    public function __invoke(Request $request, PaymentEventActions $actions)
    {
        abort_unless($request->isJson() && $request->query() === [], 422);
        $actions->receive($request->getContent(), (string) $request->header('Paymongo-Signature', ''));

        return response()->json(['received' => true])->header('Cache-Control', 'no-store, private');
    }
}
