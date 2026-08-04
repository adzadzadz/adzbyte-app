<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::get('me', CurrentUserController::class)->name('me.show');
