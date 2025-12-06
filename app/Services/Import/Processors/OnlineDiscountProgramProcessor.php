<?php

namespace App\Services\Import\Processors;

class OnlineDiscountProgramProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'online_discount_program';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'order_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'order_id',
            'business_date',
            'pay_type',
            'original_subtotal',
            'modified_subtotal',
            'promo_code',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) 
            && !empty($row['business_date']) 
            && !empty($row['order_id']);
    }
}
