<?php

use App\Http\Controllers\ManualCsvImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ExportingController;
/*
|--------------------------------------------------------------------------
| Manual CSV Import Routes
|--------------------------------------------------------------------------
|
| - Index is public (UI page)
| - All actions are protected by auth.secret.key middleware
|
*/

Route::prefix('manual-import')
    ->name('manual.import.')
    ->group(function () {

        // UI page (NO secret key middleware)
        Route::get('/', [ManualCsvImportController::class, 'index'])
            ->name('index');

        // Protected routes
        Route::middleware('auth.secret.key')->group(function () {

            Route::post('/inspect-zip', [ManualCsvImportController::class, 'inspectZip'])
                ->name('inspect.zip');

            Route::post('/upload', [ManualCsvImportController::class, 'upload'])
                ->name('upload');

            Route::get('/progress/{uploadId}', [ManualCsvImportController::class, 'progress'])
                ->name('progress');

            Route::post('/reaggregate', [ManualCsvImportController::class, 'reaggregate'])
                ->name('reaggregate');

            Route::get('/aggregation-progress/{aggregationId}', [ManualCsvImportController::class, 'aggregationProgress'])
                ->name('aggregation.progress');
        });
    });

Route::get('/manual-export', function () {
    return view('manual-export');
})->name('manual.export.index');
