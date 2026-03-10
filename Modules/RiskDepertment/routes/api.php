<?php

use App\Http\Middleware\ResolveSiteFromDomain;
use Illuminate\Support\Facades\Route;
use Modules\RiskDepertment\Http\Controllers\IncidentNotificationRuleController;
use Modules\RiskDepertment\Http\Controllers\ReportController;
use Modules\RiskDepertment\Http\Controllers\RiskDepertmentController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('riskdepertments', RiskDepertmentController::class)->names('riskdepertment');
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post("/save-report", [RiskDepertmentController::class, "store"])
        ->middleware(ResolveSiteFromDomain::class);


    Route::get("/get-incident", [RiskDepertmentController::class,"index"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::post("/show-incident/{id}", [RiskDepertmentController::class,"show"])
        ->middleware(ResolveSiteFromDomain::class);


    Route::post("/incident-reports/{id}/status/{status}", [RiskDepertmentController::class,"updateReportStatus"])
        ->middleware(ResolveSiteFromDomain::class);

    Route::get('/list-incident-type', [RiskDepertmentController::class, 'listIncidentTypes'])->middleware(ResolveSiteFromDomain::class);;
    Route::get('/get-incident-type/{id}', [RiskDepertmentController::class, 'getIncidentType'])->middleware(ResolveSiteFromDomain::class);;
    Route::post('/create-incident-type', [RiskDepertmentController::class, 'createIncidentType'])->middleware(ResolveSiteFromDomain::class);;
    Route::put('/update-incident-type/{id}', [RiskDepertmentController::class, 'updateIncidentType'])->middleware(ResolveSiteFromDomain::class);;
    Route::delete('/delete-incident-type/{id}', [RiskDepertmentController::class, 'deleteIncidentType'])->middleware(ResolveSiteFromDomain::class);
    // routes/api.php
    Route::get('/incident-reports/export', [RiskDepertmentController::class, 'export'])->middleware(ResolveSiteFromDomain::class);
    Route::post('/assign-investigator/{id}', [RiskDepertmentController::class, 'assignInvestigator'])->middleware(ResolveSiteFromDomain::class);
    Route::post('/escalate-report/{id}', [RiskDepertmentController::class, 'escalateIncident'])->middleware(ResolveSiteFromDomain::class);
    Route::get('/downloadpdf/{id}', [RiskDepertmentController::class, 'downloadSinglePdf'])->middleware(ResolveSiteFromDomain::class);
    Route::get('/incidents/analytics/by-type', [ReportController::class, 'getIncidentByType'])->middleware(ResolveSiteFromDomain::class);


    Route::get('/incident-notification-rules', [IncidentNotificationRuleController::class, 'index'])->middleware(ResolveSiteFromDomain::class);
    Route::post('/incident-notification-rules', [IncidentNotificationRuleController::class, 'store'])->middleware(ResolveSiteFromDomain::class);
    Route::put('/incident-notification-rules/{id}', [IncidentNotificationRuleController::class, 'update'])->middleware(ResolveSiteFromDomain::class);
    Route::delete('/incident-notification-rules/{id}', [IncidentNotificationRuleController::class, 'destroy'])->middleware(ResolveSiteFromDomain::class);

});
