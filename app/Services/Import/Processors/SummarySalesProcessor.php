<?php

namespace App\Services\Import\Processors;

class SummarySalesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summary_sales';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'royaltyobligation',
            'customercount',
            'taxableamount',
            'nontaxableamount',
            'taxexemptamount',
            'nonroyaltyamount',
            'refundamount',
            'salestax',
            'grosssales',
            'occupationaltax',
            'deliverytip',
            'deliveryfee',
            'deliveryservicefee',
            'deliverysmallorderfee',
            'modifiedorderamount',
            'storetipamount',
            'prepaidcashorders',
            'prepaidnoncashorders',
            'prepaidsales',
            'prepaiddeliverytip',
            'prepaidinstoretipamount',
            'overshort',
            'previousdayrefunds',
            'saf',
            'managernotes',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse all numeric fields
        $numericFields = [
            'royaltyobligation', 'customercount', 'taxableamount', 'nontaxableamount',
            'taxexemptamount', 'nonroyaltyamount', 'refundamount', 'salestax',
            'grosssales', 'occupationaltax', 'deliverytip', 'deliveryfee',
            'deliveryservicefee', 'deliverysmallorderfee', 'modifiedorderamount',
            'storetipamount', 'prepaidcashorders', 'prepaidnoncashorders',
            'prepaidsales', 'prepaiddeliverytip', 'prepaidinstoretipamount',
            'overshort', 'previousdayrefunds', 'saf'
        ];

        foreach ($numericFields as $field) {
            if (isset($row[$field])) {
                $row[$field] = $this->toNumeric($row[$field]);
            }
        }

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
