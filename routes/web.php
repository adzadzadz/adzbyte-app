<?php

use App\Http\Controllers\Api\V1\AppCartController;
use App\Http\Controllers\Api\V1\ConsumeCartHandoffController;
use App\Http\Controllers\Auth\ActivateCustomerAccountController;
use App\Http\Controllers\Cart\CartPageController;
use App\Http\Controllers\Cart\HandoffLandingController;
use App\Http\Middleware\EnsureAppCartOrigin;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:app-cart', EnsureAppCartOrigin::class])->group(function (): void {
    Route::get('/cart', [CartPageController::class, 'show'])->name('cart.show');
    Route::get('/cart/handoff', [HandoffLandingController::class, 'show'])->name('cart.handoff');
    Route::delete('/cart/items/{product}', [CartPageController::class, 'remove'])->whereUlid('product')->name('cart.remove');
    Route::post('/cart/confirm', [CartPageController::class, 'confirm'])->name('cart.confirm');
});

// App guest cart APIs deliberately use the web session and CSRF middleware.
Route::prefix('api/v1')->as('api.v1.')->middleware(['throttle:app-cart', EnsureAppCartOrigin::class])->group(function (): void {
    Route::post('cart-handoffs/consume', [ConsumeCartHandoffController::class, 'store'])->name('cart-handoffs.consume');
    Route::get('cart', [AppCartController::class, 'show'])->name('cart.show');
    Route::delete('cart/items/{product}', [AppCartController::class, 'remove'])->whereUlid('product')->name('cart.remove');
    Route::post('cart/confirm', [AppCartController::class, 'confirm'])->name('cart.confirm');
});

Route::middleware('signed')->group(function (): void {
    Route::get('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'edit'])
        ->name('account-activation.edit');
    Route::post('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'update'])
        ->middleware('throttle:account-activation')
        ->name('account-activation.update');
});
