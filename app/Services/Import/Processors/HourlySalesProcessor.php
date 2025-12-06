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

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'hour' => 'hour',
            'totalsales' => 'total_sales',
            'phonesales' => 'phone_sales',
            'callcentersales' => 'call_center_sales',
            'drivethrusalse' => 'drive_thru_sales',
            'websitesales' => 'website_sales',
            'mobilesales' => 'mobile_sales',
            'websitesalesdelivery' => 'website_sales_delivery',
            'mobilesalesdelivery' => 'mobile_sales_delivery',
            'doordashsales' => 'doordash_sales',
            'ubereatsales' => 'ubereats_sales',
            'grubhubsales' => 'grubhub_sales',
            'ordercount' => 'order_count',
        ]);
    }
}
