<?php

namespace App\Services\Import\Processors;

class ThirdPartyMarketplaceOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'third_party_marketplace_orders';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'business_date',
            'franchise_store',
            'doordash_product_costs_marketplace',
            'doordash_tax_Marketplace',
            'doordash_order_total_Marketplace',
            'ubereats_product_costs_Marketplace',
            'ubereats_tax_Marketplace',
            'uberEats_order_total_Marketplace',
            'grubhub_product_costs_Marketplace',
            'grubhub_tax_Marketplace',
            'grubhub_order_total_Marketplace',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) && !empty($row['business_date']);
    }
}
