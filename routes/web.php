<?php

use App\Http\Controllers\Api\V1\AppCartController;
use App\Http\Controllers\Api\V1\ConsumeCartHandoffController;
use App\Http\Controllers\Auth\ActivateCustomerAccountController;
use App\Http\Controllers\Auth\RegisterCustomerController;
use App\Http\Controllers\Cart\CartMergeController;
use App\Http\Controllers\Cart\CartPageController;
use App\Http\Controllers\Cart\HandoffLandingController;
use App\Http\Controllers\Orders\CheckoutController;
use App\Http\Middleware\EnsureAppCartOrigin;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:registration', EnsureAppCartOrigin::class])->group(function (): void {
    Route::get('/register', [RegisterCustomerController::class, 'show'])->name('registration.show');
    Route::post('/register', [RegisterCustomerController::class, 'store'])->name('registration.store');
    Route::get('/api/v1/registration', [RegisterCustomerController::class, 'bootstrap'])->name('api.v1.registration.bootstrap');
    Route::post('/api/v1/registration', [RegisterCustomerController::class, 'store'])->name('api.v1.registration.store');
});

Route::middleware(['throttle:app-cart', EnsureAppCartOrigin::class])->group(function (): void {
    Route::get('/cart', [CartPageController::class, 'show'])->name('cart.show');
    Route::get('/cart/handoff', [HandoffLandingController::class, 'show'])->name('cart.handoff');
    Route::delete('/cart/items/{product}', [CartPageController::class, 'remove'])->whereUlid('product')->name('cart.remove');
    Route::post('/cart/confirm', [CartPageController::class, 'confirm'])->name('cart.confirm');
    Route::post('/cart/attach', [CartMergeController::class, 'store'])->middleware(['auth:web', 'verified', 'customer.api'])->name('cart.attach');
});

// App guest cart APIs deliberately use the web session and CSRF middleware.
Route::prefix('api/v1')->as('api.v1.')->middleware(['throttle:app-cart', EnsureAppCartOrigin::class])->group(function (): void {
    Route::post('cart-handoffs/consume', [ConsumeCartHandoffController::class, 'store'])->name('cart-handoffs.consume');
    Route::get('cart', [AppCartController::class, 'show'])->name('cart.show');
    Route::delete('cart/items/{product}', [AppCartController::class, 'remove'])->whereUlid('product')->name('cart.remove');
    Route::post('cart/confirm', [AppCartController::class, 'confirm'])->name('cart.confirm');
    Route::get('cart/merge', [CartMergeController::class, 'show'])->middleware(['auth:web', 'verified', 'customer.api'])->name('cart.merge.show');
    Route::post('cart/attach', [CartMergeController::class, 'store'])->middleware(['auth:web', 'verified', 'customer.api'])->name('cart.attach');
});

Route::middleware(['auth:web', 'verified', 'customer.api', 'throttle:checkout', EnsureAppCartOrigin::class])->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [CheckoutController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CheckoutController::class, 'order'])->whereUlid('order')->name('orders.show');
    Route::get('/api/v1/checkout', [CheckoutController::class, 'show'])->name('api.v1.checkout.show');
    Route::post('/api/v1/orders', [CheckoutController::class, 'store'])->name('api.v1.orders.store');
    Route::get('/api/v1/orders', [CheckoutController::class, 'index'])->name('api.v1.orders.index');
    Route::get('/api/v1/orders/{order}', [CheckoutController::class, 'order'])->whereUlid('order')->name('api.v1.orders.show');
});

Route::middleware('signed')->group(function (): void {
    Route::get('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'edit'])
        ->name('account-activation.edit');
    Route::post('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'update'])
        ->middleware('throttle:account-activation')
        ->name('account-activation.update');
});
