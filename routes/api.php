<?php

use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->middleware('throttle:api-catalog')->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->whereUlid('product')->name('products.show');
    });

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
