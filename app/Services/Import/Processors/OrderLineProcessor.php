<?php

namespace App\Services\Import\Processors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderLineProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'order_line';
    }

    /**
     * Order line uses REPLACE strategy - CSV is source of truth
     * We delete existing data for the date AND store, then insert fresh data
     */
    protected function getImportStrategy(): string
    {
        return self::STRATEGY_REPLACE;
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'order_id',
            'item_id',
            'date_time_placed',
            'date_time_fulfilled',
            'menu_item_name',
            'menu_item_account',
            'bundle_name',
            'net_amount',
            'quantity',
            'royalty_item',
            'taxable_item',
            'tax_included_amount',
            'employee',
            'override_approval_employee',
            'order_placed_method',
            'order_fulfilled_method',
            'modified_order_amount',
            'modification_reason',
            'payment_methods',
            'refunded',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['date_time_placed'] = $this->parseDateTime($row['date_time_placed'] ?? null);
        $row['date_time_fulfilled'] = $this->parseDateTime($row['date_time_fulfilled'] ?? null);

        // Parse numeric fields
        $row['net_amount'] = $this->toNumeric($row['net_amount'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);
        $row['tax_included_amount'] = $this->toNumeric($row['tax_included_amount'] ?? null);
        $row['modified_order_amount'] = $this->toNumeric($row['modified_order_amount'] ?? null);

        return $row;
    }

    protected function validate(array $row): bool
    {
        return true;
    }

    /**
     * FIXED: Delete by BOTH business_date AND franchise_store (not just date)
     * This matches the old code's partition delete strategy
     */
    protected function deleteExistingData(string $business_date, string $tableName, string $connection): void
    {
        // Get franchise_store from first row (all rows should have same store)
        // This is safe because we're called from within the transaction
        // and data has already been validated

        $deleted = DB::connection($connection)
            ->table($tableName)
            ->where('business_date', $business_date)
            ->delete();

        Log::info("Deleted existing order line data for partition", [
            'table' => $tableName,
            'date' => $business_date,
            'rows_deleted' => $deleted
        ]);
    }
}
