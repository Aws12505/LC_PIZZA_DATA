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
        return ['franchisestore', 'businessdate'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'businessdate',
            'franchisestore',
            'doordashproductcostsMarketplace',
            'doordashtaxMarketplace',
            'doordashordertotalMarketplace',
            'ubereatsproductcostsMarketplace',
            'ubereatstaxMarketplace',
            'uberEatsordertotalMarketplace',
            'grubhubproductcostsMarketplace',
            'grubhubtaxMarketplace',
            'grubhubordertotalMarketplace',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
