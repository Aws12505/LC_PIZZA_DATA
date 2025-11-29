<?php

namespace App\Services\Import\Processors;

class CashManagementProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'cash_management';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'create_datetime', 'till', 'check_type'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'create_datetime', 'verified_datetime',
            'till', 'check_type', 'system_totals', 'verified', 'variance',
            'created_by', 'verified_by',
        ];
    }

    protected function transformData(array $row): array
    {
        $row['create_datetime'] = $this->parseDateTime($row['create_datetime'] ?? null);
        $row['verified_datetime'] = $this->parseDateTime($row['verified_datetime'] ?? null);
        $row['system_totals'] = $this->toNumeric($row['system_totals'] ?? null);
        $row['verified'] = $this->toNumeric($row['verified'] ?? null);
        $row['variance'] = $this->toNumeric($row['variance'] ?? null);

        return $row;
    }
}
