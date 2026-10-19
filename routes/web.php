<?php

use App\Actions\Orders\OrderReviewActions;
use App\Http\Controllers\Api\V1\AppCartController;
use App\Http\Controllers\Api\V1\ConsumeCartHandoffController;
use App\Http\Controllers\Auth\ActivateCustomerAccountController;
use App\Http\Controllers\Auth\RegisterCustomerController;
use App\Http\Controllers\Cart\CartMergeController;
use App\Http\Controllers\Cart\CartPageController;
use App\Http\Controllers\Cart\HandoffLandingController;
use App\Http\Controllers\Orders\CheckoutController;
use App\Http\Controllers\Orders\OrderManagementController;
use App\Http\Controllers\Orders\OrderReviewController;
use App\Http\Controllers\Orders\PaymentAttemptController;
use App\Http\Controllers\Orders\PaymentControlController;
use App\Http\Controllers\Orders\PaymentOperationsController;
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
    Route::get('/orders/{order}/payment', [PaymentAttemptController::class, 'show'])->whereUlid('order')->name('orders.payment.show');
    Route::post('/orders/{order}/payment', [PaymentAttemptController::class, 'store'])->whereUlid('order')->name('orders.payment.store');
    Route::get('/api/v1/orders/{order}/payment', [PaymentAttemptController::class, 'show'])->whereUlid('order')->name('api.v1.orders.payment.show');
    Route::post('/api/v1/orders/{order}/payment', [PaymentAttemptController::class, 'store'])->whereUlid('order')->name('api.v1.orders.payment.store');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [CheckoutController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderReviewController::class, 'show'])->whereUlid('order')->name('orders.show');
    Route::post('/orders/{order}/cancel', [PaymentControlController::class, 'cancel'])->whereUlid('order')->name('orders.cancel');
    Route::post('/api/v1/orders/{order}/cancel', [PaymentControlController::class, 'cancel'])->whereUlid('order')->name('api.v1.orders.cancel');
    Route::get('/api/v1/orders/{order}/review', [OrderReviewController::class, 'show'])->whereUlid('order')->name('api.v1.orders.review');
    foreach (['reply', 'resubmit', 'accept_proposal'] as $command) {
        $path = str_replace('_', '-', $command);
        Route::post('/orders/{order}/'.$path, [OrderReviewController::class, 'command'])->whereUlid('order')->defaults('command', $command)->name('orders.'.$command);
        Route::post('/api/v1/orders/{order}/'.$path, [OrderReviewController::class, 'command'])->whereUlid('order')->defaults('command', $command)->name('api.v1.orders.'.$command);
    }
    Route::get('/api/v1/checkout', [CheckoutController::class, 'show'])->name('api.v1.checkout.show');
    Route::post('/api/v1/orders', [CheckoutController::class, 'store'])->name('api.v1.orders.store');
    Route::get('/api/v1/orders', [CheckoutController::class, 'index'])->name('api.v1.orders.index');
    Route::get('/api/v1/orders/{order}', [CheckoutController::class, 'order'])->whereUlid('order')->name('api.v1.orders.show');
});

Route::prefix('api/v1/management')->as('api.v1.management.')->middleware(['auth:web', 'verified', 'throttle:checkout', EnsureAppCartOrigin::class])->group(function (): void {
    Route::get('/payments/{payment}', [PaymentOperationsController::class, 'show'])->whereUlid('payment')->name('payments.show');
    Route::post('/payments/{payment}/reconcile', [PaymentOperationsController::class, 'reconcile'])->whereUlid('payment')->name('payments.reconcile');
    foreach (['expire' => 'expire', 'recover-session' => 'recover', 'verify-control' => 'verify'] as $path => $action) {
        Route::post('/payments/{payment}/'.$path, [PaymentControlController::class, 'management'])->whereUlid('payment')->defaults('control_action', $action)->name('payments.'.$action);
    }
    Route::get('/commerce-settings', [OrderManagementController::class, 'settings'])->name('settings.show');
    Route::put('/commerce-settings', [OrderManagementController::class, 'settings'])->name('settings.update');
    Route::get('/orders', [OrderManagementController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderManagementController::class, 'show'])->whereUlid('order')->name('orders.show');
    foreach (array_keys(OrderReviewActions::STAFF) as $command) {
        Route::post('/orders/{order}/'.$command, [OrderManagementController::class, 'command'])->whereUlid('order')->defaults('command', $command)->name('orders.'.$command);
    }
});

Route::middleware('signed')->group(function (): void {
    Route::get('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'edit'])
        ->name('account-activation.edit');
    Route::post('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'update'])
        ->middleware('throttle:account-activation')
        ->name('account-activation.update');
});
