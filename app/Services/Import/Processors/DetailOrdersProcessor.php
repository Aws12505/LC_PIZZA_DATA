<?php

namespace App\Services\Import\Processors;

class DetailOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'detail_orders';
    }

    protected function getImportStrategy(): string
    {
        return self::STRATEGY_UPSERT;
    }

    protected function getUniqueKeys(): array
    {
        // FIXED: Added transactiontype back (was missing in new code)
        return ['franchisestore', 'businessdate', 'orderid', 'transactiontype'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'orderid',
            'datetimeplaced',
            'datetimefulfilled',
            'royaltyobligation',
            'quantity',
            'customercount',
            'taxableamount',
            'nontaxableamount',
            'taxexemptamount',
            'nonroyaltyamount',
            'salestax',
            'grosssales',
            'occupationaltax',
            'employee',
            'overrideapprovalemployee',
            'orderplacedmethod',
            'orderfulfilledmethod',
            'deliverytip',
            'deliverytiptax',
            'deliveryfee',
            'deliveryfeetax',
            'deliveryservicefee',
            'deliveryservicefeetax',
            'deliverysmallorderfee',
            'deliverysmallorderfeetax',
            'modifiedorderamount',
            'modificationreason',
            'refunded',
            'paymentmethods',
            'transactiontype',
            'storetipamount',
            'promisedate',
            'taxexemptionid',
            'taxexemptionentityname',
            'userid',
            'hnrOrder',
            'brokenpromise',
            'portaleligible',
            'portalused',
            'putintoportalbeforepromisetime',
            'portalcompartmentsused',
            'timeloadedintoportal',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['datetimeplaced'] = $this->parseDateTime($row['datetimeplaced'] ?? null);
        $row['datetimefulfilled'] = $this->parseDateTime($row['datetimefulfilled'] ?? null);
        $row['promisedate'] = $this->parseDateTime($row['promisedate'] ?? null);
        $row['timeloadedintoportal'] = $this->parseDateTime($row['timeloadedintoportal'] ?? null);

        // Parse numeric fields
        foreach (['royaltyobligation', 'quantity', 'customercount', 'grosssales'] as $field) {
            $row[$field] = $this->toNumeric($row[$field] ?? null);
        }

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['orderid']);
    }
}
