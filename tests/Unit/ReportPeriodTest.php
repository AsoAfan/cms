<?php

use App\Enums\ReportPreset;
use App\Support\Money;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-03-15 10:30:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Ends
|--------------------------------------------------------------------------
*/

it('runs from the start of the first day to the end of the last', function () {
    $period = ReportPeriod::between('2026-02-01', '2026-02-28');

    expect($period->from->toDateTimeString())->toBe('2026-02-01 00:00:00')
        ->and($period->to->toDateTimeString())->toBe('2026-02-28 23:59:59')
        ->and($period->days())->toBe(28);
});

it('counts a single day as one day, not none', function () {
    expect(ReportPeriod::between('2026-02-10', '2026-02-10')->days())->toBe(1);
});

it('puts a backwards range the right way round', function () {
    $period = ReportPeriod::between('2026-02-28', '2026-02-01');

    expect($period->toDateStrings())->toBe(['2026-02-01', '2026-02-28']);
});

/*
|--------------------------------------------------------------------------
| Presets
|--------------------------------------------------------------------------
*/

it('never runs a preset past today', function () {
    // The 15th of the month: "this month" is fifteen days, not thirty-one with
    // a fortnight of zeroes dragging every average down.
    $period = ReportPeriod::preset(ReportPreset::ThisMonth);

    expect($period->toDateStrings())->toBe(['2026-03-01', '2026-03-15'])
        ->and($period->days())->toBe(15);
});

it('resolves each preset to the range its label promises', function (ReportPreset $preset, string $from, string $to) {
    expect(ReportPeriod::preset($preset)->toDateStrings())->toBe([$from, $to]);
})->with([
    'today' => [ReportPreset::Today, '2026-03-15', '2026-03-15'],
    'last 7 days' => [ReportPreset::Last7Days, '2026-03-09', '2026-03-15'],
    'last 30 days' => [ReportPreset::Last30Days, '2026-02-14', '2026-03-15'],
    'last month' => [ReportPreset::LastMonth, '2026-02-01', '2026-02-28'],
    'this year' => [ReportPreset::ThisYear, '2026-01-01', '2026-03-15'],
    'last year' => [ReportPreset::LastYear, '2025-01-01', '2025-12-31'],
]);

it('counts both ends of a preset, so last 7 days is 7', function () {
    expect(ReportPeriod::preset(ReportPreset::Last7Days)->days())->toBe(7)
        ->and(ReportPeriod::preset(ReportPreset::Last30Days)->days())->toBe(30)
        ->and(ReportPeriod::preset(ReportPreset::Today)->days())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Reading a query string
|--------------------------------------------------------------------------
*/

it('prefers explicit dates over a named preset', function () {
    $period = ReportPeriod::fromInput('2026-01-01', '2026-01-31', 'last_7_days');

    expect($period->toDateStrings())->toBe(['2026-01-01', '2026-01-31'])
        ->and($period->preset)->toBeNull();
});

it('falls back to the default period rather than failing', function (?string $from, ?string $to, ?string $preset) {
    expect(ReportPeriod::fromInput($from, $to, $preset)->preset)
        ->toBe(ReportPeriod::DEFAULT_PRESET);
})->with([
    'nothing given' => [null, null, null],
    'blank strings' => ['', '  ', ''],
    'not a date' => ['banana', null, null],
    'a date that does not exist' => ['2026-02-31', null, null],
    'an unknown preset' => [null, null, 'since_the_dawn_of_time'],
]);

it('reads a half-given range as that single day', function () {
    expect(ReportPeriod::fromInput('2026-02-10', null)->toDateStrings())
        ->toBe(['2026-02-10', '2026-02-10']);
});

/*
|--------------------------------------------------------------------------
| Comparison period
|--------------------------------------------------------------------------
*/

it('compares against the same length immediately before', function () {
    // February against the 28 days before it, not against January. January is
    // three days longer, and the comparison would read as a slump.
    $previous = ReportPeriod::between('2026-02-01', '2026-02-28')->previous();

    expect($previous->toDateStrings())->toBe(['2026-01-04', '2026-01-31'])
        ->and($previous->days())->toBe(28);
});

it('gives a previous period for a single day too', function () {
    $previous = ReportPeriod::between('2026-03-15', '2026-03-15')->previous();

    expect($previous->toDateStrings())->toBe(['2026-03-14', '2026-03-14']);
});

/*
|--------------------------------------------------------------------------
| Averages
|--------------------------------------------------------------------------
*/

it('averages a total across the days it covers', function () {
    $period = ReportPeriod::between('2026-02-01', '2026-02-28');
    $averages = $period->averages(Money::fromDecimal('2800.00'));

    expect($averages['per_day']->toDecimal())->toBe('100.00')
        ->and($averages['per_week']->toDecimal())->toBe('700.00')
        // A month is the mean 365.25/12 = 30.4375 days, so a rate of 100 a day
        // is 3043.75 a month whatever length of period it was measured over.
        ->and($averages['per_month']->toDecimal())->toBe('3043.75');
});

it('measures the same daily rate the same way over any period', function () {
    $short = ReportPeriod::between('2026-02-01', '2026-02-07');
    $long = ReportPeriod::between('2026-01-01', '2026-03-31');

    expect($short->averagePerMonth(Money::fromDecimal('700.00'))->toDecimal())
        ->toBe($long->averagePerMonth(Money::fromDecimal('9000.00'))->toDecimal())
        ->toBe('3043.75');
});

it('rounds an average to the cent rather than dropping the fraction', function () {
    // 1000.00 over 3 days is 333.333 a day.
    $period = ReportPeriod::between('2026-02-01', '2026-02-03');

    expect($period->averagePerDay(Money::fromDecimal('1000.00'))->toDecimal())->toBe('333.33');
});

/*
|--------------------------------------------------------------------------
| Serialization
|--------------------------------------------------------------------------
*/

it('serializes what a report screen needs to render its filter', function () {
    expect(ReportPeriod::preset(ReportPreset::LastMonth)->jsonSerialize())->toBe([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
        'preset' => 'last_month',
        'label' => 'Last month',
        'days' => 28,
    ]);
});

it('labels a custom range by its dates', function () {
    expect((string) ReportPeriod::between('2026-02-01', '2026-02-28'))
        ->toBe('1 Feb 2026 – 28 Feb 2026')
        ->and((string) ReportPeriod::between('2026-02-10', '2026-02-10'))
        ->toBe('10 Feb 2026');
});
