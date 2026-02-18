<?php

namespace App\Services\DataEntry;

use App\Models\KeyStoreRule;
use Carbon\Carbon;

class ScheduleEvaluationService
{
    public function __construct()
    {
        Carbon::setWeekStartsAt(Carbon::TUESDAY);
        Carbon::setWeekEndsAt(Carbon::MONDAY);
    }

    public function inWindow(KeyStoreRule $rule, Carbon $date): bool
    {
        $d = $date->copy()->startOfDay();

        if ($d->lt($rule->starts_at->copy()->startOfDay())) return false;
        if ($rule->ends_at && $d->gt($rule->ends_at->copy()->startOfDay())) return false;

        return true;
    }

    public function isDueOnDate(KeyStoreRule $rule, Carbon $date): bool
    {
        if (!$this->inWindow($rule, $date)) return false;

        return match ($rule->frequency_type) {
            'daily' => $this->dailyDue($rule, $date),
            'weekly' => $this->weeklyDue($rule, $date),
            'monthly' => $this->monthlyDueFixedOrNth($rule, $date), // due "today" only for fixed/nth
            'yearly' => $this->yearlyDue($rule, $date),
            default => false,
        };
    }

    /**
     * For "monthly any day" rules, being "due" is "the rule applies in this month",
     * and "missing" is determined by checking whether a value exists in that month.
     * (Handled in Due resolver service.)
     */
    public function isMonthlyAnyDayRule(KeyStoreRule $rule): bool
    {
        return $rule->frequency_type === 'monthly'
            && is_null($rule->month_day)
            && (is_null($rule->week_of_month) || is_null($rule->week_day));
    }

    public function monthlyIsApplicableThisMonth(KeyStoreRule $rule, Carbon $date): bool
    {
        if ($rule->frequency_type !== 'monthly') return false;
        if (!$this->inWindow($rule, $date)) return false;

        $startMonth = $rule->starts_at->copy()->startOfMonth();
        $curMonth = $date->copy()->startOfMonth();
        $months = $startMonth->diffInMonths($curMonth);

        return ($months % max(1, (int)$rule->interval)) === 0;
    }

    private function dailyDue(KeyStoreRule $rule, Carbon $date): bool
    {
        $diffDays = $rule->starts_at->copy()->startOfDay()->diffInDays($date->copy()->startOfDay());
        return ($diffDays % max(1, (int)$rule->interval)) === 0;
    }

    private function weeklyDue(KeyStoreRule $rule, Carbon $date): bool
    {
        $weekDays = $rule->week_days ?? [];
        if (empty($weekDays)) return false;

        $isoDow = (int)$date->dayOfWeekIso; // 1..7
        if (!in_array($isoDow, $weekDays, true)) return false;

        // interval weeks measured using YOUR week (Tue..Mon)
        $startWeek = $rule->starts_at->copy()->startOfWeek(Carbon::TUESDAY);
        $curWeek = $date->copy()->startOfWeek(Carbon::TUESDAY);
        $weeks = $startWeek->diffInWeeks($curWeek);

        return ($weeks % max(1, (int)$rule->interval)) === 0;
    }

    private function monthlyDueFixedOrNth(KeyStoreRule $rule, Carbon $date): bool
    {
        // interval months
        $startMonth = $rule->starts_at->copy()->startOfMonth();
        $curMonth = $date->copy()->startOfMonth();
        $months = $startMonth->diffInMonths($curMonth);
        if (($months % max(1, (int)$rule->interval)) !== 0) return false;

        // fixed day
        if (!is_null($rule->month_day)) {
            return (int)$date->day === (int)$rule->month_day;
        }

        // nth weekday
        if (!is_null($rule->week_of_month) && !is_null($rule->week_day)) {
            $targetWeekday = (int)$rule->week_day;       // 1..7
            $nth = (int)$rule->week_of_month;            // 1..4 or -1

            if ($nth === -1) {
                $last = $date->copy()->endOfMonth();
                while ((int)$last->dayOfWeekIso !== $targetWeekday) $last->subDay();
                return $date->isSameDay($last);
            }

            if ($nth < 1 || $nth > 4) return false;

            $first = $date->copy()->startOfMonth();
            while ((int)$first->dayOfWeekIso !== $targetWeekday) $first->addDay();

            $target = $first->copy()->addWeeks($nth - 1);
            return $date->isSameDay($target);
        }

        // monthly any-day doesn't have a specific "due today" day
        return false;
    }

    private function yearlyDue(KeyStoreRule $rule, Carbon $date): bool
    {
        if (is_null($rule->year_month) || is_null($rule->month_day)) return false;

        $years = $rule->starts_at->copy()->startOfYear()->diffInYears($date->copy()->startOfYear());
        if (($years % max(1, (int)$rule->interval)) !== 0) return false;

        return ((int)$date->month === (int)$rule->year_month)
            && ((int)$date->day === (int)$rule->month_day);
    }
}
