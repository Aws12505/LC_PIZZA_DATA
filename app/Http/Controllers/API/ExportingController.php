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
    public function exportCSV(Request $request){ 

        DB::connection()->disableQueryLog();
        set_time_limit(0);

        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'store' => 'nullable|string',
            'model' => 'required|string|in:detail_orders,order_line,summary_sales',
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

            // Build query using DatabaseRouter
            $query = DatabaseRouter::query($model, $startDate, $endDate);

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
            'model' => 'required|string|in:detail_orders,order_line,summary_sales',
            'limit' => 'nullable|integer|min:1|max:50000',
        ]);

        $startDate = Carbon::parse($validated['start']);
        $endDate = Carbon::parse($validated['end']);
        $store = $validated['store'] ?? null;
        $model = $validated['model'];
        $limit = $validated['limit'] ?? null;

        // Build query using DatabaseRouter
        $query = DatabaseRouter::query($model, $startDate, $endDate);

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
     * Get columns for a model
     */
    protected function getColumnsForModel(string $model): array
    {
        $columnMap = [
            'detail_orders' => [
                'franchise_store', 'business_date', 'order_id', 'date_time_placed',
                'date_time_fulfilled', 'gross_sales', 'royalty_obligation', 'customer_count',
                'order_placed_method', 'order_fulfilled_method', 'delivery_tip', 'sales_tax'
            ],
            'order_line' => [
                'franchise_store', 'business_date', 'order_id', 'item_id', 'menu_item_name',
                'quantity', 'net_amount', 'order_placed_method', 'order_fulfilled_method'
            ],
            'summary_sales' => [
                'franchise_store', 'business_date', 'gross_sales', 'royalty_obligation',
                'customer_count', 'sales_tax', 'over_short'
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
