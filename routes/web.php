<?php

use App\Http\Controllers\Auth\ActivateCustomerAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('signed')->group(function (): void {
    Route::get('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'edit'])
        ->name('account-activation.edit');
    Route::post('/activate-account/{user}/{hash}', [ActivateCustomerAccountController::class, 'update'])
        ->middleware('throttle:account-activation')
        ->name('account-activation.update');
});

Route::get('/', function () {
    return view('welcome');
});
