<?php

namespace App\Services\DataEntry;

use App\Models\EnteredKey;
use App\Models\EnteredKeyValue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DueKeyResolverService
{
    public function __construct(
        private readonly ScheduleEvaluationService $schedule,
    ) {}

    /**
     * Return list of keys needed for store on date with filled status.
     */
    public function dueForStoreOnDate(string $storeId, Carbon $date): Collection
    {
        $date = $date->copy()->startOfDay();

        // Load active keys AND the rule for that store (if none => not needed)
        $keys = EnteredKey::query()
            ->where('is_active', true)
            ->with(['storeRules' => fn($q) => $q->where('store_id', $storeId)])
            ->get();

        // Values on exact date (for daily/weekly/fixed monthly/yearly)
        $valuesToday = EnteredKeyValue::query()
            ->where('store_id', $storeId)
            ->whereDate('entry_date', $date)
            ->get()
            ->keyBy('key_id');

        // For monthly-any-day we also need month-range values
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $valuesThisMonth = EnteredKeyValue::query()
            ->where('store_id', $storeId)
            ->whereBetween('entry_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->groupBy('key_id');

        $out = collect();

        foreach ($keys as $key) {
            $rule = $key->storeRules->first(); // single rule per store/key (unique)
            if (!$rule) continue; // not needed for this store

            // monthly any-day: due if rule applies this month and not yet filled this month
            if ($this->schedule->isMonthlyAnyDayRule($rule)) {
                if (!$this->schedule->monthlyIsApplicableThisMonth($rule, $date)) continue;

                $filledThisMonth = $valuesThisMonth->has($key->id);
                $out->push([
                    'key_id' => $key->id,
                    'label' => $key->label,
                    'data_type' => $key->data_type,
                    'frequency_type' => $rule->frequency_type,
                    'interval' => (int)$rule->interval,
                    'mode' => 'monthly_any_day',
                    'filled' => $filledThisMonth,
                    'value' => $filledThisMonth ? $valuesThisMonth[$key->id]->sortByDesc('entry_date')->first() : null,
                ]);
                continue;
            }

            // normal: due specifically on date
            $isDueToday = $this->schedule->isDueOnDate($rule, $date);
            if (!$isDueToday) continue;

            $filledToday = $valuesToday->has($key->id);

            $out->push([
                'key_id' => $key->id,
                'label' => $key->label,
                'data_type' => $key->data_type,
                'frequency_type' => $rule->frequency_type,
                'interval' => (int)$rule->interval,
                'mode' => 'date_specific',
                'filled' => $filledToday,
                'value' => $filledToday ? $valuesToday[$key->id] : null,
            ]);
        }

        return $out->values();
    }

    /**
     * Due range for dashboards: returns date => items
     */
    public function dueRange(string $storeId, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $result = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $result[$cursor->toDateString()] = $this->dueForStoreOnDate($storeId, $cursor)->all();
            $cursor->addDay();
        }

        return $result;
    }
}
