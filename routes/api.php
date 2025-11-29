<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    ReportController,
    ExportingController,
    DashboardController
};

/**
 * API Routes for Pizza Data System
 * 
 * All routes return JSON
 * Authenticated via API middleware
 */

// ════════════════════════════════════════════════════════════════════════════════════════════
// DASHBOARD ROUTES
// ════════════════════════════════════════════════════════════════════════════════════════════

Route::prefix('dashboard')->group(function () {
    Route::get('overview', [DashboardController::class, 'overview'])->name('dashboard.overview');
    Route::get('stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
});

// ════════════════════════════════════════════════════════════════════════════════════════════
// REPORT ROUTES
// ════════════════════════════════════════════════════════════════════════════════════════════

Route::prefix('reports')->group(function () {
    // Sales reports
    Route::get('sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales-summary');
    Route::get('daily-breakdown', [ReportController::class, 'dailyBreakdown'])->name('reports.daily-breakdown');
    Route::get('hourly-sales', [ReportController::class, 'hourlySales'])->name('reports.hourly-sales');

    // Performance reports
    Route::get('channel-performance', [ReportController::class, 'channelPerformance'])->name('reports.channel-performance');
    Route::get('product-categories', [ReportController::class, 'productCategories'])->name('reports.product-categories');
    Route::get('top-items', [ReportController::class, 'topItems'])->name('reports.top-items');

    // Analysis reports
    Route::get('waste-analysis', [ReportController::class, 'wasteAnalysis'])->name('reports.waste-analysis');
    Route::get('weekly-comparison', [ReportController::class, 'weeklyComparison'])->name('reports.weekly-comparison');
    Route::get('monthly-comparison', [ReportController::class, 'monthlyComparison'])->name('reports.monthly-comparison');
});

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
