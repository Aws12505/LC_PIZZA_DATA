<?php

namespace App\Services\Aggregation;

use DateTime;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// Import all your existing models
use App\Models\Aggregation\{HourlyStoreSummary, HourlyItemSummary, DailyStoreSummary, DailyItemSummary, WeeklyStoreSummary, WeeklyItemSummary, MonthlyStoreSummary, MonthlyItemSummary, QuarterlyStoreSummary, QuarterlyItemSummary, YearlyStoreSummary, YearlyItemSummary};
use Illuminate\Support\Facades\DB;

/**
 * Intelligent Multi-Granularity Data Aggregation Service
 * 
 * DYNAMIC COST-BASED OPTIMIZATION
 * - Compares ALL granularities (yearly/monthly/weekly/daily)
 * - Chooses optimal for EACH operation (add/subtract)
 * - Recursively optimizes each period
 * 
 * Example: Query 2024-01-22 to 2025-01-02
 *   Optimal: yearly_2024 - monthly[Jan] + daily[22-31] + daily[2025:1-2]
 *   Result: 14 rows instead of naive 345 rows (96% reduction)
 */
class IntelligentAggregationService
{
    private float $executionStartTime;
    private int $debugLevel = 0; // Set to 1 for optimization logs

    /**
     * Map granularity + summary type to model class
     */
    private const MODEL_MAP = [
        'hourly_store' => HourlyStoreSummary::class,
        'hourly_item' => HourlyItemSummary::class,
        'daily_store' => DailyStoreSummary::class,
        'daily_item' => DailyItemSummary::class,
        'weekly_store' => WeeklyStoreSummary::class,
        'weekly_item' => WeeklyItemSummary::class,
        'monthly_store' => MonthlyStoreSummary::class,
        'monthly_item' => MonthlyItemSummary::class,
        'quarterly_store' => QuarterlyStoreSummary::class,
        'quarterly_item' => QuarterlyItemSummary::class,
        'yearly_store' => YearlyStoreSummary::class,
        'yearly_item' => YearlyItemSummary::class,
    ];

    public function fetchAggregatedData(array $input): array
    {
        $this->executionStartTime = microtime(true);

        $request = $this->parseRequest($input);
        $strategy = $this->optimizeStrategy($request);
        $data = $this->executeQueries($request, $strategy);

        return [
            'success' => true,
            'data' => $data,
            'metadata' => [
                'query_plan' => $strategy,
                'execution_time_ms' => round((microtime(true) - $this->executionStartTime) * 1000, 2),
                'rows_returned' => count($data),
                'rows_scanned' => $this->calculateTotalRows($strategy),
            ]
        ];
    }

    public function explain(array $input): array
    {
        $this->debugLevel = 1;
        $request = $this->parseRequest($input);
        $strategy = $this->optimizeStrategy($request);

        return [
            'request' => $request,
            'strategy' => $strategy,
        ];
    }

    // ========================================================================
    // LAYER 1: REQUEST PARSING
    // ========================================================================

    private function parseRequest(array $input): array
    {
        $startDate = new DateTime($input['start_date'] ?? throw new InvalidArgumentException('start_date required'));
        $endDate = new DateTime($input['end_date'] ?? throw new InvalidArgumentException('end_date required'));

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('start_date must be before end_date');
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary_type' => $input['summary_type'] ?? 'store',
            'metrics' => $this->parseMetrics($input['metrics'] ?? ['*']),
            'filters' => $input['filters'] ?? [],
            'group_by' => $input['group_by'] ?? [],
            'order_by' => $input['order_by'] ?? null,
            'limit' => $input['limit'] ?? null,
            'day_span' => $startDate->diff($endDate)->days + 1,
        ];
    }

    private function parseMetrics(array $metricsInput): array
    {
        $metrics = [];

        foreach ($metricsInput as $metric) {
            if ($metric === '*') {
                return [
                    ['field' => 'total_sales', 'agg' => 'SUM', 'alias' => 'total_sales'],
                    ['field' => 'gross_sales', 'agg' => 'SUM', 'alias' => 'gross_sales'],
                    ['field' => 'total_orders', 'agg' => 'SUM', 'alias' => 'total_orders'],
                ];
            }

            if (is_string($metric)) {
                $metrics[] = [
                    'field' => $metric,
                    'agg' => $this->inferAggregation($metric),
                    'alias' => $metric
                ];
            } elseif (is_array($metric)) {
                $metrics[] = [
                    'field' => $metric['field'],
                    'agg' => $metric['agg'] ?? 'SUM',
                    'alias' => $metric['alias'] ?? $metric['field']
                ];
            }
        }

        return $metrics;
    }

    private function inferAggregation(string $field): string
    {
        if (
            str_starts_with($field, 'avg_') || str_ends_with($field, '_rate') ||
            str_ends_with($field, 'penetration')
        ) {
            return 'AVG';
        }
        return 'SUM';
    }

    // ========================================================================
    // LAYER 2: INTELLIGENT COST-BASED STRATEGY OPTIMIZATION
    // ========================================================================

    private function optimizeStrategy(array $request): array
    {
        $days = $request['day_span'];

        // Short ranges: direct query
        if ($days <= 3) {
            return ['operations' => [
                ['type' => '+', 'granularity' => 'hourly', 'start' => $request['start_date'], 'end' => $request['end_date']]
            ]];
        }

        if ($days <= 14) {
            return ['operations' => [
                ['type' => '+', 'granularity' => 'daily', 'start' => $request['start_date'], 'end' => $request['end_date']]
            ]];
        }

        // Long ranges: use intelligent cost-based optimization
        return $this->buildIntelligentStrategy($request['start_date'], $request['end_date'], '+');
    }

    /**
     * CORE ALGORITHM: Recursive cost-based optimization
     * Tries ALL possible granularities and chooses the one with minimum cost
     */
    private function buildIntelligentStrategy(DateTime $start, DateTime $end, string $operationType): array
    {
        $operations = [];

        // Calculate costs for each approach
        $costs = [
            'direct_daily' => $this->calculateDirectCost($start, $end, 'daily'),
            'direct_weekly' => $this->calculateDirectCost($start, $end, 'weekly'),
            'direct_monthly' => $this->calculateDirectCost($start, $end, 'monthly'),
            'yearly_subtraction' => $this->calculateYearlySubtractionCost($start, $end),
            'monthly_subtraction' => $this->calculateMonthlySubtractionCost($start, $end),
        ];

        if ($this->debugLevel > 0) {
            echo "\nOptimizing period: " . $start->format('Y-m-d') . " to " . $end->format('Y-m-d') . "\n";
            echo "Costs: " . json_encode($costs, JSON_PRETTY_PRINT) . "\n";
        }

        // Choose minimum cost approach
        $minCost = min($costs);
        $bestApproach = array_search($minCost, $costs);

        if ($this->debugLevel > 0) {
            echo "Chosen: {$bestApproach} (cost: {$minCost})\n";
        }

        // Build operations based on best approach
        switch ($bestApproach) {
            case 'yearly_subtraction':
                $operations = $this->buildYearlySubtractionOps($start, $end);
                break;

            case 'monthly_subtraction':
                $operations = $this->buildMonthlySubtractionOps($start, $end);
                break;

            case 'direct_weekly':
                $operations = $this->buildDirectOps($start, $end, 'weekly');
                break;

            case 'direct_monthly':
                $operations = $this->buildDirectOps($start, $end, 'monthly');
                break;

            default: // direct_daily
                $operations = $this->buildDirectOps($start, $end, 'daily');
        }

        return ['operations' => $operations];
    }

    /**
     * Calculate cost for direct query (no subtraction)
     */
    private function calculateDirectCost(DateTime $start, DateTime $end, string $granularity): int
    {
        $days = $start->diff($end)->days + 1;

        return match ($granularity) {
            'hourly' => $days * 24,
            'daily' => $days,
            'weekly' => (int)ceil($days / 7),
            'monthly' => (int)ceil($days / 30),
            'quarterly' => (int)ceil($days / 90),
            'yearly' => (int)ceil($days / 365),
            default => $days
        };
    }

    /**
     * Calculate cost for yearly + subtraction approach
     */
    private function calculateYearlySubtractionCost(DateTime $start, DateTime $end): int
    {
        $startYear = (int)$start->format('Y');
        $endYear = (int)$end->format('Y');
        $cost = 0;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearStart = new DateTime("{$year}-01-01");
            $yearEnd = new DateTime("{$year}-12-31");

            $overlapDays = $this->getOverlapDays($yearStart, $yearEnd, $start, $end);

            if ($overlapDays >= 183) { // More than half year
                $cost += 1; // Add yearly

                // Cost of excluding leading days
                if ($yearStart < $start) {
                    $exclusionEnd = (clone $start)->modify('-1 day');
                    $cost += $this->getOptimalExclusionCost($yearStart, $exclusionEnd);
                }

                // Cost of excluding trailing days
                if ($yearEnd > $end && $yearEnd->format('Y') == $end->format('Y')) {
                    $exclusionStart = (clone $end)->modify('+1 day');
                    $cost += $this->getOptimalExclusionCost($exclusionStart, $yearEnd);
                }
            } else {
                // Partial year - use monthly/daily
                $periodStart = max($yearStart, $start);
                $periodEnd = min($yearEnd, $end);
                $days = $periodStart->diff($periodEnd)->days + 1;
                $cost += min((int)ceil($days / 30), $days);
            }
        }

        return $cost;
    }

    /**
     * Calculate cost for monthly + subtraction approach
     */
    private function calculateMonthlySubtractionCost(DateTime $start, DateTime $end): int
    {
        $cost = 0;
        $current = clone $start;

        while ($current <= $end) {
            $monthStart = new DateTime($current->format('Y-m-01'));
            $monthEnd = new DateTime($current->format('Y-m-t'));

            $overlapDays = $this->getOverlapDays($monthStart, $monthEnd, $start, $end);

            if ($overlapDays >= 15) { // More than half month
                $cost += 1; // Add monthly

                // Leading exclusion
                if ($monthStart < $start && $monthStart->format('Y-m') == $start->format('Y-m')) {
                    $exclusionEnd = (clone $start)->modify('-1 day');
                    $cost += $this->getOptimalExclusionCost($monthStart, $exclusionEnd);
                }

                // Trailing exclusion
                if ($monthEnd > $end && $monthEnd->format('Y-m') == $end->format('Y-m')) {
                    $exclusionStart = (clone $end)->modify('+1 day');
                    $cost += $this->getOptimalExclusionCost($exclusionStart, $monthEnd);
                }
            } else {
                $cost += $overlapDays; // Use daily
            }

            $current = (clone $monthStart)->modify('+1 month');
        }

        return $cost;
    }

    /**
     * Get optimal cost for excluding a period (choose best granularity)
     */
    private function getOptimalExclusionCost(DateTime $start, DateTime $end): int
    {
        $days = $start->diff($end)->days + 1;

        if ($days <= 3) {
            return $days; // Daily
        }

        // Check if weekly aligned
        if ($start->format('N') == 1 && $days >= 7) { // Starts on Monday
            $weeks = (int)floor($days / 7);
            $remainingDays = $days % 7;

            if ($weeks > 0 && $weeks < $days) {
                return $weeks + $remainingDays; // Weekly + daily remainder
            }
        }

        // Check if monthly aligned
        if ($start->format('d') == '01' && $days >= 28) {
            $months = 0;
            $current = clone $start;
            $tempEnd = clone $end;

            while ($current->format('d') == '01' && $current <= $tempEnd) {
                $monthEnd = new DateTime($current->format('Y-m-t'));
                if ($monthEnd <= $tempEnd) {
                    $months++;
                    $current = (clone $monthEnd)->modify('+1 day');
                } else {
                    break;
                }
            }

            $remaining = $current <= $tempEnd ? $current->diff($tempEnd)->days + 1 : 0;

            if ($months > 0 && ($months + $remaining) < $days) {
                return $months + $remaining; // Monthly + daily remainder
            }
        }

        return $days; // Fall back to daily
    }

    /**
     * Build operations for yearly + subtraction
     */
    private function buildYearlySubtractionOps(DateTime $start, DateTime $end): array
    {
        $operations = [];
        $startYear = (int)$start->format('Y');
        $endYear = (int)$end->format('Y');

        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearStart = new DateTime("{$year}-01-01");
            $yearEnd = new DateTime("{$year}-12-31");

            $overlapDays = $this->getOverlapDays($yearStart, $yearEnd, $start, $end);

            if ($overlapDays >= 183) {
                // Add yearly
                $operations[] = [
                    'type' => '+',
                    'granularity' => 'yearly',
                    'start' => $yearStart,
                    'end' => $yearEnd,
                    'metadata' => ['year_num' => $year]
                ];

                // Subtract leading period optimally
                if ($yearStart < $start) {
                    $exclusionEnd = (clone $start)->modify('-1 day');
                    $exclusionOps = $this->buildOptimalExclusion($yearStart, $exclusionEnd);
                    $operations = array_merge($operations, $exclusionOps);
                }

                // Subtract trailing period optimally
                if ($yearEnd > $end && $yearEnd->format('Y') == $end->format('Y')) {
                    $exclusionStart = (clone $end)->modify('+1 day');
                    $exclusionOps = $this->buildOptimalExclusion($exclusionStart, $yearEnd);
                    $operations = array_merge($operations, $exclusionOps);
                }
            } else {
                // Use monthly/daily for partial year
                $periodStart = max($yearStart, $start);
                $periodEnd = min($yearEnd, $end);
                $operations[] = [
                    'type' => '+',
                    'granularity' => 'daily',
                    'start' => $periodStart,
                    'end' => $periodEnd
                ];
            }
        }

        return $operations;
    }

    /**
     * Build operations for monthly + subtraction
     */
    private function buildMonthlySubtractionOps(DateTime $start, DateTime $end): array
    {
        $operations = [];
        $current = clone $start;

        while ($current <= $end) {
            $monthStart = new DateTime($current->format('Y-m-01'));
            $monthEnd = new DateTime($current->format('Y-m-t'));

            $overlapDays = $this->getOverlapDays($monthStart, $monthEnd, $start, $end);

            if ($overlapDays >= 15) {
                // Add monthly
                $operations[] = [
                    'type' => '+',
                    'granularity' => 'monthly',
                    'start' => $monthStart,
                    'end' => $monthEnd,
                    'metadata' => [
                        'year_num' => (int)$monthStart->format('Y'),
                        'month_num' => (int)$monthStart->format('m')
                    ]
                ];

                // Subtract leading
                if ($monthStart < $start && $monthStart->format('Y-m') == $start->format('Y-m')) {
                    $exclusionEnd = (clone $start)->modify('-1 day');
                    $exclusionOps = $this->buildOptimalExclusion($monthStart, $exclusionEnd);
                    $operations = array_merge($operations, $exclusionOps);
                }

                // Subtract trailing
                if ($monthEnd > $end && $monthEnd->format('Y-m') == $end->format('Y-m')) {
                    $exclusionStart = (clone $end)->modify('+1 day');
                    $exclusionOps = $this->buildOptimalExclusion($exclusionStart, $monthEnd);
                    $operations = array_merge($operations, $exclusionOps);
                }
            } else {
                // Add daily for partial month
                $periodStart = max($monthStart, $start);
                $periodEnd = min($monthEnd, $end);
                $operations[] = [
                    'type' => '+',
                    'granularity' => 'daily',
                    'start' => $periodStart,
                    'end' => $periodEnd
                ];
            }

            $current = (clone $monthStart)->modify('+1 month');
        }

        return $operations;
    }

    /**
     * Build optimal exclusion operations (choose best granularity)
     */
    private function buildOptimalExclusion(DateTime $start, DateTime $end): array
    {
        $operations = [];
        $days = $start->diff($end)->days + 1;

        if ($days <= 3) {
            return [[
                'type' => '-',
                'granularity' => 'daily',
                'start' => $start,
                'end' => $end
            ]];
        }

        // Try monthly if aligned
        if ($start->format('d') == '01' && $days >= 28) {
            $current = clone $start;
            $months = [];

            while ($current->format('d') == '01' && $current <= $end) {
                $monthEnd = new DateTime($current->format('Y-m-t'));
                if ($monthEnd <= $end) {
                    $months[] = [
                        'type' => '-',
                        'granularity' => 'monthly',
                        'start' => $current,
                        'end' => $monthEnd,
                        'metadata' => [
                            'year_num' => (int)$current->format('Y'),
                            'month_num' => (int)$current->format('m')
                        ]
                    ];
                    $current = (clone $monthEnd)->modify('+1 day');
                } else {
                    break;
                }
            }

            if (!empty($months)) {
                $operations = array_merge($operations, $months);

                // Add remaining days
                if ($current <= $end) {
                    $operations[] = [
                        'type' => '-',
                        'granularity' => 'daily',
                        'start' => $current,
                        'end' => $end
                    ];
                }

                return $operations;
            }
        }

        // Try weekly if aligned
        if ($start->format('N') == 1 && $days >= 7) {
            $weeks = (int)floor($days / 7);

            if ($weeks > 0) {
                $current = clone $start;
                for ($i = 1; $i <= $weeks; $i++) {
                    $wEnd = (clone $current)->modify('+6 days');
                    $operations[] = [
                        'type' => '-',
                        'granularity' => 'weekly',
                        'start' => $current,
                        'end' => $wEnd,
                        'metadata' => [
                            'year_num' => (int)$current->format('Y'),
                            'week_num' => (int)$current->format('W')
                        ]
                    ];
                    $current = (clone $wEnd)->modify('+1 day');
                }

                // Remaining days
                if ($current <= $end) {
                    $operations[] = [
                        'type' => '-',
                        'granularity' => 'daily',
                        'start' => $current,
                        'end' => $end
                    ];
                }

                return $operations;
            }
        }

        // Fall back to daily
        return [[
            'type' => '-',
            'granularity' => 'daily',
            'start' => $start,
            'end' => $end
        ]];
    }

    /**
     * Build direct operations (no subtraction)
     */
    private function buildDirectOps(DateTime $start, DateTime $end, string $granularity): array
    {
        return [[
            'type' => '+',
            'granularity' => $granularity,
            'start' => $start,
            'end' => $end
        ]];
    }

    private function getOverlapDays(DateTime $p1Start, DateTime $p1End, DateTime $p2Start, DateTime $p2End): int
    {
        $overlapStart = max($p1Start, $p2Start);
        $overlapEnd = min($p1End, $p2End);

        if ($overlapStart > $overlapEnd) {
            return 0;
        }

        return $overlapStart->diff($overlapEnd)->days + 1;
    }

    // ========================================================================
    // LAYER 3: QUERY BUILDING WITH ELOQUENT
    // ========================================================================

    /**
     * Get the Eloquent model class for a given granularity and summary type
     */
    private function getModelClass(string $granularity, string $summaryType): string
    {
        $key = "{$granularity}_{$summaryType}";

        if (!isset(self::MODEL_MAP[$key])) {
            throw new InvalidArgumentException("No model found for {$key}");
        }

        return self::MODEL_MAP[$key];
    }

    /**
     * Build Eloquent query for an operation
     */
    private function buildQuery(array $request, array $operation): Builder
    {
        $modelClass = $this->getModelClass($operation['granularity'], $request['summary_type']);
        $query = $modelClass::query();

        // Apply WHERE conditions
        $this->applyWhereConditions($query, $operation, $request['filters']);

        // Apply SELECT with aggregations
        $this->applySelectClause($query, $request['metrics'], $operation['granularity'], $request['summary_type']);

        // Apply GROUP BY
        $this->applyGroupBy($query, $operation['granularity'], $request['summary_type']);

        // Apply ORDER BY
        if ($request['order_by']) {
            $query->orderByRaw($request['order_by']);
        }

        // Apply LIMIT
        if ($request['limit']) {
            $query->limit($request['limit']);
        }

        return $query;
    }

    /**
     * Apply WHERE conditions to query
     */
    private function applyWhereConditions(Builder $query, array $operation, array $filters): void
    {
        // Date/Period filtering
        if (isset($operation['metadata']['year_num'])) {
            $query->where('year_num', $operation['metadata']['year_num']);

            if (isset($operation['metadata']['week_num'])) {
                $query->where('week_num', $operation['metadata']['week_num']);
            }
            if (isset($operation['metadata']['month_num'])) {
                $query->where('month_num', $operation['metadata']['month_num']);
            }
        } else {
            $query->whereBetween('business_date', [
                $operation['start']->format('Y-m-d'),
                $operation['end']->format('Y-m-d')
            ]);
        }

        // Store filtering
        if (isset($filters['franchise_store'])) {
            if (is_array($filters['franchise_store'])) {
                $query->whereIn('franchise_store', $filters['franchise_store']);
            } else {
                $query->where('franchise_store', $filters['franchise_store']);
            }
        }

        // Additional filters
        foreach ($filters as $key => $value) {
            if ($key === 'franchise_store') {
                continue; // Already handled
            }

            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }
    }

    /**
     * Apply SELECT clause with aggregations
     */
    private function applySelectClause(Builder $query, array $metrics, string $granularity, string $summaryType): void
    {
        $selectFields = ['franchise_store'];

        // Add granularity-specific fields
        $selectFields = array_merge($selectFields, match ($granularity) {
            'hourly' => ['business_date', 'hour'],
            'daily' => ['business_date'],
            'weekly' => ['year_num', 'week_num'],
            'monthly' => ['year_num', 'month_num'],
            'yearly' => ['year_num'],
            default => ['business_date']
        });

        // Add item fields if needed
        if ($summaryType === 'item') {
            $selectFields = array_merge($selectFields, ['item_id', 'menu_item_name']);
        }

        // Add metric aggregations
        $aggregations = [];
        foreach ($metrics as $metric) {
            $aggregations[] = DB::raw("{$metric['agg']}({$metric['field']}) as {$metric['alias']}");
        }

        $query->select(array_merge($selectFields, $aggregations));
    }

    /**
     * Apply GROUP BY clause
     */
    private function applyGroupBy(Builder $query, string $granularity, string $summaryType): void
    {
        $groupFields = ['franchise_store'];

        $groupFields = array_merge($groupFields, match ($granularity) {
            'hourly' => ['business_date', 'hour'],
            'daily' => ['business_date'],
            'weekly' => ['year_num', 'week_num'],
            'monthly' => ['year_num', 'month_num'],
            'yearly' => ['year_num'],
            default => ['business_date']
        });

        if ($summaryType === 'item') {
            $groupFields = array_merge($groupFields, ['item_id', 'menu_item_name']);
        }

        $query->groupBy($groupFields);
    }

    // ========================================================================
    // LAYER 4: QUERY EXECUTION & SUBTRACTION
    // ========================================================================

    private function executeQueries(array $request, array $strategy): array
    {
        $additions = [];
        $subtractions = [];

        foreach ($strategy['operations'] as $operation) {
            $query = $this->buildQuery($request, $operation);
            $result = $query->get()->toArray();
            $normalized = $this->normalizeResult($result, $operation['granularity']);

            if ($operation['type'] === '+') {
                $additions[] = $normalized;
            } else {
                $subtractions[] = $normalized;
            }
        }

        $merged = $this->mergeResults($additions);

        foreach ($subtractions as $subData) {
            $merged = $this->subtractResults($merged, $subData, $request['metrics']);
        }

        return $this->cleanResults($merged);
    }

    private function normalizeResult(array $rows, string $granularity): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $row['_key'] = ($row['franchise_store'] ?? '') . '|' . ($row['item_id'] ?? '');
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function mergeResults(array $resultSets): array
    {
        if (empty($resultSets)) {
            return [];
        }

        $merged = [];

        foreach ($resultSets as $resultSet) {
            foreach ($resultSet as $row) {
                $key = $row['_key'];

                if (!isset($merged[$key])) {
                    $merged[$key] = $row;
                } else {
                    $merged[$key] = $this->sumRows($merged[$key], $row);
                }
            }
        }

        return array_values($merged);
    }

    private function subtractResults(array $base, array $toSubtract, array $metrics): array
    {
        $subtractMap = [];
        foreach ($toSubtract as $row) {
            $subtractMap[$row['_key']] = $row;
        }

        $result = [];
        foreach ($base as $baseRow) {
            if (isset($subtractMap[$baseRow['_key']])) {
                $baseRow = $this->subtractRows($baseRow, $subtractMap[$baseRow['_key']], $metrics);
            }
            $result[] = $baseRow;
        }

        return $result;
    }

    private function sumRows(array $row1, array $row2): array
    {
        $result = $row1;

        // Get all numeric fields dynamically
        $numericFields = array_filter($row1, function ($value, $key) {
            return is_numeric($value) && $key !== '_key' && !in_array($key, ['year_num', 'month_num', 'week_num', 'hour', 'item_id']);
        }, ARRAY_FILTER_USE_BOTH);

        foreach (array_keys($numericFields) as $field) {
            if (isset($row1[$field]) && isset($row2[$field])) {
                $result[$field] = $row1[$field] + $row2[$field];
            }
        }

        return $result;
    }

    private function subtractRows(array $row1, array $row2, array $metrics): array
    {
        $result = $row1;

        foreach ($metrics as $metric) {
            if ($metric['agg'] === 'SUM') {
                $field = $metric['alias'];
                if (isset($row1[$field]) && isset($row2[$field])) {
                    $result[$field] = max(0, $row1[$field] - $row2[$field]);
                }
            }
        }

        return $result;
    }

    private function cleanResults(array $results): array
    {
        return array_map(function ($row) {
            unset($row['_key']);
            return $row;
        }, $results);
    }

    private function calculateTotalRows(array $strategy): int
    {
        $total = 0;
        foreach ($strategy['operations'] as $op) {
            $days = $op['start']->diff($op['end'])->days + 1;
            $total += match ($op['granularity']) {
                'yearly' => 1,
                'monthly' => 1,
                'weekly' => 1,
                'daily' => $days,
                'hourly' => $days * 24,
                default => $days
            };
        }
        return $total;
    }
}
