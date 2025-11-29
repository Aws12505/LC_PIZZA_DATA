<?php

namespace App\Services\Import\Processors;

class ChannelDataProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'channel_data';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'category', 'sub_category', 
                'order_placed_method', 'order_fulfilled_method'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'category', 'sub_category',
            'order_placed_method', 'order_fulfilled_method', 'amount',
        ];
    }

    protected function deleteExistingData(string $businessDate, string $tableName, string $connection): void
    {
        // Channel data uses composite key, delete by date only
        $deleted = \Illuminate\Support\Facades\DB::connection($connection)
            ->table($tableName)
            ->where('business_date', $businessDate)
            ->delete();

        \Illuminate\Support\Facades\Log::info("Deleted existing channel data", [
            'table' => $tableName,
            'date' => $businessDate,
            'rows_deleted' => $deleted
        ]);
    }
}
