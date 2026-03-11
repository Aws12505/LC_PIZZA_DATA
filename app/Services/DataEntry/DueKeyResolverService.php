<?php

namespace App\Services\DataEntry;

use App\Models\EnteredKey;
use App\Models\EnteredKeyValue;
use App\Models\UserStoreRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DueKeyResolverService
{
    public function __construct(
        private readonly ScheduleEvaluationService $schedule,
    ) {
    }

    /**
     * Return list of keys needed for store on date with filled status.
     */
    public function dueForStoreOnDate(string $storeId, Carbon $date): Collection
    {
        $date = $date->copy()->startOfDay();

        $keys = EnteredKey::query()
            ->where('is_active', true)
            ->with(['storeRules' => fn($q) => $q->where('store_id', $storeId)])
            ->get();

        $valuesToday = EnteredKeyValue::query()
            ->where('store_id', $storeId)
            ->whereDate('entry_date', $date)
            ->get();

        $valuesByKey = $valuesToday->groupBy('key_id');

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $valuesThisMonth = EnteredKeyValue::query()
            ->where('store_id', $storeId)
            ->whereBetween('entry_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->groupBy('key_id');

        $out = collect();

        foreach ($keys as $key) {

            $rule = $key->storeRules->first();
            if (!$rule)
                continue;

            /*
            |--------------------------------------------------------------------------
            | Monthly Any Day Mode
            |--------------------------------------------------------------------------
            */

            if ($this->schedule->isMonthlyAnyDayRule($rule)) {

                if (!$this->schedule->monthlyIsApplicableThisMonth($rule, $date)) {
                    continue;
                }

                $existingValues = $valuesThisMonth->get($key->id, collect());

                if ($rule->fill_mode === 'store_once') {

                    $filled = $existingValues->isNotEmpty();

                    $out->push([
                        'key_id' => $key->id,
                        'label' => $key->label,
                        'data_type' => $key->data_type,
                        'frequency_type' => $rule->frequency_type,
                        'interval' => (int) $rule->interval,
                        'mode' => 'monthly_any_day',
                        'fill_mode' => $rule->fill_mode,
                        'filled' => $filled,
                        'value' => $filled ? $existingValues->sortByDesc('entry_date')->first() : null,
                    ]);

                } else {

                    $roles = $rule->role_names ?? [];

                    $users = UserStoreRole::query()
                        ->where('store_id', $storeId)
                        ->where('active', true)
                        ->whereIn('role_name', $roles)
                        ->get(['user_id', 'role_name']);

                    foreach ($users as $userRole) {

                        $value = $existingValues
                            ->where('user_id', $userRole->user_id)
                            ->sortByDesc('entry_date')
                            ->first();

                        $user = \App\Models\User::find($userRole->user_id);

                        $out->push([
                            'key_id' => $key->id,
                            'label' => $key->label,
                            'data_type' => $key->data_type,

                            'frequency_type' => $rule->frequency_type,
                            'interval' => (int) $rule->interval,

                            'mode' => 'monthly_any_day',
                            'fill_mode' => $rule->fill_mode,

                            'user_id' => $userRole->user_id,
                            'user_name' => $user?->name,
                            'role_name' => $userRole->role_name,

                            'filled' => $value !== null,
                            'value' => $value,
                        ]);
                    }

                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Normal Scheduled Rules
            |--------------------------------------------------------------------------
            */

            if (!$this->schedule->isDueOnDate($rule, $date)) {
                continue;
            }

            $existingValues = $valuesByKey->get($key->id, collect());

            if ($rule->fill_mode === 'store_once') {

                $value = $existingValues->first();

                $out->push([
                    'key_id' => $key->id,
                    'label' => $key->label,
                    'data_type' => $key->data_type,
                    'frequency_type' => $rule->frequency_type,
                    'interval' => (int) $rule->interval,
                    'mode' => 'date_specific',
                    'fill_mode' => $rule->fill_mode,
                    'filled' => $value !== null,
                    'value' => $value,
                ]);

            } else {

                $roles = $rule->role_names ?? [];

                $users = UserStoreRole::query()
                    ->where('store_id', $storeId)
                    ->where('active', true)
                    ->whereIn('role_name', $roles)
                    ->get(['user_id', 'role_name']);

                foreach ($users as $userRole) {

                    $value = $existingValues
                        ->where('user_id', $userRole->user_id)
                        ->first();

                    $user = \App\Models\User::find($userRole->user_id);

                    $out->push([
                        'key_id' => $key->id,
                        'label' => $key->label,
                        'data_type' => $key->data_type,

                        'frequency_type' => $rule->frequency_type,
                        'interval' => (int) $rule->interval,

                        'mode' => 'date_specific',
                        'fill_mode' => $rule->fill_mode,

                        'user_id' => $userRole->user_id,
                        'user_name' => $user?->name,
                        'role_name' => $userRole->role_name,

                        'filled' => $value !== null,
                        'value' => $value,
                    ]);
                }
            }

        }

        return $out->values();
    }

    /**
     * Due range for dashboards
     */
    public function dueRange(string $storeId, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $result = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {

            $result[$cursor->toDateString()] = $this
                ->dueForStoreOnDate($storeId, $cursor)
                ->all();

            $cursor->addDay();
        }

        return $result;
    }
}