<?php

use App\Exceptions\MissingExchangeRateException;
use App\Support\ExchangeRates;
use App\Support\Money;

/**
 * IQD base, 1,320 dinars to the dollar — the shape every test here uses unless
 * it says otherwise.
 */
function rates(string $rate = '1320'): ExchangeRates
{
    return ExchangeRates::for('IQD', ['USD' => ExchangeRates::rateFromDecimal($rate)], '2026-08-08');
}

it('parses a rate as typed, without touching a float', function (string|int|float $input, int $expected) {
    expect(ExchangeRates::rateFromDecimal($input))->toBe($expected);
})->with([
    'whole number' => ['1320', 1_320_000_000],
    'one decimal' => ['1320.5', 1_320_500_000],
    'full precision' => ['1320.123456', 1_320_123_456],
    'trailing zeros' => ['1320.500000', 1_320_500_000],
    'a rate below one' => ['0.000758', 758],
    'integer input' => [1320, 1_320_000_000],
    'surrounding whitespace' => ['  1320  ', 1_320_000_000],
]);

it('rejects a rate it cannot represent exactly', function (string $input) {
    ExchangeRates::rateFromDecimal($input);
})->with([
    'more precision than the scale' => '1320.1234567',
    'zero' => '0',
    'negative' => '-1320',
    'letters' => 'abc',
    'empty' => '',
    'thousands separator' => '1,320',
])->throws(InvalidArgumentException::class);

it('renders a rate back with trailing zeros trimmed', function (int $rate, string $expected) {
    expect(ExchangeRates::rateToDecimal($rate))->toBe($expected);
})->with([
    'whole' => [1_320_000_000, '1320'],
    'one decimal' => [1_320_500_000, '1320.5'],
    'full precision' => [1_320_123_456, '1320.123456'],
    'below one' => [758, '0.000758'],
]);

it('round-trips a rate through its decimal form', function (string $rate) {
    expect(ExchangeRates::rateToDecimal(ExchangeRates::rateFromDecimal($rate)))->toBe($rate);
})->with(['1320', '1320.5', '1320.123456', '0.000758']);

it('treats the base currency as its own unit', function () {
    $rates = rates();

    expect($rates->rateFor('IQD'))->toBe(ExchangeRates::SCALE)
        ->and($rates->has('IQD'))->toBeTrue();
});

it('leaves a base-currency amount exactly as it is', function () {
    $amount = Money::fromDecimal('24000.00');

    expect(rates()->toBase($amount, 'IQD')->minorUnits)->toBe($amount->minorUnits)
        ->and(rates()->fromBase($amount, 'IQD')->minorUnits)->toBe($amount->minorUnits);
});

it('converts a foreign amount into the base currency exactly', function (string $amount, string $rate, string $expected) {
    expect(rates($rate)->toBase(Money::fromDecimal($amount), 'USD')->toDecimal())->toBe($expected);
})->with([
    'a whole rate divides cleanly' => ['18.50', '1320', '24420.00'],
    'a fractional rate' => ['18.50', '1320.5', '24429.25'],
    'one cent' => ['0.01', '1320', '13.20'],
    'zero is zero in any currency' => ['0.00', '1320', '0.00'],
    'a negative amount keeps its sign' => ['-18.50', '1320', '-24420.00'],
    'a rate needing rounding, half away from zero' => ['0.01', '1320.555', '13.21'],
]);

it('converts back out of the base currency for display', function () {
    $base = Money::fromDecimal('24420.00');

    expect(rates()->fromBase($base, 'USD')->toDecimal())->toBe('18.50');
});

it('rounds half away from zero, matching Money', function () {
    // 1 cent at 1320.555 is 13.20555 dinars, which rounds up; the negative of
    // it must round the same distance in the other direction, not toward zero.
    $rates = rates('1320.555');

    expect($rates->toBase(Money::fromDecimal('0.01'), 'USD')->minorUnits)->toBe(1321)
        ->and($rates->toBase(Money::fromDecimal('-0.01'), 'USD')->minorUnits)->toBe(-1321);
});

it('refuses to convert a currency it has no rate for', function () {
    rates()->toBase(Money::fromDecimal('10.00'), 'EUR');
})->throws(MissingExchangeRateException::class);

it('says which currency and date it had no rate for', function () {
    expect(fn () => rates()->rateFor('EUR'))
        ->toThrow(MissingExchangeRateException::class, 'EUR against IQD on or before 2026-08-08');
});

it('knows which currencies it can convert, base first', function () {
    expect(rates()->currencies())->toBe(['IQD', 'USD'])
        ->and(rates()->has('USD'))->toBeTrue()
        ->and(rates()->has('EUR'))->toBeFalse();
});

it('reads currency codes case-insensitively', function () {
    expect(rates()->has('usd'))->toBeTrue()
        ->and(rates()->rateFor('usd'))->toBe(1_320_000_000)
        ->and(rates()->toBase(Money::fromDecimal('1.00'), 'usd')->toDecimal())->toBe('1320.00');
});

it('never stores a rate for the base currency against itself', function () {
    $rates = ExchangeRates::for('IQD', ['IQD' => 5_000_000, 'USD' => 1_320_000_000]);

    expect($rates->rates)->toBe(['USD' => 1_320_000_000])
        ->and($rates->rateFor('IQD'))->toBe(ExchangeRates::SCALE);
});

it('refuses a rate of zero or less', function (int $rate) {
    ExchangeRates::for('IQD', ['USD' => $rate]);
})->with([0, -1])->throws(InvalidArgumentException::class);
