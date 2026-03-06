<?php

use Illuminate\Support\Facades\Route;
use Modules\RiskDepertment\Http\Controllers\RiskDepertmentController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('riskdepertments', RiskDepertmentController::class)->names('riskdepertment');
});
