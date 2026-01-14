<?php

namespace App\Http\Controllers\API;

use App\Models\Aggregation\{HourlyStoreSummary, DailyStoreSummary, DailyItemSummary};
use App\Services\Analytics\SummaryQueryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class ReportsController extends Controller
{
    protected SummaryQueryService $summaryQuery;

    public function __construct(SummaryQueryService $summaryQuery)
    {
        $this->summaryQuery = $summaryQuery;
    }

    /**
     * DSPR Report - Daily Store Performance Report
     * Route: GET /api/reports/dspr/{store}/{date}
     */
    public function dspr(Request $request, string $store, string $date)
    {
        // Validation
        if (empty($store) || empty($date)) {
            return response()->noContent();
        }
        if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format, expected YYYY-MM-DD'], 400);
        }

        // Fallback items for upselling calculation
        $defaultItemIds = [
            103001, // Crazy Bread
            201128, // EMB Cheese
            201106, // EMB Pepperoni
            105001, // Caesar Wings
            103002, // Crazy Sauce
            103044, // Pepperoni Crazy Puffs®
            103033, // 4 Cheese Crazy Puffs®
            103055, // Bacon & Cheese Crazy Puffs®
        ];

        // Validate items if provided
        $validated = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*' => ['integer'],
        ]);

        $itemIds = isset($validated['items']) && count($validated['items']) > 0
            ? array_values(array_unique($validated['items']))
            : $defaultItemIds;

        // Date calculations - Convert to Carbon for consistency
        $givenDate = Carbon::parse($date);
        $usedDate = Carbon::parse($givenDate);
        $dayName = $givenDate->dayName;

        // Current week (Tuesday-Monday)
        $weekNumber = $usedDate->isoWeek;
        $weekStartDate = Carbon::parse($usedDate)->startOfWeek(CarbonInterface::TUESDAY);
        $weekEndDate = Carbon::parse($weekStartDate)->addDays(6);

        // Previous week
        $prevWeekStartDate = Carbon::parse($weekStartDate)->subWeek();
        $prevWeekEndDate = Carbon::parse($weekEndDate)->subWeek();

        // Lookback period (84 days)
        $lookBackStartDate = Carbon::parse($usedDate)->subDays(84);
        $lookBackEndDate = Carbon::parse($usedDate);

        // External deposit/delivery data (keeping from old system)
        $startStr = $weekStartDate->toDateString();
        $endStr = $weekEndDate->toDateString();
        $base = rtrim('https://hook.pneunited.com/api/deposit-delivery-dsqr-weekly', '/');
        $url = $base . '/' . rawurlencode($store) . '/' . rawurlencode($startStr) . '/' . rawurlencode($endStr);

        Log::info('Fetching weekly deposit/delivery data', [
            'store' => $store,
            'start' => $weekStartDate,
            'end' => $weekEndDate,
            'url' => $url,
        ]);

        $response = Http::get($url);
        $weeklyDepositDeliveryCollection = $response->successful()
            ? collect($response->json()['weeklyDepositDelivery'] ?? [])
            : collect();

        // Previous week deposit/delivery
        $prevStartStr = $prevWeekStartDate->toDateString();
        $prevEndStr = $prevWeekEndDate->toDateString();
        $prevUrl = $base . '/' . rawurlencode($store) . '/' . rawurlencode($prevStartStr) . '/' . rawurlencode($prevEndStr);

        $prevResponse = Http::get($prevUrl);
        $prevWeeklyDepositDeliveryCollection = $prevResponse->successful()
            ? collect($prevResponse->json()['weeklyDepositDelivery'] ?? [])
            : collect();

        // Filter to single day
        $dailyDepositDeliveryCollection = $weeklyDepositDeliveryCollection->where('HookWorkDaysDate', $date);

        // Build reports using new summary tables
        $dailyHourlySalesData = $this->buildDailyHourlySales($store, $usedDate);
        $dailyDSPRData = $this->buildDailyDSPR($store, $usedDate, $dailyDepositDeliveryCollection);
        $weeklyDSPRData = $this->buildWeeklyDSPR($store, $weekStartDate, $weekEndDate, $weeklyDepositDeliveryCollection);
        $prevWeeklyDSPRData = $this->buildWeeklyDSPR($store, $prevWeekStartDate, $prevWeekEndDate, $prevWeeklyDepositDeliveryCollection);

        // Customer Service calculations
        $customerService = $this->calculateCustomerService($store, $dayName, $weekStartDate, $weekEndDate, $lookBackStartDate, $lookBackEndDate);
        $prevCustomerService = $this->calculateCustomerService($store, $dayName, $prevWeekStartDate, $prevWeekEndDate, $lookBackStartDate, $lookBackEndDate);

        // Add customer service scores to DSPR data (only if arrays)
        if (is_array($dailyDSPRData) && is_array($customerService) && isset($customerService['dailyScore'], $customerService['weeklyScore'])) {
            $dailyDSPRData['Customer_count_percent'] = round((float)$customerService['dailyScore'], 2);

            // Calculate Customer_Service average
            $dailyDSPRData['Customer_Service'] = round((
                (float)($dailyDSPRData['Customer_count_percent'] ?? 0) +
                (float)($dailyDSPRData['Put_into_Portal_Percent'] ?? 0) +
                (float)($dailyDSPRData['In_Portal_on_Time_Percent'] ?? 0)
            ) / 3, 2);
        }

        if (is_array($weeklyDSPRData) && is_array($customerService) && isset($customerService['weeklyScore'])) {
            $weeklyDSPRData['Customer_count_percent'] = round((float)$customerService['weeklyScore'], 2);

            $weeklyDSPRData['Customer_Service'] = round((
                (float)($weeklyDSPRData['Customer_count_percent'] ?? 0) +
                (float)($weeklyDSPRData['Put_into_Portal_Percent'] ?? 0) +
                (float)($weeklyDSPRData['In_Portal_on_Time_Percent'] ?? 0)
            ) / 3, 2);
        }

        if (is_array($prevWeeklyDSPRData) && is_array($prevCustomerService) && isset($prevCustomerService['weeklyScore'])) {
            $prevWeeklyDSPRData['Customer_count_percent'] = round((float)$prevCustomerService['weeklyScore'], 2);

            if (isset($prevWeeklyDSPRData['Put_into_Portal_Percent'], $prevWeeklyDSPRData['In_Portal_on_Time_Percent'])) {
                $prevWeeklyDSPRData['Customer_Service'] = round((
                    (float)$prevWeeklyDSPRData['Customer_count_percent'] +
                    (float)$prevWeeklyDSPRData['Put_into_Portal_Percent'] +
                    (float)$prevWeeklyDSPRData['In_Portal_on_Time_Percent']
                ) / 3, 2);
            }
        }

        // Upselling calculations
        $upselling = $this->calculateUpselling($store, $dayName, $weekStartDate, $weekEndDate, $lookBackStartDate, $lookBackEndDate, $itemIds);
        if (is_array($dailyDSPRData) && is_array($upselling) && isset($upselling['dailyScore'], $upselling['weeklyScore'])) {
            $dailyDSPRData['Upselling'] = round((float)$upselling['dailyScore'], 2);
        }

        if (is_array($weeklyDSPRData) && is_array($upselling) && isset($upselling['weeklyScore'])) {
            $weeklyDSPRData['Upselling'] = round((float)$upselling['weeklyScore'], 2);
        }

        $prevUpselling = $this->calculateUpselling($store, $dayName, $prevWeekStartDate, $prevWeekEndDate, $lookBackStartDate, $lookBackEndDate, $itemIds);
        if (is_array($prevWeeklyDSPRData) && is_array($prevUpselling) && isset($prevUpselling['weeklyScore'])) {
            $prevWeeklyDSPRData['Upselling'] = round((float)$prevUpselling['weeklyScore'], 2);
        }

        // Daily DSPR by date for the week
        $dailyDSPRRange = [];
        for ($d = clone $weekStartDate; $d->lte($weekEndDate); $d->addDay()) {
            $key = $d->toDateString();
            $ddForDay = $weeklyDepositDeliveryCollection->where('HookWorkDaysDate', $key);
            $dayDspr = $this->buildDailyDSPR($store, clone $d, $ddForDay);

            if (is_array($dayDspr)) {
                $thisDayName = Carbon::parse($key)->dayName;
                $csForDay = $this->calculateCustomerService($store, $thisDayName, $weekStartDate, $weekEndDate, $lookBackStartDate, $lookBackEndDate);
                $upForDay = $this->calculateUpselling($store, $thisDayName, $weekStartDate, $weekEndDate, $lookBackStartDate, $lookBackEndDate, $itemIds);

                if (is_array($csForDay) && isset($csForDay['dailyScore'])) {
                    $dayDspr['Customer_count_percent'] = round((float)$csForDay['dailyScore'], 2);
                }

                if (is_array($upForDay) && isset($upForDay['dailyScore'])) {
                    $dayDspr['Upselling'] = round((float)$upForDay['dailyScore'], 2);
                }

                if (isset($dayDspr['Customer_count_percent'], $dayDspr['Put_into_Portal_Percent'], $dayDspr['In_Portal_on_Time_Percent'])) {
                    $dayDspr['Customer_Service'] = round((
                        (float)$dayDspr['Customer_count_percent'] +
                        (float)$dayDspr['Put_into_Portal_Percent'] +
                        (float)$dayDspr['In_Portal_on_Time_Percent']
                    ) / 3, 2);
                }

                // Previous week same day
                $prevDate = Carbon::parse($d)->subWeek();
                $prevKey = $prevDate->toDateString();
                $prevDdForDay = $prevWeeklyDepositDeliveryCollection->where('HookWorkDaysDate', $prevKey);
                $prevDayDspr = $this->buildDailyDSPR($store, $prevDate, $prevDdForDay);

                if (is_array($prevDayDspr)) {
                    $prevDayName = $prevDate->dayName;
                    $csPrev = $this->calculateCustomerService($store, $prevDayName, $prevWeekStartDate, $prevWeekEndDate, $lookBackStartDate, $lookBackEndDate);
                    $upPrev = $this->calculateUpselling($store, $prevDayName, $prevWeekStartDate, $prevWeekEndDate, $lookBackStartDate, $lookBackEndDate, $itemIds);

                    if (is_array($csPrev) && isset($csPrev['dailyScore'])) {
                        $prevDayDspr['Customer_count_percent'] = round((float)$csPrev['dailyScore'], 2);
                    }

                    if (is_array($upPrev) && isset($upPrev['dailyScore'])) {
                        $prevDayDspr['Upselling'] = round((float)$upPrev['dailyScore'], 2);
                    }

                    if (isset($prevDayDspr['Customer_count_percent'], $prevDayDspr['Put_into_Portal_Percent'], $prevDayDspr['In_Portal_on_Time_Percent'])) {
                        $prevDayDspr['Customer_Service'] = round((
                            (float)$prevDayDspr['Customer_count_percent'] +
                            (float)$prevDayDspr['Put_into_Portal_Percent'] +
                            (float)$prevDayDspr['In_Portal_on_Time_Percent']
                        ) / 3, 2);
                    }
                }

                $dayDspr['PrevWeek'] = $prevDayDspr ?? 'No data available.';
                $dailyDSPRRange[$key] = $dayDspr;
            } else {
                $dailyDSPRRange[$key] = $dayDspr;
            }
        }

        // Build response
        $response = [
            'Filtering Values' => [
                'date' => $date,
                'store' => $store,
                'items' => $itemIds,
                'week' => $weekNumber,
                'weekStartDate' => $weekStartDate->toDateString(),
                'weekEndDate' => $weekEndDate->toDateString(),
                'look back start' => $lookBackStartDate->toDateString(),
                'look back end' => $lookBackEndDate->toDateString(),
                'depositDeliveryUrl' => $url,
            ],
            'reports' => [
                'daily' => [
                    'dailyHourlySales' => $dailyHourlySalesData,
                    'dailyDSPRData' => $dailyDSPRData,
                ],
                'weekly' => [
                    'DSPRData' => $weeklyDSPRData,
                    'PrevWeekDSPRData' => $prevWeeklyDSPRData,
                    'DailyDSPRByDate' => $dailyDSPRRange,
                ],
            ],
        ];

        return $this->jsonRoundedResponse($response, 2);
    }

    /**
     * Build daily hourly sales data from HourlyStoreSummary model
     */
    protected function buildDailyHourlySales(string $store, Carbon $date): array
    {
        $hours = [];

        for ($h = 0; $h <= 23; $h++) {
            // Query using HourlyStoreSummary model
            $hourlyData = HourlyStoreSummary::where('franchise_store', '=', $store)
                ->where('business_date', '=', $date->toDateString())
                ->where('hour', '=', $h)
                ->first();

            if ($hourlyData) {
                // Calculate combined fields
                $hnrDeliveryQty = (int)($hourlyData->hnr_delivery_quantity ?? 0);
                $hnrCarryoutQty = (int)($hourlyData->hnr_carryout_quantity ?? 0);
                $hnrDeliverySales = (float)($hourlyData->hnr_delivery_sales ?? 0);
                $hnrCarryoutSales = (float)($hourlyData->hnr_carryout_sales ?? 0);

                $hours[$h] = [
                    'Total_Sales' => round((float)$hourlyData->gross_sales, 2),
                    'Phone_Sales' => round((float)$hourlyData->phone_sales, 2),
                    'Call_Center_Agent' => round((float)$hourlyData->call_center_sales, 2),
                    'Drive_Thru' => round((float)$hourlyData->drive_thru_sales, 2),
                    'Website' => round((float)$hourlyData->website_sales, 2),
                    'Mobile' => round((float)$hourlyData->mobile_sales, 2),
                    'Order_Count' => (int)$hourlyData->total_orders,
                    'HNR_Quantity' => $hnrDeliveryQty + $hnrCarryoutQty,
                    'HNR_Sales' => round($hnrDeliverySales + $hnrCarryoutSales, 2),
                ];
            } else {
                $hours[$h] = (object)[];
            }
        }

        return [
            'franchise_store' => $store,
            'business_date' => $date->toDateString(),
            'hours' => $hours,
        ];
    }

    /**
     * Build daily DSPR data from DailyStoreSummary model
     */
    protected function buildDailyDSPR(string $store, Carbon $date, $depositDeliveryCollection): array|string
    {
        $dailySummary = DailyStoreSummary::where('franchise_store', '=', $store)
            ->where('business_date', '=', $date->toDateString())
            ->first();

        if (!$dailySummary) {
            return 'No daily store summary data available.';
        }

        // Calculate portal metrics
        $portalEligible = (int)$dailySummary->portal_eligible_orders;
        $portalUsed = (int)$dailySummary->portal_used_orders;
        $portalOnTime = (int)$dailySummary->portal_on_time_orders;

        $putIntoPortalPercent = $portalEligible > 0
            ? round(($portalUsed / $portalEligible) * 100, 2)
            : 0.0;

        $inPortalOnTimePercent = $portalUsed > 0
            ? round(($portalOnTime / $portalUsed) * 100, 2)
            : 0.0;

        // Calculate combined HNR fields
        $hnrDeliveryQty = (int)($dailySummary->hnr_delivery_quantity ?? 0);
        $hnrCarryoutQty = (int)($dailySummary->hnr_carryout_quantity ?? 0);
        $hnrDeliverySales = (float)($dailySummary->hnr_delivery_sales ?? 0);
        $hnrCarryoutSales = (float)($dailySummary->hnr_carryout_sales ?? 0);

        return [
            'Royalty_Obligation' => round((float)$dailySummary->gross_sales, 2),
            'Phone_Sales' => round((float)$dailySummary->phone_sales, 2),
            'Call_Center_Agent' => round((float)$dailySummary->call_center_sales, 2),
            'Drive_Thru' => round((float)$dailySummary->drive_thru_sales, 2),
            'Website_Sales' => round((float)$dailySummary->website_sales, 2),
            'Mobile_Sales' => round((float)$dailySummary->mobile_sales, 2),
            'Customer_Count' => (int)$dailySummary->customer_count,
            'Customer_count_percent' => 0, // Will be filled by customer service calculation
            'HNR_Total_Quantity' => $hnrDeliveryQty + $hnrCarryoutQty,
            'HNR_Total_Sales' => round($hnrDeliverySales + $hnrCarryoutSales, 2),
            'Portal_Transaction' => $portalEligible,
            'Put_into_Portal' => $portalUsed,
            'Put_into_Portal_Percent' => $putIntoPortalPercent,
            'Put_in_Portal_on_Time' => $portalOnTime,
            'In_Portal_on_Time_Percent' => $inPortalOnTimePercent,
            'Delivery_Tips' => round((float)$dailySummary->delivery_tips, 2),
            'Store_Tips' => round((float)$dailySummary->store_tips, 2),
            'Total_Tips' => round((float)$dailySummary->total_tips, 2),
            'Over_Short' => round((float)$dailySummary->over_short, 2),
            'Cash_Sales' => round((float)$dailySummary->cash_sales, 2),
            'Total_Waste_Cost' => round((float)($dailySummary->total_waste_cost ?? 0), 2),
            'Upselling' => 0, // Will be filled by upselling calculation
        ];
    }

    /**
     * Build weekly DSPR data from DailyStoreSummary model aggregation
     */
    protected function buildWeeklyDSPR(string $store, Carbon $startDate, Carbon $endDate, $depositDeliveryCollection): array|string
    {
        $weeklySummaries = DailyStoreSummary::where('franchise_store', '=', $store)
            ->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        if ($weeklySummaries->isEmpty()) {
            return 'No weekly data available.';
        }

        $totalSales = $weeklySummaries->sum('gross_sales');
        $portalEligible = $weeklySummaries->sum('portal_eligible_orders');
        $portalUsed = $weeklySummaries->sum('portal_used_orders');
        $portalOnTime = $weeklySummaries->sum('portal_on_time_orders');

        $putIntoPortalPercent = $portalEligible > 0
            ? round(($portalUsed / $portalEligible) * 100, 2)
            : 0.0;

        $inPortalOnTimePercent = $portalUsed > 0
            ? round(($portalOnTime / $portalUsed) * 100, 2)
            : 0.0;

        // Calculate combined HNR fields
        $hnrDeliveryQty = $weeklySummaries->sum('hnr_delivery_quantity');
        $hnrCarryoutQty = $weeklySummaries->sum('hnr_carryout_quantity');
        $hnrDeliverySales = $weeklySummaries->sum('hnr_delivery_sales');
        $hnrCarryoutSales = $weeklySummaries->sum('hnr_carryout_sales');

        return [
            'Royalty_Obligation' => round($totalSales, 2),
            'Phone_Sales' => round($weeklySummaries->sum('phone_sales'), 2),
            'Call_Center_Agent' => round($weeklySummaries->sum('call_center_sales'), 2),
            'Drive_Thru' => round($weeklySummaries->sum('drive_thru_sales'), 2),
            'Website_Sales' => round($weeklySummaries->sum('website_sales'), 2),
            'Mobile_Sales' => round($weeklySummaries->sum('mobile_sales'), 2),
            'Customer_Count' => (int)$weeklySummaries->sum('customer_count'),
            'Customer_count_percent' => 0,
            'HNR_Total_Quantity' => (int)($hnrDeliveryQty + $hnrCarryoutQty),
            'HNR_Total_Sales' => round($hnrDeliverySales + $hnrCarryoutSales, 2),
            'Portal_Transaction' => (int)$portalEligible,
            'Put_into_Portal' => (int)$portalUsed,
            'Put_into_Portal_Percent' => $putIntoPortalPercent,
            'Put_in_Portal_on_Time' => (int)$portalOnTime,
            'In_Portal_on_Time_Percent' => $inPortalOnTimePercent,
            'Delivery_Tips' => round($weeklySummaries->sum('delivery_tips'), 2),
            'Store_Tips' => round($weeklySummaries->sum('store_tips'), 2),
            'Total_Tips' => round($weeklySummaries->sum('total_tips'), 2),
            'Over_Short' => round($weeklySummaries->sum('over_short'), 2),
            'Cash_Sales' => round($weeklySummaries->sum('cash_sales'), 2),
            'Total_Waste_Cost' => round($weeklySummaries->sum('total_waste_cost'), 2),
            'Upselling' => 0,
        ];
    }

    /**
     * Calculate customer service score using DailyStoreSummary model
     */
    protected function calculateCustomerService(string $store, string $dayName, Carbon $weekStart, Carbon $weekEnd, Carbon $lookbackStart, Carbon $lookbackEnd): array
    {
        // Get weekly data grouped by day using Eloquent
        $weeklyData = DailyStoreSummary::selectRaw('DAYNAME(business_date) as day_name, SUM(gross_sales) as total_sales', [])
            ->where('franchise_store', '=', $store)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->groupBy('day_name')
            ->get()
            ->keyBy('day_name');

        // Get lookback data grouped by day with averages
        $lookbackData = DailyStoreSummary::selectRaw('DAYNAME(business_date) as day_name, AVG(gross_sales) as avg_sales', [])
            ->where('franchise_store', '=', $store)
            ->whereBetween('business_date', [$lookbackStart->toDateString(), $lookbackEnd->toDateString()])
            ->groupBy('day_name')
            ->get()
            ->keyBy('day_name');

        $weeklyDaysCount = $weeklyData->count();
        $weeklyTotal = $weeklyData->sum('total_sales');
        $weeklyAvr = $weeklyDaysCount > 0 ? $weeklyTotal / $weeklyDaysCount : 0;

        $lookbackAvr = $lookbackData->avg('avg_sales') ?? 0;

        // Weekly final value
        $weeklyFinalValue = $lookbackAvr > 0 ? ($weeklyAvr - $lookbackAvr) / $lookbackAvr : 0;

        // Daily values
        $dailyForWeekly = $weeklyData->get($dayName)->total_sales ?? 0;
        $dailyForLookback = $lookbackData->get($dayName)->avg_sales ?? 0;

        $dailyFinalValue = $dailyForLookback > 0 ? ($dailyForWeekly - $dailyForLookback) / $dailyForLookback : 0;

        // Calculate scores
        $dailyScore = $this->score($dailyFinalValue);
        $weeklyScore = $this->score($weeklyFinalValue);

        return [
            'dailyScore' => $dailyScore / 100,
            'weeklyScore' => $weeklyScore / 100,
        ];
    }

    /**
     * Calculate upselling score based on item sales using DailyItemSummary model
     */
    protected function calculateUpselling(string $store, string $dayName, Carbon $weekStart, Carbon $weekEnd, Carbon $lookbackStart, Carbon $lookbackEnd, array $itemIds): array
    {
        // Get weekly item data grouped by day using Eloquent
        $weeklyData = DailyItemSummary::selectRaw('DAYNAME(business_date) as day_name, SUM(gross_sales) as total_sales', [])
            ->where('franchise_store', '=', $store)
            ->whereIn('item_id', $itemIds)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->groupBy('day_name')
            ->get()
            ->keyBy('day_name');

        // Get lookback item data grouped by day
        $lookbackData = DailyItemSummary::selectRaw('DAYNAME(business_date) as day_name, AVG(gross_sales) as avg_sales', [])
            ->where('franchise_store', '=', $store)
            ->whereIn('item_id', $itemIds)
            ->whereBetween('business_date', [$lookbackStart->toDateString(), $lookbackEnd->toDateString()])
            ->groupBy('day_name')
            ->get()
            ->keyBy('day_name');

        $weeklyDaysCount = $weeklyData->count();
        $weeklyTotal = $weeklyData->sum('total_sales');
        $weeklyAvr = $weeklyDaysCount > 0 ? $weeklyTotal / $weeklyDaysCount : 0;

        $lookbackAvr = $lookbackData->avg('avg_sales') ?? 0;

        $weeklyFinalValue = $lookbackAvr > 0 ? ($weeklyAvr - $lookbackAvr) / $lookbackAvr : 0;

        $dailyForWeekly = $weeklyData->get($dayName)->total_sales ?? 0;
        $dailyForLookback = $lookbackData->get($dayName)->avg_sales ?? 0;

        $dailyFinalValue = $dailyForLookback > 0 ? ($dailyForWeekly - $dailyForLookback) / $dailyForLookback : 0;

        $dailyScore = $this->score($dailyFinalValue);
        $weeklyScore = $this->score($weeklyFinalValue);

        return [
            'dailyScore' => $dailyScore / 100,
            'weeklyScore' => $weeklyScore / 100,
        ];
    }

    /**
     * Score calculation (from old system)
     */
    protected function score($value): int
    {
        $score = 0;
        if ($value >= -1.00 && $value <= -0.1001) {
            $score = 75;
        } elseif ($value >= -0.10 && $value <= -0.0401) {
            $score = 80;
        } elseif ($value >= -0.04 && $value <= -0.0001) {
            $score = 85;
        } elseif ($value >= 0.00 && $value <= 0.0399) {
            $score = 90;
        } elseif ($value >= 0.04 && $value <= 0.0699) {
            $score = 95;
        } elseif ($value >= 0.07 && $value <= 1.00) {
            $score = 100;
        }

        return $score;
    }

    /**
     * Helper method to round all numeric values in response
     */
    protected function roundArray($data, int $precision = 2)
    {
        if (is_array($data)) {
            return array_map(fn($v) => $this->roundArray($v, $precision), $data);
        }
        if ($data instanceof \Illuminate\Support\Collection) {
            return $data->map(fn($v) => $this->roundArray($v, $precision));
        }
        if (is_numeric($data)) {
            return round((float)$data, $precision);
        }
        return $data;
    }

    /**
     * JSON response with proper float formatting
     */
    protected function jsonRoundedResponse(array $payload, int $precision = 2)
    {
        $payload = $this->roundArray($payload, $precision);

        $oldSerialize = ini_get('serialize_precision');
        $oldPrecision = ini_get('precision');

        ini_set('serialize_precision', '-1');
        ini_set('precision', '14');

        $json = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION);

        ini_set('serialize_precision', $oldSerialize === false ? '17' : $oldSerialize);
        ini_set('precision', $oldPrecision === false ? '14' : $oldPrecision);

        return response($json, 200)->header('Content-Type', 'application/json');
    }
}
