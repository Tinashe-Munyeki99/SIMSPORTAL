<?php

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\AuthenticationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('authentication', AuthenticationController::class)->names('authentication');
});

Route::get('/setup-password/{token}', [AuthenticationController::class, 'showPasswordSetupForm'])
    ->name('auth.password.setup.form');

Route::post('/setup-password/{token}', [AuthenticationController::class, 'completePasswordSetup'])
    ->name('auth.password.setup.complete');
