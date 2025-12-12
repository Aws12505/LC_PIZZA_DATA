<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Database\DatabaseRouter;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExportingController - Export data as CSV or JSON
 * 
 * Updated to use DatabaseRouter for queries spanning operational/analytics
 * Supports all models including aggregation tables
 */
class ExportingController extends Controller
{
    protected const CHUNK_SIZE = 5000;
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Export data as CSV
     * 
     * GET /api/export/csv?start=2025-01-01&end=2025-12-31&store=03795&model=detail_orders
     */
    public function exportCSV(Request $request)
    { 
        DB::connection()->disableQueryLog();
        set_time_limit(0);

        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'store' => 'nullable|string',
            'model' => 'required|string|in:' . implode(',', $this->getAvailableModels()),
        ]);

        $startDate = Carbon::parse($validated['start']);
        $endDate = Carbon::parse($validated['end']);
        $store = $validated['store'] ?? null;
        $model = $validated['model'];

        // Get columns for model
        $columns = $this->getColumnsForModel($model);

        $filename = $this->makeFilename($model, $startDate, $endDate, $store, 'csv');

        $callback = function () use ($model, $startDate, $endDate, $store, $columns) {
            $out = fopen('php://output', 'w');

            // Write header
            fputcsv($out, $columns);

            // Build query based on model type
            $query = $this->buildQuery($model, $startDate, $endDate);

            if ($store) {
                $query->where('franchise_store', $store);
            }

            $query->select($columns);

            // Get primary key for chunking
            $pk = $this->getPrimaryKeyForModel($model);

            // Stream data in chunks
            $query->orderBy($pk)
                ->chunkById(self::CHUNK_SIZE, function ($rows) use ($out, $columns) {
                    foreach ($rows as $row) {
                        $line = [];
                        foreach ($columns as $col) {
                            $line[] = $row->{$col} ?? null;
                        }
                        fputcsv($out, $line);
                    }
                }, $pk);

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    /**
     * Export data as JSON
     * 
     * GET /api/export/json?start=2025-01-01&end=2025-12-31&store=03795&model=detail_orders
     */
    public function exportJson(Request $request)
    {
        DB::connection()->disableQueryLog();
        set_time_limit(0);

        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'store' => 'nullable|string',
            'model' => 'required|string|in:' . implode(',', $this->getAvailableModels()),
            'limit' => 'nullable|integer|min:1|max:50000',
        ]);

        $startDate = Carbon::parse($validated['start']);
        $endDate = Carbon::parse($validated['end']);
        $store = $validated['store'] ?? null;
        $model = $validated['model'];
        $limit = $validated['limit'] ?? null;

        // Build query based on model type
        $query = $this->buildQuery($model, $startDate, $endDate);

        if ($store) {
            $query->where('franchise_store', $store);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'record_count' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * Build query based on model type
     */
    protected function buildQuery(string $model, Carbon $startDate, Carbon $endDate)
    {
        // Check if this is an aggregation table
        if ($this->isAggregationTable($model)) {
            return $this->buildAggregationQuery($model, $startDate, $endDate);
        }

        // Use DatabaseRouter for operational/analytics tables
        return DatabaseRouter::query($model, $startDate, $endDate);
    }

    /**
     * Check if model is an aggregation table
     */
    protected function isAggregationTable(string $model): bool
    {
        $aggregationTables = [
            'yearly_store_summary', 'yearly_item_summary',
            'weekly_store_summary', 'weekly_item_summary',
            'quarterly_store_summary', 'quarterly_item_summary',
            'monthly_store_summary', 'monthly_item_summary',
            'daily_store_summary', 'daily_item_summary',
        ];

        return in_array($model, $aggregationTables);
    }

    /**
     * Build query for aggregation tables
     */
    protected function buildAggregationQuery(string $model, Carbon $startDate, Carbon $endDate)
    {
        $query = DB::connection('analytics')->table($model);

        // Apply date filters based on table type
        switch ($model) {
            case 'yearly_store_summary':
            case 'yearly_item_summary':
                $query->whereBetween('year_num', [
                    $startDate->year,
                    $endDate->year
                ]);
                break;

            case 'weekly_store_summary':
            case 'weekly_item_summary':
                // For weekly, filter by week_start_date and week_end_date
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('week_start_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ])->orWhereBetween('week_end_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ]);
                });
                break;

            case 'quarterly_store_summary':
            case 'quarterly_item_summary':
                // For quarterly, filter by quarter_start_date and quarter_end_date
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('quarter_start_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ])->orWhereBetween('quarter_end_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ]);
                });
                break;

            case 'monthly_store_summary':
            case 'monthly_item_summary':
                // For monthly, filter by year and month
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->where('year_num', '>=', $startDate->year)
                      ->where('year_num', '<=', $endDate->year);
                    
                    // If same year, apply month filter
                    if ($startDate->year === $endDate->year) {
                        $q->whereBetween('month_num', [
                            $startDate->month,
                            $endDate->month
                        ]);
                    }
                });
                break;

            case 'daily_store_summary':
            case 'daily_item_summary':
                $query->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString()
                ]);
                break;
        }

        return $query;
    }

    /**
     * Get available models
     */
    protected function getAvailableModels(): array
    {
        return [
            // Operational/Analytics tables (with hot/archive)
            'detail_orders', 'order_line', 'summary_sales', 'summary_items',
            'summary_transactions', 'waste', 'cash_management', 'financial_views',
            'alta_inventory_waste', 'alta_inventory_ingredient_usage',
            'alta_inventory_ingredient_orders', 'alta_inventory_cogs',
            // Aggregation tables
            'yearly_store_summary', 'yearly_item_summary',
            'weekly_store_summary', 'weekly_item_summary',
            'quarterly_store_summary', 'quarterly_item_summary',
            'monthly_store_summary', 'monthly_item_summary',
            'daily_store_summary', 'daily_item_summary',
        ];
    }

    /**
     * Get columns for a model
     */
    protected function getColumnsForModel(string $model): array
    {
        $columnMap = [
            'detail_orders' => [
                'franchise_store', 'business_date', 'order_id', 'date_time_placed',
                'date_time_fulfilled', 'royalty_obligation', 'quantity', 'customer_count',
                'taxable_amount', 'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount',
                'sales_tax', 'gross_sales', 'occupational_tax', 'employee',
                'override_approval_employee', 'order_placed_method', 'order_fulfilled_method',
                'delivery_tip', 'delivery_tip_tax', 'delivery_fee', 'delivery_fee_tax',
                'delivery_service_fee', 'delivery_service_fee_tax', 'delivery_small_order_fee',
                'delivery_small_order_fee_tax', 'modified_order_amount', 'modification_reason',
                'refunded', 'payment_methods', 'transaction_type', 'store_tip_amount',
                'promise_date', 'tax_exemption_id', 'tax_exemption_entity_name', 'user_id',
                'hnrOrder', 'broken_promise', 'portal_eligible', 'portal_used',
                'put_into_portal_before_promise_time', 'portal_compartments_used', 'time_loaded_into_portal'
            ],
            'order_line' => [
                'franchise_store', 'business_date', 'order_id', 'item_id',
                'date_time_placed', 'date_time_fulfilled', 'menu_item_name', 'menu_item_account',
                'bundle_name', 'net_amount', 'quantity', 'royalty_item', 'taxable_item',
                'tax_included_amount', 'employee', 'override_approval_employee',
                'order_placed_method', 'order_fulfilled_method', 'modified_order_amount',
                'modification_reason', 'payment_methods', 'refunded'
            ],
            'summary_sales' => [
                'franchise_store', 'business_date', 'royalty_obligation', 'customer_count',
                'taxable_amount', 'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount',
                'refund_amount', 'sales_tax', 'gross_sales', 'occupational_tax', 'delivery_tip',
                'delivery_fee', 'delivery_service_fee', 'delivery_small_order_fee',
                'modified_order_amount', 'store_tip_amount', 'prepaid_cash_orders',
                'prepaid_non_cash_orders', 'prepaid_sales', 'prepaid_delivery_tip',
                'prepaid_in_store_tip_amount', 'over_short', 'previous_day_refunds', 'saf', 'manager_notes'
            ],
            'summary_items' => [
                'franchise_store', 'business_date', 'menu_item_name', 'menu_item_account',
                'item_id', 'item_quantity', 'royalty_obligation', 'taxable_amount',
                'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount', 'tax_included_amount'
            ],
            'summary_transactions' => [
                'franchise_store', 'business_date', 'payment_method', 'sub_payment_method',
                'total_amount', 'saf_qty', 'saf_total'
            ],
            'waste' => [
                'business_date', 'franchise_store', 'cv_item_id', 'menu_item_name',
                'expired', 'waste_date_time', 'produce_date_time', 'waste_reason',
                'cv_order_id', 'waste_type', 'item_cost', 'quantity'
            ],
            'cash_management' => [
                'franchise_store', 'business_date', 'create_datetime', 'verified_datetime',
                'till', 'check_type', 'system_totals', 'verified', 'variance',
                'created_by', 'verified_by'
            ],
            'financial_views' => [
                'franchise_store', 'business_date', 'area', 'sub_account', 'amount'
            ],
            'alta_inventory_waste' => [
                'franchise_store', 'business_date', 'item_id', 'item_description',
                'waste_reason', 'unit_food_cost', 'qty'
            ],
            'alta_inventory_ingredient_usage' => [
                'franchise_store', 'business_date', 'count_period', 'ingredient_id',
                'ingredient_description', 'ingredient_category', 'ingredient_unit',
                'ingredient_unit_cost', 'starting_inventory_qty', 'received_qty',
                'net_transferred_qty', 'ending_inventory_qty', 'actual_usage',
                'theoretical_usage', 'variance_qty', 'waste_qty'
            ],
            'alta_inventory_ingredient_orders' => [
                'franchise_store', 'business_date', 'supplier', 'invoice_number',
                'purchase_order_number', 'ingredient_id', 'ingredient_description',
                'ingredient_category', 'ingredient_unit', 'unit_price', 'order_qty',
                'sent_qty', 'received_qty', 'total_cost'
            ],
            'alta_inventory_cogs' => [
                'franchise_store', 'business_date', 'count_period', 'inventory_category',
                'starting_value', 'received_value', 'net_transfer_value', 'ending_value',
                'used_value', 'theoretical_usage_value', 'variance_value'
            ],
            'yearly_store_summary' => [
                'franchise_store', 'year_num', 'total_sales', 'gross_sales', 'net_sales',
                'refund_amount', 'total_orders', 'completed_orders', 'cancelled_orders',
                'modified_orders', 'refunded_orders', 'avg_order_value', 'customer_count',
                'avg_customers_per_order', 'operational_days', 'operational_months',
                'avg_daily_sales', 'avg_monthly_sales', 'sales_vs_prior_year', 'sales_growth_percent',
                'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
                'mobile_orders', 'mobile_sales', 'call_center_orders', 'call_center_sales',
                'drive_thru_orders', 'drive_thru_sales', 'doordash_orders', 'doordash_sales',
                'ubereats_orders', 'ubereats_sales', 'grubhub_orders', 'grubhub_sales',
                'delivery_orders', 'delivery_sales', 'carryout_orders', 'carryout_sales',
                'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
                'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
                'beverages_quantity', 'beverages_sales', 'crazy_puffs_quantity', 'crazy_puffs_sales',
                'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',
                'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',
                'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
                'portal_on_time_orders', 'portal_on_time_rate', 'total_waste_items',
                'total_waste_cost', 'digital_orders', 'digital_sales', 'digital_penetration'
            ],
            'yearly_item_summary' => [
                'franchise_store', 'year_num', 'item_id', 'menu_item_name', 'menu_item_account',
                'quantity_sold', 'gross_sales', 'net_sales', 'avg_item_price',
                'avg_daily_quantity', 'delivery_quantity', 'carryout_quantity'
            ],
            'weekly_store_summary' => [
                'franchise_store', 'year_num', 'week_num', 'week_start_date', 'week_end_date',
                'total_sales', 'gross_sales', 'net_sales', 'refund_amount', 'total_orders',
                'completed_orders', 'cancelled_orders', 'modified_orders', 'refunded_orders',
                'avg_order_value', 'customer_count', 'avg_customers_per_order',
                'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
                'mobile_orders', 'mobile_sales', 'call_center_orders', 'call_center_sales',
                'drive_thru_orders', 'drive_thru_sales', 'doordash_orders', 'doordash_sales',
                'ubereats_orders', 'ubereats_sales', 'grubhub_orders', 'grubhub_sales',
                'delivery_orders', 'delivery_sales', 'carryout_orders', 'carryout_sales',
                'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
                'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
                'beverages_quantity', 'beverages_sales', 'crazy_puffs_quantity', 'crazy_puffs_sales',
                'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',
                'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',
                'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
                'portal_on_time_orders', 'portal_on_time_rate', 'total_waste_items',
                'total_waste_cost', 'digital_orders', 'digital_sales', 'digital_penetration',
                'sales_vs_prior_week', 'sales_growth_percent', 'orders_vs_prior_week', 'orders_growth_percent'
            ],
            'weekly_item_summary' => [
                'franchise_store', 'year_num', 'week_num', 'week_start_date', 'week_end_date',
                'item_id', 'menu_item_name', 'menu_item_account', 'quantity_sold',
                'gross_sales', 'net_sales', 'avg_item_price', 'avg_daily_quantity',
                'delivery_quantity', 'carryout_quantity'
            ],
            'quarterly_store_summary' => [
                'franchise_store', 'year_num', 'quarter_num', 'quarter_start_date', 'quarter_end_date',
                'total_sales', 'gross_sales', 'net_sales', 'refund_amount', 'total_orders',
                'completed_orders', 'cancelled_orders', 'modified_orders', 'refunded_orders',
                'avg_order_value', 'customer_count', 'avg_customers_per_order',
                'operational_days', 'operational_months', 'avg_daily_sales', 'avg_monthly_sales',
                'sales_vs_prior_quarter', 'sales_growth_percent',
                'sales_vs_same_quarter_prior_year', 'yoy_growth_percent',
                'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
                'mobile_orders', 'mobile_sales', 'call_center_orders', 'call_center_sales',
                'drive_thru_orders', 'drive_thru_sales', 'doordash_orders', 'doordash_sales',
                'ubereats_orders', 'ubereats_sales', 'grubhub_orders', 'grubhub_sales',
                'delivery_orders', 'delivery_sales', 'carryout_orders', 'carryout_sales',
                'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
                'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
                'beverages_quantity', 'beverages_sales', 'crazy_puffs_quantity', 'crazy_puffs_sales',
                'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',
                'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',
                'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
                'portal_on_time_orders', 'portal_on_time_rate', 'total_waste_items',
                'total_waste_cost', 'digital_orders', 'digital_sales', 'digital_penetration'
            ],
            'quarterly_item_summary' => [
                'franchise_store', 'year_num', 'quarter_num', 'quarter_start_date', 'quarter_end_date',
                'item_id', 'menu_item_name', 'menu_item_account', 'quantity_sold',
                'gross_sales', 'net_sales', 'avg_item_price', 'avg_daily_quantity',
                'delivery_quantity', 'carryout_quantity'
            ],
            'monthly_store_summary' => [
                'franchise_store', 'year_num', 'month_num', 'month_name', 'total_sales',
                'gross_sales', 'net_sales', 'refund_amount', 'total_orders',
                'completed_orders', 'cancelled_orders', 'modified_orders', 'refunded_orders',
                'avg_order_value', 'customer_count', 'avg_customers_per_order',
                'operational_days', 'avg_daily_sales', 'avg_daily_orders',
                'sales_vs_prior_month', 'sales_growth_percent',
                'sales_vs_same_month_prior_year', 'yoy_growth_percent',
                'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
                'mobile_orders', 'mobile_sales', 'call_center_orders', 'call_center_sales',
                'drive_thru_orders', 'drive_thru_sales', 'doordash_orders', 'doordash_sales',
                'ubereats_orders', 'ubereats_sales', 'grubhub_orders', 'grubhub_sales',
                'delivery_orders', 'delivery_sales', 'carryout_orders', 'carryout_sales',
                'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
                'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
                'beverages_quantity', 'beverages_sales', 'crazy_puffs_quantity', 'crazy_puffs_sales',
                'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',
                'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',
                'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
                'portal_on_time_orders', 'portal_on_time_rate', 'total_waste_items',
                'total_waste_cost', 'digital_orders', 'digital_sales', 'digital_penetration'
            ],
            'monthly_item_summary' => [
                'franchise_store', 'year_num', 'month_num', 'item_id', 'menu_item_name',
                'menu_item_account', 'quantity_sold', 'gross_sales', 'net_sales',
                'avg_item_price', 'avg_daily_quantity', 'quantity_vs_prior_month',
                'quantity_growth_percent', 'sales_vs_prior_month', 'sales_growth_percent',
                'delivery_quantity', 'carryout_quantity'
            ],
            'daily_store_summary' => [
                'franchise_store', 'business_date', 'total_sales', 'gross_sales', 'net_sales',
                'refund_amount', 'total_orders', 'completed_orders', 'cancelled_orders',
                'modified_orders', 'refunded_orders', 'avg_order_value', 'customer_count',
                'avg_customers_per_order', 'phone_orders', 'phone_sales', 'website_orders',
                'website_sales', 'mobile_orders', 'mobile_sales', 'call_center_orders',
                'call_center_sales', 'drive_thru_orders', 'drive_thru_sales', 'doordash_orders',
                'doordash_sales', 'ubereats_orders', 'ubereats_sales', 'grubhub_orders',
                'grubhub_sales', 'delivery_orders', 'delivery_sales', 'carryout_orders',
                'carryout_sales', 'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
                'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
                'beverages_quantity', 'beverages_sales', 'crazy_puffs_quantity', 'crazy_puffs_sales',
                'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',
                'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',
                'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
                'portal_on_time_orders', 'portal_on_time_rate', 'total_waste_items',
                'total_waste_cost', 'digital_orders', 'digital_sales', 'digital_penetration'
            ],
            'daily_item_summary' => [
                'franchise_store', 'business_date', 'item_id', 'menu_item_name',
                'menu_item_account', 'quantity_sold', 'gross_sales', 'net_sales',
                'avg_item_price', 'delivery_quantity', 'carryout_quantity',
                'modified_quantity', 'refunded_quantity'
            ],
        ];

        return $columnMap[$model] ?? ['*'];
    }

    /**
     * Get primary key for a model
     */
    protected function getPrimaryKeyForModel(string $model): string
    {
        return 'id';
    }

    /**
     * Make filename for export
     */
    protected function makeFilename(
        string $model,
        Carbon $startDate,
        Carbon $endDate,
        ?string $store,
        string $extension
    ): string {
        $parts = [
            $model,
            $startDate->format('Ymd'),
            $endDate->format('Ymd')
        ];

        if ($store) {
            $parts[] = $store;
        }

        return implode('_', $parts) . '.' . $extension;
    }
}
