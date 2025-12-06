<?php

namespace App\Services\Import\Processors;

class HourlySalesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'hourly_sales';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'hour'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'hour',
            'total_sales',
            'phone_sales',
            'call_center_sales',
            'drive_thru_sales',
            'website_sales',
            'mobile_sales',
            'website_sales_delivery',
            'mobile_sales_delivery',
            'doordash_sales',
            'ubereats_sales',
            'grubhub_sales',
            'order_count',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) 
            && !empty($row['business_date']) 
            && !empty($row['hour']);
    }
}
