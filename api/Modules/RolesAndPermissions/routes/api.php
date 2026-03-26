<?php

use Illuminate\Support\Facades\Route;
use Modules\RolesAndPermissions\Http\Controllers\RolesAndPermissionsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('rolesandpermissions', RolesAndPermissionsController::class)->names('rolesandpermissions');
});
