<?php

namespace App\Services\Import\Processors;

class SummaryTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summary_transactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'payment_method', 'sub_payment_method'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'payment_method', 'sub_payment_method',
            'total_amount', 'saf_qty', 'saf_total',
        ];
    }
}
