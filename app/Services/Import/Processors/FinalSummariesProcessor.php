<?php

namespace App\Services\Import\Processors;

class FinalSummariesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'final_summaries';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'total_sales',
            'modified_order_qty',
            'refunded_order_qty',
            'customer_count',
            'phone_sales',
            'call_center_sales',
            'drive_thru_sales',
            'website_sales',
            'mobile_sales',
            'doordash_sales',
            'grubhub_sales',
            'ubereats_sales',
            'delivery_sales',
            'digital_sales_percent',
            'portal_transactions',
            'put_into_portal',
            'portal_used_percent',
            'put_in_portal_on_time',
            'in_portal_on_time_percent',
            'delivery_tips',
            'prepaid_delivery_tips',
            'in_store_tip_amount',
            'prepaid_in_store_tip_amount',
            'total_tips',
            'over_short',
            'cash_sales',
            'total_waste_cost',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) && !empty($row['business_date']);
    }
}
