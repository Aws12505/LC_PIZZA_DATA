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
        return ['franchisestore', 'businessdate', 'createdatetime', 'till', 'checktype'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'createdatetime',
            'verifieddatetime',
            'till',
            'checktype',
            'systemtotals',
            'verified',
            'variance',
            'createdby',
            'verifiedby',
        ];
    }

    protected function transformData(array $row): array
    {
        $row['createdatetime'] = $this->parseDateTime($row['createdatetime'] ?? null);
        $row['verifieddatetime'] = $this->parseDateTime($row['verifieddatetime'] ?? null);
        $row['systemtotals'] = $this->toNumeric($row['systemtotals'] ?? null);
        $row['verified'] = $this->toNumeric($row['verified'] ?? null);
        $row['variance'] = $this->toNumeric($row['variance'] ?? null);

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['createdatetime']) 
            && !empty($row['till']) 
            && !empty($row['checktype']);
    }
}
