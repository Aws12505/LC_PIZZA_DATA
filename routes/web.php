<?php

use App\Http\Controllers\ManualCsvImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('manual-import')->name('manual.import.')->group(function () {
    Route::get('/', [ManualCsvImportController::class, 'index'])->name('index');
    Route::post('/inspect-zip', [ManualCsvImportController::class, 'inspectZip'])->name('inspect.zip');
    Route::post('/upload', [ManualCsvImportController::class, 'upload'])->name('upload');
    Route::get('/progress/{uploadId}', [ManualCsvImportController::class, 'progress'])->name('progress');
    Route::post('/reaggregate', [ManualCsvImportController::class, 'reaggregate'])->name('reaggregate');
    Route::get('/aggregation-progress/{aggregationId}', [ManualCsvImportController::class, 'aggregationProgress'])->name('aggregation.progress');
});

