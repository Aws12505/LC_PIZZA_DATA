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

        $cacheKey = $this->cacheKey($store, $date);

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store, $date) {
            return $this->buildReport($store, $date);
        });

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

        $lastYearWeekStart = $weekStart->subYear();
        $lastYearWeekEnd   = $weekEnd->subYear();

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
                'hourly_sales_and_channels' => $this->hourlySalesByChannel($store, $day),

                'total_cash_sales' => $this->summaryQuery->getCashSales($store, $day->toMutable(), $day->toMutable()),
                'total_deposit' => $this->totalDepositForDay($store, $day),

                'over_short' => $this->summaryQuery->getOverShort($store, $day->toMutable(), $day->toMutable()),

                'refunded_orders' => [
                    'count' => $this->summaryQuery->getRefundedOrders($store, $day->toMutable(), $day->toMutable()),
                    'sales' => $this->summaryQuery->getRefundAmount($store, $day->toMutable(), $day->toMutable()),
                ],

                'waste' => [
                    'alta_inventory' => $this->altaInventoryWasteForDay($store, $day),
                    'normal' => $this->normalWasteForDay($store, $day),
                ],

                'total_tips' => $this->summaryQuery->getTotalTips($store, $day->toMutable(), $day->toMutable()),

                'portal' => $this->portalMetrics($store, $day),
            ],
        ];
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
            ])
            ->map(static function ($row) {
                return [
                    'ingredient_id' => $row->ingredient_id,
                    'ingredient_description' => $row->ingredient_description,
                    'actual_usage' => round((float) $row->total_actual_usage, 2),
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
                'gross_sales',
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
            ->sum('waste_cost');
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
            ->sum('waste_cost');
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
