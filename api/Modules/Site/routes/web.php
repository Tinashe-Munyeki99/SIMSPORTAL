<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\SiteController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('sites', SiteController::class)->names('site');
});
