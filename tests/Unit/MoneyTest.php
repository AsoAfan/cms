<?php

use App\Support\Money;

it('builds from minor units', function () {
    expect(Money::fromMinorUnits(1234)->minorUnits)->toBe(1234)
        ->and(Money::zero()->minorUnits)->toBe(0);
});

it('parses decimal strings without touching a float', function (string|int $input, int $expected) {
    expect(Money::fromDecimal($input)->minorUnits)->toBe($expected);
})->with([
    'whole number' => ['1234', 123400],
    'two decimals' => ['1234.56', 123456],
    'one decimal' => ['12.5', 1250],
    'trailing dot' => ['12.', 1200],
    'zero' => ['0', 0],
    'zero with decimals' => ['0.00', 0],
    'sub-unit precision that floats round wrong' => ['0.29', 29],
    'negative' => ['-12.34', -1234],
    'explicit positive' => ['+12.34', 1234],
    'padded with insignificant zeros' => ['12.340', 1234],
    'integer input' => [7, 700],
    'surrounding whitespace' => ['  12.34  ', 1234],
]);

it('rejects input it cannot represent exactly', function (string $input) {
    Money::fromDecimal($input);
})->with([
    'more precision than a cent' => '12.345',
    'letters' => 'abc',
    'empty' => '',
    'thousands separator' => '1,234.56',
    'currency symbol' => '$12.34',
    'bare decimal point' => '.',
])->throws(InvalidArgumentException::class);

it('adds and subtracts', function () {
    $ten = Money::fromDecimal('10.00');
    $three = Money::fromDecimal('3.50');

    expect($ten->plus($three)->toDecimal())->toBe('13.50')
        ->and($ten->minus($three)->toDecimal())->toBe('6.50')
        ->and($three->minus($ten)->toDecimal())->toBe('-6.50');
});

it('sums a list, and sums nothing to zero', function () {
    expect(Money::sum(
        Money::fromDecimal('1.11'),
        Money::fromDecimal('2.22'),
        Money::fromDecimal('3.33'),
    )->toDecimal())->toBe('6.66')
        ->and(Money::sum()->isZero())->toBeTrue();
});

it('multiplies by a whole quantity exactly', function () {
    expect(Money::fromDecimal('3.45')->multipliedBy(7)->toDecimal())->toBe('24.15')
        ->and(Money::fromDecimal('3.45')->multipliedBy(0)->isZero())->toBeTrue()
        ->and(Money::fromDecimal('3.45')->multipliedBy(-2)->toDecimal())->toBe('-6.90');
});

it('scales by a fraction, rounding half away from zero', function (string $amount, int $numerator, int $denominator, string $expected) {
    expect(Money::fromDecimal($amount)->multipliedByFraction($numerator, $denominator)->toDecimal())
        ->toBe($expected);
})->with([
    '12.5% of 100.00' => ['100.00', 125, 1000, '12.50'],
    '10% of 9.99 rounds up from .999' => ['9.99', 10, 100, '1.00'],
    'a third of 0.01 rounds down' => ['0.01', 1, 3, '0.00'],
    'two thirds of 0.01 rounds up' => ['0.01', 2, 3, '0.01'],
    'exact half rounds away from zero' => ['0.05', 1, 2, '0.03'],
    'negative half rounds away from zero' => ['-0.05', 1, 2, '-0.03'],
    'whole multiple' => ['1.00', 3, 1, '3.00'],
]);

it('refuses to scale by a zero denominator', function () {
    Money::fromDecimal('1.00')->multipliedByFraction(1, 0);
})->throws(InvalidArgumentException::class);

it('negates and takes absolute value', function () {
    expect(Money::fromDecimal('-4.20')->negated()->toDecimal())->toBe('4.20')
        ->and(Money::fromDecimal('-4.20')->absolute()->toDecimal())->toBe('4.20')
        ->and(Money::fromDecimal('4.20')->absolute()->toDecimal())->toBe('4.20');
});

it('splits 100.00 three ways as 33.33 / 33.33 / 33.34', function () {
    $parts = Money::fromDecimal('100.00')->split(3);

    expect(array_map(fn (Money $part): string => $part->toDecimal(), $parts))
        ->toBe(['33.33', '33.33', '33.34']);
});

it('always allocates back to exactly the original amount', function (int $minorUnits, array $weights) {
    $parts = Money::fromMinorUnits($minorUnits)->allocate($weights);

    expect(Money::sum(...$parts)->minorUnits)->toBe($minorUnits)
        ->and($parts)->toHaveCount(count($weights));
})->with([
    'even split with remainder' => [10000, [1, 1, 1]],
    'weighted by value' => [10000, [3, 5, 2]],
    'awkward weights' => [1, [1, 1, 1, 1, 1]],
    'single line takes everything' => [9999, [1]],
    'zero-weight line gets nothing extra' => [10000, [0, 1, 1]],
    'negative total, e.g. a credit' => [-10000, [1, 1, 1]],
    'large freight across many lines' => [123457, [7, 11, 13, 17, 19]],
    'zero amount' => [0, [1, 2, 3]],
]);

it('gives leftover minor units to the last line when shares tie', function () {
    $parts = Money::fromMinorUnits(10)->allocate([1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1]);

    // 10 units over 12 equal weights: every line rounds down to 0, so all ten
    // residual units land on the last lines rather than the first.
    expect(array_map(fn (Money $part): int => $part->minorUnits, $parts))
        ->toBe([0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1]);
});

it('allocates a negative amount symmetrically to its positive counterpart', function () {
    $positive = Money::fromMinorUnits(10000)->allocate([1, 1, 1]);
    $negative = Money::fromMinorUnits(-10000)->allocate([1, 1, 1]);

    expect(array_map(fn (Money $part): int => -$part->minorUnits, $negative))
        ->toBe(array_map(fn (Money $part): int => $part->minorUnits, $positive));
});

it('weights allocation proportionally', function () {
    $parts = Money::fromDecimal('100.00')->allocate([70, 30]);

    expect(array_map(fn (Money $part): string => $part->toDecimal(), $parts))
        ->toBe(['70.00', '30.00']);
});

it('rejects allocations it cannot make sense of', function (array $weights) {
    Money::fromDecimal('10.00')->allocate($weights);
})->with([
    'no weights' => [[]],
    'all zero' => [[0, 0]],
    'negative weight' => [[1, -1]],
])->throws(InvalidArgumentException::class);

it('refuses to split into fewer than one part', function () {
    Money::fromDecimal('10.00')->split(0);
})->throws(InvalidArgumentException::class);

it('reports sign', function () {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::zero()->isPositive())->toBeFalse()
        ->and(Money::zero()->isNegative())->toBeFalse()
        ->and(Money::fromDecimal('0.01')->isPositive())->toBeTrue()
        ->and(Money::fromDecimal('-0.01')->isNegative())->toBeTrue();
});

it('compares amounts', function () {
    $low = Money::fromDecimal('1.00');
    $high = Money::fromDecimal('2.00');

    expect($low->equals(Money::fromDecimal('1.00')))->toBeTrue()
        ->and($low->equals($high))->toBeFalse()
        ->and($low->compareTo($high))->toBe(-1)
        ->and($high->compareTo($low))->toBe(1)
        ->and($low->compareTo(Money::fromDecimal('1.00')))->toBe(0)
        ->and($low->isLessThan($high))->toBeTrue()
        ->and($high->isGreaterThan($low))->toBeTrue()
        ->and($low->isLessThanOrEqualTo(Money::fromDecimal('1.00')))->toBeTrue()
        ->and($low->isGreaterThanOrEqualTo(Money::fromDecimal('1.00')))->toBeTrue();
});

it('renders a plain decimal string', function (int $minorUnits, string $expected) {
    expect(Money::fromMinorUnits($minorUnits)->toDecimal())->toBe($expected)
        ->and((string) Money::fromMinorUnits($minorUnits))->toBe($expected);
})->with([
    [0, '0.00'],
    [5, '0.05'],
    [50, '0.50'],
    [123456, '1234.56'],
    [-5, '-0.05'],
    [-123456, '-1234.56'],
]);

it('serializes to raw minor units so the frontend gets an exact integer', function () {
    expect(Money::fromDecimal('1234.56')->jsonSerialize())->toBe(123456)
        ->and(json_encode(['total' => Money::fromDecimal('1234.56')]))->toBe('{"total":123456}');
});

it('survives a decimal round trip', function (string $input) {
    expect(Money::fromDecimal($input)->toDecimal())->toBe($input);
})->with(['0.00', '0.01', '9.99', '1234.56', '-1234.56', '-0.01']);
