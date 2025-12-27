<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    ExportingController,
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
    Route::get('csv', [ExportingController::class, 'exportCSV'])->name('export.csv');
    Route::get('json', [ExportingController::class, 'exportJson'])->name('export.json');
});

// ════════════════════════════════════════════════════════════════════════════════════════════
// EXAMPLE REQUESTS
// ════════════════════════════════════════════════════════════════════════════════════════════

/**
 * Dashboard Overview:
 * GET /api/dashboard/overview?store=03795
 * 
 * Sales Summary:
 * GET /api/reports/sales-summary?store=03795&start=2025-01-01&end=2025-01-31
 * 
 * Top Items:
 * GET /api/reports/top-items?store=03795&start=2025-01-01&end=2025-01-31&limit=20
 * 
 * Channel Performance:
 * GET /api/reports/channel-performance?store=03795&start=2025-01-01&end=2025-01-31
 * 
 * Export CSV:
 * GET /api/export/csv?model=detail_orders&store=03795&start=2025-01-01&end=2025-01-31
 * 
 * Export JSON:
 * GET /api/export/json?model=detail_orders&store=03795&start=2025-01-01&end=2025-01-31&limit=1000
 */
