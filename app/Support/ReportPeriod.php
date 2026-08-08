<?php

namespace App\Support;

use App\Enums\ReportInterval;
use App\Enums\ReportPreset;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * The window a report covers: two inclusive dates, and the arithmetic every
 * report does with them.
 *
 * `from` is the start of its day and `to` is the end of its, so a period can be
 * handed straight to a `whereBetween` over a timestamp without a sale made at
 * five o'clock falling out of its own day.
 *
 * Immutable and free of facades, so the averages and the comparison period can
 * be unit-tested without a database or a request.
 */
final readonly class ReportPeriod implements JsonSerializable, Stringable
{
    /**
     * The period reports fall back to when nothing is asked for.
     */
    public const ReportPreset DEFAULT_PRESET = ReportPreset::Last30Days;

    /**
     * The mean length of a month, 365.25/12 days, as an exact fraction over
     * 10000. Monthly averages scale by this rather than by "30" so a figure
     * taken over a quarter agrees with one taken over a year.
     */
    private const int DAYS_PER_MONTH_TIMES_TEN_THOUSAND = 304375;

    private const int DAYS_PER_WEEK = 7;

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?ReportPreset $preset,
    ) {}

    /**
     * Two dates, inclusive. Reversed input is put the right way round rather
     * than returning nothing — the user meant the range between them.
     */
    public static function between(
        DateTimeInterface|string $from,
        DateTimeInterface|string $to,
        ?ReportPreset $preset = null,
    ): self {
        $start = self::parse($from);
        $end = self::parse($to);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return new self($start->startOfDay(), $end->endOfDay(), $preset);
    }

    public static function preset(ReportPreset $preset): self
    {
        [$from, $to] = $preset->range();

        return new self($from->startOfDay(), $to->endOfDay(), $preset);
    }

    /**
     * Build from raw query-string values.
     *
     * Explicit dates win over a named preset: if the user has dragged a
     * calendar, that is what they want to see. A half-given range — one date
     * and not the other — becomes that single day rather than being thrown
     * away, because a partly-typed URL should still show something sensible.
     *
     * Nothing here throws. This reads a query string, which anyone can edit,
     * and a report answering for the default period beats a 500.
     */
    public static function fromInput(
        ?string $from = null,
        ?string $to = null,
        ?string $preset = null,
        ReportPreset $default = self::DEFAULT_PRESET,
    ): self {
        $start = self::tryParse($from);
        $end = self::tryParse($to);

        if ($start !== null || $end !== null) {
            return self::between($start ?? $end, $end ?? $start);
        }

        return self::preset(
            ($preset !== null ? ReportPreset::tryFrom($preset) : null) ?? $default
        );
    }

    /**
     * How many days the period covers, counting both ends.
     */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * The equivalent stretch immediately before this one, for comparisons.
     *
     * Same number of days, ending the day before this period starts. Matching
     * the length rather than the calendar keeps a comparison honest: February
     * against January is three days short, and would read as a slump.
     */
    public function previous(): self
    {
        $end = $this->from->subDay();

        return new self(
            $end->subDays($this->days() - 1)->startOfDay(),
            $end->endOfDay(),
            null,
        );
    }

    /**
     * The bucket size a trend over this period should use.
     */
    public function interval(): ReportInterval
    {
        $days = $this->days();

        return match (true) {
            $days <= 92 => ReportInterval::Day,
            $days <= 366 => ReportInterval::Week,
            default => ReportInterval::Month,
        };
    }

    /**
     * Every bucket in the period, in order, as `Y-m-d` bucket starts.
     *
     * Includes the buckets where nothing happened: a gap in a trend chart
     * reads as missing data, whereas a zero reads as a quiet week.
     *
     * @return list<string>
     */
    public function buckets(): array
    {
        $interval = $this->interval();
        $cursor = CarbonImmutable::parse($interval->bucket($this->from));
        $buckets = [];

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $buckets[] = $cursor->toDateString();
            $cursor = $interval->next($cursor);
        }

        return $buckets;
    }

    /**
     * Fold dated totals into this period's buckets.
     *
     * Takes what a `GROUP BY` over a date column returns — a map of date to
     * figure — and returns a map of bucket start to total, with every bucket
     * in the period present and the quiet ones zero.
     *
     * @param  iterable<string, int|float|string>  $totals  date => figure
     * @return array<string, int>
     */
    public function fold(iterable $totals): array
    {
        $interval = $this->interval();
        $folded = array_fill_keys($this->buckets(), 0);

        foreach ($totals as $date => $total) {
            $bucket = $interval->bucket(CarbonImmutable::parse($date));

            if (array_key_exists($bucket, $folded)) {
                $folded[$bucket] += (int) $total;
            }
        }

        return $folded;
    }

    public function contains(DateTimeInterface $date): bool
    {
        return CarbonImmutable::instance($date)->betweenIncluded($this->from, $this->to);
    }

    /**
     * The two ends as `Y-m-d`, for a `whereBetween` over a date column.
     *
     * @return array{string, string}
     */
    public function toDateStrings(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    /**
     * Spread a total evenly across the days in the period.
     */
    public function averagePerDay(Money $total): Money
    {
        return $total->multipliedByFraction(1, $this->days());
    }

    public function averagePerWeek(Money $total): Money
    {
        return $total->multipliedByFraction(self::DAYS_PER_WEEK, $this->days());
    }

    public function averagePerMonth(Money $total): Money
    {
        return $total->multipliedByFraction(
            self::DAYS_PER_MONTH_TIMES_TEN_THOUSAND,
            $this->days() * 10000,
        );
    }

    /**
     * Averages per day, week and month in one go — the shape a report screen
     * shows them in.
     *
     * @return array{per_day: Money, per_week: Money, per_month: Money}
     */
    public function averages(Money $total): array
    {
        return [
            'per_day' => $this->averagePerDay($total),
            'per_week' => $this->averagePerWeek($total),
            'per_month' => $this->averagePerMonth($total),
        ];
    }

    public function label(): string
    {
        if ($this->preset !== null) {
            return $this->preset->label();
        }

        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('j M Y');
        }

        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /**
     * @return array{from: string, to: string, preset: string|null, label: string, days: int, interval: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'preset' => $this->preset?->value,
            'label' => $this->label(),
            'days' => $this->days(),
            'interval' => $this->interval()->value,
        ];
    }

    public function __toString(): string
    {
        return $this->label();
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function parse(DateTimeInterface|string $date): CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date);
        }

        return self::tryParse($date)
            ?? throw new InvalidArgumentException("Cannot read [{$date}] as a date; expected Y-m-d.");
    }

    /**
     * Read a `Y-m-d` string, or null for anything else — including "2026-02-31",
     * which `createFromFormat` would otherwise roll forward into March.
     */
    private static function tryParse(?string $date): ?CarbonImmutable
    {
        $date = trim((string) $date);

        if ($date === '' || ! CarbonImmutable::hasFormat($date, 'Y-m-d')) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date);

        return $parsed instanceof CarbonImmutable && $parsed->toDateString() === $date
            ? $parsed
            : null;
    }
}
