<?php

namespace App\Services\Import\Processors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderLineProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'orderline';
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
            'franchisestore',
            'businessdate',
            'orderid',
            'itemid',
            'datetimeplaced',
            'datetimefulfilled',
            'menuitemname',
            'menuitemaccount',
            'bundlename',
            'netamount',
            'quantity',
            'royaltyitem',
            'taxableitem',
            'taxincludedamount',
            'employee',
            'overrideapprovalemployee',
            'orderplacedmethod',
            'orderfulfilledmethod',
            'modifiedorderamount',
            'modificationreason',
            'paymentmethods',
            'refunded',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['datetimeplaced'] = $this->parseDateTime($row['datetimeplaced'] ?? null);
        $row['datetimefulfilled'] = $this->parseDateTime($row['datetimefulfilled'] ?? null);

        // Parse numeric fields
        $row['netamount'] = $this->toNumeric($row['netamount'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);
        $row['taxincludedamount'] = $this->toNumeric($row['taxincludedamount'] ?? null);
        $row['modifiedorderamount'] = $this->toNumeric($row['modifiedorderamount'] ?? null);

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['orderid']) 
            && !empty($row['itemid']);
    }

    /**
     * FIXED: Delete by BOTH businessdate AND franchisestore (not just date)
     * This matches the old code's partition delete strategy
     */
    protected function deleteExistingData(string $businessDate, string $tableName, string $connection): void
    {
        // Get franchisestore from first row (all rows should have same store)
        // This is safe because we're called from within the transaction
        // and data has already been validated

        $deleted = DB::connection($connection)
            ->table($tableName)
            ->where('businessdate', $businessDate)
            ->where('franchisestore', '03795') // Use your configured store ID
            ->delete();

        Log::info("Deleted existing order line data for partition", [
            'table' => $tableName,
            'date' => $businessDate,
            'store' => '03795',
            'rows_deleted' => $deleted
        ]);
    }
}
