<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PackageCheckout\QrphSignature;
use App\Services\PackageCheckout\QrphWebhooks;
use Illuminate\Http\Request;

class PackageQrphWebhookController extends Controller
{
    public function __invoke(Request $request, QrphSignature $signature, QrphWebhooks $webhooks)
    {
        $body = $request->getContent();
        $signature->verify($body, (string) $request->header('Paymongo-Signature'));
        $webhooks->receive($body);

        return response()->json(['received' => true]);
    }
}
