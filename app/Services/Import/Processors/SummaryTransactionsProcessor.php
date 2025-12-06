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
            'franchise_store',
            'business_date',
            'payment_method',
            'sub_payment_method',
            'total_amount',
            'saf_qty',
            'saf_total',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'paymentmethod' => 'payment_method',
            'subpaymentmethod' => 'sub_payment_method',
            'totalamount' => 'total_amount',
            'safqty' => 'saf_qty',
            'saftotal' => 'saf_total',
        ]);
    }
}
