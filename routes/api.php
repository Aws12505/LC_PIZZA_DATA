<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    ExportingController,
    ReportsController,
    DueController,
    KeyController,
    KeyRuleController,
    ValueController,
    ManualCsvImportController
};


/**
 * API Routes for Pizza Data System
 * 
 * All routes return JSON
 * Authenticated via API middleware
 */


// ════════════════════════════════════════════════════════════════════════════════════════════
// EXPORT ROUTES
// ════════════════════════════════════════════════════════════════════════════════════════════

Route::prefix('export')->group(function () {
    Route::get('csv', [ExportingController::class, 'exportCSV'])->name('export.csv')->middleware('auth.secret.key');
    Route::get('json', [ExportingController::class, 'exportJson'])->name('export.json')->middleware('auth.token.store');
});

Route::get('/reports/dspr/{store}/{date}', [ReportsController::class, 'dsprLite'])->middleware('auth.token.store');



Route::prefix('engine')->middleware('auth.token.store')->group(function () {

    // Keys CRUD
    Route::apiResource('keys', KeyController::class);

    // Optional rules convenience
    Route::get('keys/{key}/rules', [KeyRuleController::class, 'index']);
    Route::put('keys/{key}/rules', [KeyRuleController::class, 'replace']);

    // Values
    // Route::get('values', [ValueController::class, 'index']);
    Route::get('stores/{store_id}/values', [ValueController::class, 'storeIndex']);

    Route::post('stores/{store_id}/dates/{date}/values', [ValueController::class, 'upsertOne']);
    Route::post('stores/{store_id}/dates/{date}/values/bulk', [ValueController::class, 'upsertBulk']);

    // Due
    Route::get('stores/{store_id}/dates/{date}/due', [DueController::class, 'dueOnDate']);
    Route::get('stores/{store_id}/due-range', [DueController::class, 'dueRange']);

    Route::get('stores/{store_id}/dates/{date}/values', [ValueController::class, 'grid']);
});


Route::prefix('manual-import')
    ->middleware('auth.token.store')
    ->group(function () {

        // UI page (NO secret key middleware, accessible for API requests)
        Route::get('/', [ManualCsvImportController::class, 'index']);
        Route::post('/inspect-zip', [ManualCsvImportController::class, 'inspectZip']);

        Route::post('/upload', [ManualCsvImportController::class, 'upload']);

        Route::get('/progress/{uploadId}', [ManualCsvImportController::class, 'progress']);

        Route::post('/reaggregate', [ManualCsvImportController::class, 'reaggregate']);

        Route::get('/aggregation-progress/{aggregationId}', [ManualCsvImportController::class, 'aggregationProgress']);
    });
