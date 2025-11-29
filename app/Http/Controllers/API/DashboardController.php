<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use App\Services\Database\DatabaseRouter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * DashboardController - Quick dashboard statistics
 */
class DashboardController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * GET /api/dashboard/overview
     * 
     * Quick overview for today, yesterday, week, month
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
        ]);

        $store = $validated['store'];
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $cacheKey = "dashboard_overview_{$store}_" . $today->toDateString();

        $data = Cache::remember($cacheKey, 300, function () use ($store, $today, $yesterday, $weekStart, $monthStart) {
            return [
                'today' => $this->getDaySummary($store, $today),
                'yesterday' => $this->getDaySummary($store, $yesterday),
                'week' => $this->getPeriodSummary($store, $weekStart, $today),
                'month' => $this->getPeriodSummary($store, $monthStart, $today),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/dashboard/stats
     * 
     * Latest statistics and health checks
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => 'required|string',
        ]);

        $store = $validated['store'];

        $stats = [
            'last_import' => $this->getLastImportDate(),
            'data_health' => $this->getDataHealth($store),
            'partition_info' => DatabaseRouter::getDataDistribution('detail_orders'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get summary for a single day
     */
    protected function getDaySummary(string $store, Carbon $date): ?array
    {
        $summary = DB::connection('analytics')
            ->table('daily_store_summary')
            ->where('franchise_store', $store)
            ->where('business_date', $date->toDateString())
            ->select([
                'total_sales',
                'total_orders',
                'customer_count',
                'avg_order_value',
                'digital_penetration'
            ])
            ->first();

        if (!$summary) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'total_sales' => $summary->total_sales ?? 0,
            'total_orders' => $summary->total_orders ?? 0,
            'customer_count' => $summary->customer_count ?? 0,
            'avg_order_value' => round($summary->avg_order_value ?? 0, 2),
            'digital_penetration' => round($summary->digital_penetration ?? 0, 2),
        ];
    }

    /**
     * Get summary for a period
     */
    protected function getPeriodSummary(string $store, Carbon $start, Carbon $end): array
    {
        $summary = DB::connection('analytics')
            ->table('daily_store_summary')
            ->where('franchise_store', $store)
            ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('
                SUM(total_sales) as total_sales,
                SUM(total_orders) as total_orders,
                SUM(customer_count) as customer_count,
                AVG(avg_order_value) as avg_order_value,
                AVG(digital_penetration) as avg_digital_penetration
            ')
            ->first();

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_sales' => $summary->total_sales ?? 0,
            'total_orders' => $summary->total_orders ?? 0,
            'customer_count' => $summary->customer_count ?? 0,
            'avg_order_value' => round($summary->avg_order_value ?? 0, 2),
            'avg_digital_penetration' => round($summary->avg_digital_penetration ?? 0, 2),
        ];
    }

    /**
     * Get last import date
     */
    protected function getLastImportDate(): ?string
    {
        $lastDate = DB::connection('operational')
            ->table('detail_orders_hot')
            ->max('business_date');

        return $lastDate;
    }

    /**
     * Get data health metrics
     */
    protected function getDataHealth(string $store): array
    {
        $yesterday = Carbon::yesterday();

        $hasYesterdayData = DB::connection('operational')
            ->table('detail_orders_hot')
            ->where('franchise_store', $store)
            ->where('business_date', $yesterday->toDateString())
            ->exists();

        $recentDaysWithData = DB::connection('operational')
            ->table('detail_orders_hot')
            ->where('franchise_store', $store)
            ->where('business_date', '>=', Carbon::now()->subDays(7)->toDateString())
            ->distinct('business_date')
            ->count('business_date');

        return [
            'has_yesterday_data' => $hasYesterdayData,
            'recent_days_with_data' => $recentDaysWithData,
            'health_status' => $hasYesterdayData && $recentDaysWithData >= 5 ? 'good' : 'warning',
        ];
    }
}
