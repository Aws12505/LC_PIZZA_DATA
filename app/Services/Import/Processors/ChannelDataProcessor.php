<?php

namespace App\Services\Import\Processors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChannelDataProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'channel_data';
    }

    protected function getUniqueKeys(): array
    {
        return [
            'franchise_store',
            'business_date',
            'category',
            'sub_category',
            'order_placed_method',
            'order_fulfilled_method'
        ];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'category',
            'sub_category',
            'order_placed_method',
            'order_fulfilled_method',
            'amount',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'category' => 'category',
            'subcategory' => 'sub_category',
            'orderplacedmethod' => 'order_placed_method',
            'orderfulfilledmethod' => 'order_fulfilled_method',
            'amount' => 'amount',
        ]);
    }

    protected function deleteExistingData(string $business_date, string $tableName, string $connection): void
    {
        $deleted = DB::connection($connection)
            ->table($tableName)
            ->where('business_date', $business_date)
            ->delete();

        Log::info("Deleted existing channel data", [
            'table' => $tableName,
            'date' => $business_date,
            'rows_deleted' => $deleted
        ]);
    }
}
