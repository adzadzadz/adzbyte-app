<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Middleware\EnsureAppCartOrigin;
use App\Http\Middleware\PortalResponse;
use Illuminate\Support\Facades\Route;

Route::get('me', CurrentUserController::class)->name('me.show');

Route::prefix('portal')->as('portal.')->middleware([PortalResponse::class, EnsureAppCartOrigin::class])->group(function (): void {
    $controller = PortalController::class;
    Route::get('works', [$controller, 'works'])->name('works.index');
    Route::get('works/{reference}', [$controller, 'work'])->whereUlid('reference')->name('works.show');
    Route::post('works/{reference}/read', [$controller, 'read'])->whereUlid('reference')->name('works.read');
    Route::get('categories', [$controller, 'categories'])->name('categories.index');
    Route::get('cases', [$controller, 'cases'])->name('cases.index');
    Route::post('cases', [$controller, 'store'])->name('cases.store');
    Route::get('cases/{reference}', [$controller, 'supportCase'])->whereUlid('reference')->name('cases.show');
    Route::post('cases/{reference}/messages', [$controller, 'reply'])->whereUlid('reference')->name('cases.reply');
    Route::post('cases/{reference}/read', [$controller, 'read'])->whereUlid('reference')->name('cases.read');
});
