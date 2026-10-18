<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StorefrontCartController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::prefix('v1')->as('api.v1.')->group(function (): void {
    Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->middleware('throttle:api-catalog')->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->whereUlid('product')->name('products.show');
    });

    Route::prefix('storefront')->as('storefront.')
        ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
        ->middleware(['auth:sanctum', 'throttle:api-storefront'])->group(function (): void {
            Route::post('carts', [StorefrontCartController::class, 'store'])->middleware('storefront.token:cart:create')->name('carts.store');
            Route::get('carts/resolve', [StorefrontCartController::class, 'resolve'])->middleware('storefront.token:cart:read')->name('carts.resolve');
            Route::get('carts/{cart}', [StorefrontCartController::class, 'show'])->whereUlid('cart')->middleware('storefront.token:cart:read')->name('carts.show');
            Route::post('carts/{cart}/items', [StorefrontCartController::class, 'add'])->whereUlid('cart')->middleware('storefront.token:cart:write')->name('carts.add');
            Route::delete('carts/{cart}/items/{product}', [StorefrontCartController::class, 'remove'])->whereUlid('cart')->whereUlid('product')->middleware('storefront.token:cart:write')->name('carts.remove');
            Route::post('carts/{cart}/confirm', [StorefrontCartController::class, 'confirm'])->whereUlid('cart')->middleware('storefront.token:cart:write')->name('carts.confirm');
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
