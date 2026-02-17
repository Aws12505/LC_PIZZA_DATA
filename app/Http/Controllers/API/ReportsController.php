<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Aggregation\HourlyStoreSummary;
use App\Services\Aggregation\IntelligentAggregationService;
use App\Services\Analytics\SummaryQueryService;
use App\Services\Database\DatabaseRouter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Aggregation\DailyStoreSummary;

/**
 * DSPR Lite Report Controller
 *
 * - Cached by store + date
 * - ISO week number, business week = Tuesday → Monday
 * - Uses aggregation tables + DatabaseRouter
 */
class ReportsController extends Controller
{
    /**
     * Cache TTL (seconds)
     * 48 hours = 172800
     */
    private const CACHE_TTL = 172800;

    public function __construct(
        private readonly SummaryQueryService $summaryQuery,
        private readonly IntelligentAggregationService $intelligentAgg,
    ) {}

    /**
     * GET /api/reports/dspr-lite/{store}/{date}
     */
    public function dsprLite(string $store, string $date): JsonResponse
    {
        $this->validateInputs($store, $date);

        $payload = $this->buildReport($store, $date);

        return response()->json($payload);
    }

    // ---------------------------------------------------------------------
    // Core builder (cached)
    // ---------------------------------------------------------------------

    private function buildReport(string $store, string $date): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        [$weekStart, $weekEnd] = $this->isoBusinessWeek($day);

        $prevWeekStart = $weekStart->subWeek();
        $prevWeekEnd   = $weekEnd->subWeek();

        // Same business week last year (Tue–Mon)
        $lastYearWeekStart = $weekStart->subWeeks(52);
        $lastYearWeekEnd   = $weekEnd->subWeeks(52);
        $daily = $this->dailySummary($store, $day);

        $hourlySalesByChannel = $this->hourlySalesByChannel($store, $day);

        $totalSales = [
            'royalty_obligation' => 0,
            'phone_sales' => 0,
            'call_center_sales' => 0,
            'drive_thru_sales' => 0,
            'website_sales' => 0,
            'mobile_sales' => 0,
            'doordash_sales' => 0,
            'ubereats_sales' => 0,
            'grubhub_sales' => 0,
        ];

        // Loop through the hourly data and sum the sales for each channel
        foreach ($hourlySalesByChannel as $hourlyData) {
            $totalSales['royalty_obligation'] += $hourlyData['royalty_obligation'];
            $totalSales['phone_sales'] += $hourlyData['phone_sales'];
            $totalSales['call_center_sales'] += $hourlyData['call_center_sales'];
            $totalSales['drive_thru_sales'] += $hourlyData['drive_thru_sales'];
            $totalSales['website_sales'] += $hourlyData['website_sales'];
            $totalSales['mobile_sales'] += $hourlyData['mobile_sales'];
            $totalSales['doordash_sales'] += $hourlyData['doordash_sales'];
            $totalSales['ubereats_sales'] += $hourlyData['ubereats_sales'];
            $totalSales['grubhub_sales'] += $hourlyData['grubhub_sales'];
        }
        return [
            'filtering' => [
                'store' => $store,
                'date' => $day->toDateString(),
                'iso_week' => $day->isoWeek(),
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],

            'sales' => [
                'this_week_by_day' => $this->salesByDay($store, $weekStart, $weekEnd),
                'previous_week_by_day' => $this->salesByDay($store, $prevWeekStart, $prevWeekEnd),
                'same_week_last_year_by_day' => $this->salesByDay($store, $lastYearWeekStart, $lastYearWeekEnd),
            ],

            'top' => [
                'top_5_items_sales_for_day' => $this->topItemsForDay($store, $day, 5),
                'top_3_ingredients_used' => $this->topIngredientsForDay($store, $day),
            ],

            'day' => [
                'hourly_sales_and_channels' => $hourlySalesByChannel,
                'total_sales' => $totalSales,

                'total_cash_sales' => (float) ($daily->cash_sales ?? 0),
                'total_deposit' => $this->totalDepositForDay($store, $day),

                'over_short' => (float) ($daily->over_short ?? 0),

                'refunded_orders' => [
                    'count' => (int) ($daily->refund_orders ?? 0),
                    'sales' => (float) ($daily->refund_amount ?? 0),
                ],

                'customer_count' => (int) ($daily->customer_count ?? 0),

                'waste' => [
                    'alta_inventory' => $this->altaInventoryWasteForDay($store, $day),
                    'normal' => $this->normalWasteForDay($store, $day),
                ],

                'total_tips' => $this->summaryQuery->getTotalTips($store, $day->toMutable(), $day->toMutable()),

                'hnr' => [
                    'hnr_transactions' => (int) ($daily->hnr_transactions ?? 0),
                    'hnr_broken_promises' => (int) ($daily->hnr_broken_promises ?? 0),
                    'hnr_promise_met' => (int) ($daily->hnr_transactions ?? 0) - (int) ($daily->hnr_broken_promises ?? 0),
                    'hnr_promise_met_percent' => ((int) ($daily->hnr_transactions ?? 0) > 0)
                        ? round((((int) ($daily->hnr_transactions ?? 0) - (int) ($daily->hnr_broken_promises ?? 0)) /
                            (int) ($daily->hnr_transactions ?? 0)) * 100, 2)
                        : 0.0,
                ],

                'labor' => 0,

                'portal' => $this->portalMetrics($store, $day),
            ],
        ];
    }

    private function dailySummary(string $store, CarbonImmutable $day): ?DailyStoreSummary
    {
        return DailyStoreSummary::where('franchise_store', $store)
            ->where('business_date', $day->toDateString())
            ->first();
    }

    // ---------------------------------------------------------------------
    // Cache helpers
    // ---------------------------------------------------------------------

    private function cacheKey(string $store, string $date): string
    {
        return sprintf('reports:dspr-lite:%s:%s', strtolower($store), $date);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    private function validateInputs(string $store, string $date): void
    {
        if ($store === '') {
            throw ValidationException::withMessages(['store' => 'Store is required']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages(['date' => 'Invalid date format']);
        }
    }

    // ---------------------------------------------------------------------
    // Week helpers
    // ---------------------------------------------------------------------

    private function isoBusinessWeek(CarbonImmutable $date): array
    {
        $start = $date->startOfWeek(CarbonInterface::TUESDAY);
        return [$start, $start->addDays(6)];
    }

    // ---------------------------------------------------------------------
    // Sales
    // ---------------------------------------------------------------------

    private function salesByDay(string $store, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];

        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $out[$d->toDateString()] = $this->summaryQuery->getSales(
                $store,
                $d->toMutable(),
                $d->toMutable()
            );
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Top Items
    // ---------------------------------------------------------------------

    private function topItemsForDay(string $store, CarbonImmutable $day, int $limit): array
    {
        $result = $this->intelligentAgg->fetchAggregatedData([
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'summary_type' => 'item',
            'metrics' => [
                ['field' => 'gross_sales', 'agg' => 'SUM', 'alias' => 'gross_sales'],
                ['field' => 'quantity_sold', 'agg' => 'SUM', 'alias' => 'quantity_sold'],
            ],
            'filters' => ['franchise_store' => $store],
            'order_by' => 'gross_sales DESC',
            'limit' => $limit,
        ]);

        return $result['data'] ?? [];
    }

    // ---------------------------------------------------------------------
    // ✅ FIXED: Top Ingredients (uses correct schema)
    // ---------------------------------------------------------------------

    private function topIngredientsForDay(string $store, CarbonImmutable $day): array
    {
        $queries = DatabaseRouter::routedQueries(
            'alta_inventory_ingredient_usage',
            $day->toMutable(),
            $day->toMutable()
        );

        $union = array_shift($queries);
        foreach ($queries as $q) {
            $union->unionAll($q);
        }

        return DB::query()
            ->fromSub($union, 'u')
            ->where('franchise_store', $store)
            ->groupBy('ingredient_id', 'ingredient_description')
            ->orderByDesc(DB::raw('SUM(actual_usage)'))
            ->limit(3)
            ->get([
                'ingredient_id',
                'ingredient_description',
                DB::raw('SUM(actual_usage) as total_actual_usage'),
                DB::raw('SUM(variance_qty) * SUM(ingredient_unit_cost) as total_variance_value'),
            ])
            ->map(static function ($row) {
                return [
                    'ingredient_id' => $row->ingredient_id,
                    'ingredient_description' => $row->ingredient_description,
                    'actual_usage' => round((float) $row->total_actual_usage, 2),
                    'variance_value' => round((float) $row->total_variance_value, 2),
                ];
            })
            ->toArray();
    }

    // ---------------------------------------------------------------------
    // Hourly
    // ---------------------------------------------------------------------

    private function hourlySalesByChannel(string $store, CarbonImmutable $day): array
    {
        return HourlyStoreSummary::where('franchise_store', $store)
            ->where('business_date', $day->toDateString())
            ->orderBy('hour')
            ->get([
                'hour',
                'royalty_obligation',
                'phone_sales',
                'call_center_sales',
                'drive_thru_sales',
                'website_sales',
                'mobile_sales',
                'doordash_sales',
                'ubereats_sales',
                'grubhub_sales',
            ])
            ->toArray();
    }

    // ---------------------------------------------------------------------
    // Deposit
    // ---------------------------------------------------------------------

    private function totalDepositForDay(string $store, CarbonImmutable $day): float
    {
        $queries = DatabaseRouter::routedQueries(
            'financial_views',
            $day->toMutable(),
            $day->toMutable()
        );

        $union = array_shift($queries);
        foreach ($queries as $q) {
            $union->unionAll($q);
        }

        return (float) DB::query()
            ->fromSub($union, 'f')
            ->where('franchise_store', $store)
            ->where('sub_account', 'Cash-Check-Deposit')
            ->sum('amount');
    }

    // ---------------------------------------------------------------------
    // Waste
    // ---------------------------------------------------------------------

    private function altaInventoryWasteForDay(string $store, CarbonImmutable $day): float
    {
        $queries = DatabaseRouter::routedQueries(
            'alta_inventory_waste',
            $day->toMutable(),
            $day->toMutable()
        );

        $union = array_shift($queries);
        foreach ($queries as $q) {
            $union->unionAll($q);
        }

        return (float) DB::query()
            ->fromSub($union, 'w')
            ->where('franchise_store', $store)
            ->selectRaw('SUM(unit_food_cost * qty) as total_waste_cost')
            ->value('total_waste_cost') ?? 0.0;
    }

    private function normalWasteForDay(string $store, CarbonImmutable $day): float
    {
        $queries = DatabaseRouter::routedQueries(
            'waste',
            $day->toMutable(),
            $day->toMutable()
        );

        $union = array_shift($queries);
        foreach ($queries as $q) {
            $union->unionAll($q);
        }

        return (float) DB::query()
            ->fromSub($union, 'w')
            ->where('franchise_store', $store)
            ->selectRaw('SUM(item_cost * quantity) as total_waste_cost')
            ->value('total_waste_cost') ?? 0.0;
    }


    // ---------------------------------------------------------------------
    // Portal
    // ---------------------------------------------------------------------

    private function portalMetrics(string $store, CarbonImmutable $day): array
    {
        $eligible = $this->summaryQuery->getPortalEligibleOrders($store, $day->toMutable(), $day->toMutable());
        $used = $this->summaryQuery->getPortalUsedOrders($store, $day->toMutable(), $day->toMutable());
        $onTime = $this->summaryQuery->getPortalOnTimeOrders($store, $day->toMutable(), $day->toMutable());

        return [
            'portal_eligible_orders' => $eligible,
            'portal_used_orders' => $used,
            'portal_on_time_orders' => $onTime,
            'put_into_portal_percent' => $eligible > 0 ? round(($used / $eligible) * 100, 2) : 0,
            'in_portal_on_time_percent' => $used > 0 ? round(($onTime / $used) * 100, 2) : 0,
        ];
    }
}
