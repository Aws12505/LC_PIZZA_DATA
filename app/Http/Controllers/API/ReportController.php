<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * ReportController - API endpoints for reports
 * 
 * All endpoints return JSON
 * Uses aggregation tables for instant responses
 */
class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * GET /api/reports/sales-summary
     * 
     * Query params:
     *   - store: Store ID (required)
     *   - start: Start date Y-m-d (required)
     *   - end: End date Y-m-d (required)
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $summary = $this->reportService->getStoreSalesSummary(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end'])
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/reports/daily-breakdown
     */
    public function dailyBreakdown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $breakdown = $this->reportService->getDailyBreakdown(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end'])
        );

        return response()->json([
            'success' => true,
            'count' => count($breakdown),
            'data' => $breakdown,
        ]);
    }

    /**
     * GET /api/reports/top-items
     */
    public function topItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $items = $this->reportService->getTopSellingItems(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end']),
            $validated['limit'] ?? 20
        );

        return response()->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    /**
     * GET /api/reports/hourly-sales
     */
    public function hourlySales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'date' => 'required|date',
        ]);

        $hourly = $this->reportService->getHourlySales(
            $validated['store'],
            Carbon::parse($validated['date'])
        );

        return response()->json([
            'success' => true,
            'data' => $hourly,
        ]);
    }

    /**
     * GET /api/reports/channel-performance
     */
    public function channelPerformance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $channels = $this->reportService->getChannelPerformance(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end'])
        );

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    /**
     * GET /api/reports/product-categories
     */
    public function productCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $categories = $this->reportService->getProductCategoryBreakdown(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end'])
        );

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * GET /api/reports/waste-analysis
     */
    public function wasteAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $waste = $this->reportService->getWasteAnalysis(
            $validated['store'],
            Carbon::parse($validated['start']),
            Carbon::parse($validated['end'])
        );

        return response()->json([
            'success' => true,
            'data' => $waste,
        ]);
    }

    /**
     * GET /api/reports/weekly-comparison
     */
    public function weeklyComparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'weeks' => 'nullable|integer|min:1|max:12',
        ]);

        $weeks = $this->reportService->getWeeklyComparison(
            $validated['store'],
            $validated['weeks'] ?? 4
        );

        return response()->json([
            'success' => true,
            'count' => count($weeks),
            'data' => $weeks,
        ]);
    }

    /**
     * GET /api/reports/monthly-comparison
     */
    public function monthlyComparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'months' => 'nullable|integer|min:1|max:24',
        ]);

        $months = $this->reportService->getMonthlyComparison(
            $validated['store'],
            $validated['months'] ?? 6
        );

        return response()->json([
            'success' => true,
            'count' => count($months),
            'data' => $months,
        ]);
    }
}
