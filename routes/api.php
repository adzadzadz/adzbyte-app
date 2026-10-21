<?php

use App\Http\Controllers\Api\V1\PackageCheckoutController;
use App\Http\Controllers\Api\V1\PackageQrphWebhookController;
use App\Http\Middleware\PackageCheckoutAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(PackageCheckoutAccess::class)->group(function (): void {
    Route::get('package-offers/{slug}', [PackageCheckoutController::class, 'offer'])->where('slug', '[a-z]{1,32}')->middleware('throttle:package-offers');
    Route::post('package-checkouts', [PackageCheckoutController::class, 'store'])->middleware('throttle:package-create');
    Route::get('package-checkouts/resolve/{operation}', [PackageCheckoutController::class, 'resolve'])->whereUuid('operation')->middleware('throttle:package-status');
    Route::get('package-checkouts/{reference}', [PackageCheckoutController::class, 'show'])->whereUlid('reference')->middleware('throttle:package-status');
});

Route::post('v1/webhooks/package-qrph', PackageQrphWebhookController::class)
    ->middleware([PackageCheckoutAccess::class.':webhook', 'throttle:package-webhook']);
