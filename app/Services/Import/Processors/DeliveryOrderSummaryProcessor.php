<?php

namespace App\Services\Import\Processors;

class DeliveryOrderSummaryProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'deliveryordersummary';
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
            'orderscount',
            'productcost',
            'tax',
            'occupationaltax',
            'deliverycharges',
            'deliverychargestaxes',
            'servicecharges',
            'servicechargestaxes',
            'smallordercharge',
            'smallorderchargetaxes',
            'deliverylatecharge',
            'tip',
            'tiptax',
            'totaltaxes',
            'ordertotal',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
