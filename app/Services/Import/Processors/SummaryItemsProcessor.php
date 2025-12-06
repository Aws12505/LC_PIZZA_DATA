<?php

namespace App\Services\Import\Processors;

class SummaryItemsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summaryitems';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'menuitemname', 'itemid'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'menuitemname',
            'menuitemaccount',
            'itemid',
            'itemquantity',
            'royaltyobligation',
            'taxableamount',
            'nontaxableamount',
            'taxexemptamount',
            'nonroyaltyamount',
            'taxincludedamount',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['menuitemname']) 
            && !empty($row['itemid']);
    }
}
