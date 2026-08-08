<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How a trend series is bucketed.
 *
 * A chart of daily takings is unreadable over three years and uninformative
 * over three days, so the bucket follows the length of the period rather than
 * being chosen by hand — see `ReportPeriod::interval()`.
 */
enum ReportInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Daily',
            self::Week => 'Weekly',
            self::Month => 'Monthly',
        };
    }

    /**
     * The first day of the bucket this date falls in, as `Y-m-d`.
     *
     * Bucketing happens in PHP rather than in SQL: date functions differ by
     * driver, and the alternative is one query shape per database.
     */
    public function bucket(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Day => $date->toDateString(),
            self::Week => $date->startOfWeek()->toDateString(),
            self::Month => $date->startOfMonth()->toDateString(),
        };
    }

    /**
     * Step to the bucket after this one, so a series can include the periods
     * where nothing happened. A gap in a trend chart reads as missing data;
     * a zero reads as a quiet week, which is what it was.
     */
    public function next(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this) {
            self::Day => $bucketStart->addDay(),
            self::Week => $bucketStart->addWeek(),
            self::Month => $bucketStart->addMonthNoOverflow(),
        };
    }
}
