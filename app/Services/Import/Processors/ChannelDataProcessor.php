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
            'franchisestore',
            'businessdate',
            'category',
            'subcategory',
            'orderplacedmethod',
            'orderfulfilledmethod'
        ];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'category',
            'subcategory',
            'orderplacedmethod',
            'orderfulfilledmethod',
            'amount',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }

    /**
     * Channel data uses composite key, delete by date only
     */
    protected function deleteExistingData(string $businessDate, string $tableName, string $connection): void
    {
        $deleted = DB::connection($connection)
            ->table($tableName)
            ->where('businessdate', $businessDate)
            ->delete();

        Log::info("Deleted existing channel data", [
            'table' => $tableName,
            'date' => $businessDate,
            'rows_deleted' => $deleted
        ]);
    }
}
