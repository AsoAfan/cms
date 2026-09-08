<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The reporting ranges a business actually asks for.
 *
 * These mirror the presets in the frontend `DateRangePicker` exactly, so
 * "last month" means the same thing whichever end of the wire names it.
 */
enum ReportPreset: string
{
    case Today = 'today';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Last7Days => 'Last 7 days',
            self::Last30Days => 'Last 30 days',
            self::ThisMonth => 'This month',
            self::LastMonth => 'Last month',
            self::ThisYear => 'This year',
            self::LastYear => 'Last year',
        };
    }

    /**
     * The first and last day this preset covers, relative to today.
     *
     * Ranges are inclusive of both ends and never run past today: a report of
     * "this month" on the 8th covers eight days, not a month with three weeks
     * of zeroes tacked on that drag every average down.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function range(): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return match ($this) {
            self::Today => [$today, $today],
            self::Last7Days => [$today->subDays(6), $today],
            self::Last30Days => [$today->subDays(29), $today],
            self::ThisMonth => [$today->startOfMonth(), $today],
            self::LastMonth => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            self::ThisYear => [$today->startOfYear(), $today],
            self::LastYear => [
                $today->subYear()->startOfYear(),
                $today->subYear()->endOfYear()->startOfDay(),
            ],
        };
    }
}
