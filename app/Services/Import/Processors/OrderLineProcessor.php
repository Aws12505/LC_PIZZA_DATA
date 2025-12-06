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

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'orderid' => 'order_id',
            'itemid' => 'item_id',
            'datetimeplaced' => 'date_time_placed',
            'datetimefulfilled' => 'date_time_fulfilled',
            'menuitemname' => 'menu_item_name',
            'menuitemaccount' => 'menu_item_account',
            'bundlename' => 'bundle_name',
            'netamount' => 'net_amount',
            'quantity' => 'quantity',
            'royaltyitem' => 'royalty_item',
            'taxableitem' => 'taxable_item',
            'taxincludedamount' => 'tax_included_amount',
            'employee' => 'employee',
            'overrideapprovalemployee' => 'override_approval_employee',
            'orderplacedmethod' => 'order_placed_method',
            'orderfulfilledmethod' => 'order_fulfilled_method',
            'modifiedorderamount' => 'modified_order_amount',
            'modificationreason' => 'modification_reason',
            'paymentmethods' => 'payment_methods',
            'refunded' => 'refunded',
        ]);
    }

    protected function transformData(array $row): array
    {
        $row['date_time_placed'] = $this->parseDateTime($row['date_time_placed'] ?? null);
        $row['date_time_fulfilled'] = $this->parseDateTime($row['date_time_fulfilled'] ?? null);
        $row['net_amount'] = $this->toNumeric($row['net_amount'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);
        $row['tax_included_amount'] = $this->toNumeric($row['tax_included_amount'] ?? null);
        $row['modified_order_amount'] = $this->toNumeric($row['modified_order_amount'] ?? null);

        return $row;
    }

    protected function deleteExistingData(string $business_date, string $tableName, string $connection): void
    {
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
