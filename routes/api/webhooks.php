<?php

use App\Http\Controllers\Api\V1\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::post('paymongo', PayMongoWebhookController::class)->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->name('paymongo');
