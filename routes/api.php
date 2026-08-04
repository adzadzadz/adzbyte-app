<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    Route::middleware([
        'auth:sanctum',
        'verified',
        'customer.api',
        'throttle:api-customer',
    ])->group(__DIR__.'/api/customer.php');

    Route::prefix('integration')
        ->as('integration.')
        ->middleware([
            'auth:sanctum',
            'abilities:integration',
            'throttle:api-integration',
        ])
        ->group(__DIR__.'/api/integration.php');

    Route::prefix('webhooks')
        ->as('webhooks.')
        ->middleware('throttle:api-webhooks')
        ->group(__DIR__.'/api/webhooks.php');
});
