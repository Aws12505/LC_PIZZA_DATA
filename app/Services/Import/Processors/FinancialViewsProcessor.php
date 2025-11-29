<?php

namespace App\Services\Import\Processors;

class FinancialViewsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'financial_views';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'sub_account', 'area'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'area', 'sub_account', 'amount',
        ];
    }
}
