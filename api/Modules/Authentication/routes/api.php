<?php

use App\Http\Middleware\ResolveSiteFromDomain;
use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\AuthenticationController;


Route::post("/login",[AuthenticationController::class,"login"])
    ->middleware(ResolveSiteFromDomain::class);

Route::post("/self/registration",[AuthenticationController::class,"selfRegisterUser"])
    ->middleware(ResolveSiteFromDomain::class);

Route::get("/list-countries",[AuthenticationController::class,"listCountries"])
    ->middleware(ResolveSiteFromDomain::class);

Route::get("/list-brands",[AuthenticationController::class,"listbrands"])
    ->middleware(ResolveSiteFromDomain::class);


Route::get("/list-department",[AuthenticationController::class,"listDepertments"])
    ->middleware(ResolveSiteFromDomain::class);


Route::get("/list-designation",[AuthenticationController::class,"listDesignations"])
    ->middleware(ResolveSiteFromDomain::class);


Route::get("/list-role",[AuthenticationController::class,"listRole"])
    ->middleware(ResolveSiteFromDomain::class);

Route::get("/list-office",[AuthenticationController::class,"listOffice"])
    ->middleware(ResolveSiteFromDomain::class);




Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('authentication', AuthenticationController::class)->names('authentication');

    Route::post("/logout", [AuthenticationController::class, "logout"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::get("/list-users", [AuthenticationController::class, "listUsers"])->middleware(ResolveSiteFromDomain::class);
    Route::post("/password-change", [AuthenticationController::class, "changePasswordReset"])->middleware(ResolveSiteFromDomain::class);;
    Route::post("/update-user/{id}", [AuthenticationController::class, "updateUser"])->middleware(ResolveSiteFromDomain::class);


    Route::delete('/delete-user/{id}', [AuthenticationController::class, 'deleteUser'])->middleware(ResolveSiteFromDomain::class);

    Route::get("/get-menu-permissions", [AuthenticationController::class, "menuPermissions"]);

    Route::post("/register-user",
        [AuthenticationController::class, "registerUser"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::get("/list-permission",
        [AuthenticationController::class, "listPermission"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::post("/create-role",
        [AuthenticationController::class, "createRole"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::get('/roles', [AuthenticationController::class, 'listRoles'])->middleware(ResolveSiteFromDomain::class);
    Route::put('/roles/{roleId}', [AuthenticationController::class, 'updateRole'])->middleware(ResolveSiteFromDomain::class);
    Route::delete('/roles/{roleId}', [AuthenticationController::class, 'deleteRole'])->middleware(ResolveSiteFromDomain::class);

});




