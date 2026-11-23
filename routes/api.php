<?php

use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Support\Facades\Route;

/*
| API v1 integrasi ESS (Brief 9.3, US-30). Nonaktif kecuali BLJ_API_ENABLED=true.
| Autentikasi: header "Authorization: Bearer <BLJ_API_TOKEN>".
*/
Route::prefix('v1')->middleware(['blj.api', 'throttle:120,1'])->group(function () {
    Route::post('/sync/employees', [IntegrationController::class, 'syncEmployees']);
    Route::get('/employees/{nik}/trainings', [IntegrationController::class, 'trainings']);
    Route::get('/compliance', [IntegrationController::class, 'compliance']);
    Route::get('/certificates/{no}', [IntegrationController::class, 'certificate'])->where('no', '.*');
});
