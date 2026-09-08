<?php

namespace App\Support;

use App\Exceptions\MissingExchangeRateException;
use InvalidArgumentException;

/**
 * The exchange rates in force on one date, and the only place that converts
 * between currencies.
 *
 * A rate is **base major units per one foreign major unit**, held as a
 * fixed-point integer scaled by {@see self::SCALE}: 1,320.50 IQD to the dollar
 * is 1_320_500_000. Integers throughout, so a rate never drifts the way a
 * float would.
 *
 * Conversion is exact because {@see Money} uses the same two decimal places for
 * every currency, which makes the major-per-major rate also the minor-per-minor
 * rate. $18.50 (1850 minor) at 1320.5 is 1850 × 1320.5 = 2,442,925 minor, or
 * 24,429.25 dinars — one integer multiplication and one division, rounded half
 * away from zero by `Money::multipliedByFraction()`.
 *
 * Framework-free and immutable on purpose: every conversion rule in the
 * application is unit-testable without a database.
 */
final readonly class ExchangeRates
{
    /**
     * Fixed-point denominator for a rate. Mirrored as `RATE_SCALE` in
     * `resources/js/lib/money.ts` so the live preview a form shows while typing
     * cannot disagree with what the server goes on to store.
     */
    public const int SCALE = 1_000_000;

    /**
     * Decimal places a rate is recorded to, matching {@see self::SCALE}.
     */
    public const int RATE_PRECISION = 6;

    /**
     * @param  array<string, int>  $rates  foreign currency code => scaled rate, base excluded
     */
    private function __construct(
        public string $base,
        public array $rates,
        public ?string $on = null,
    ) {}

    /**
     * @param  array<string, int>  $rates  foreign currency code => scaled rate
     *
     * @throws InvalidArgumentException
     */
    public static function for(string $base, array $rates = [], ?string $on = null): self
    {
        $base = strtoupper($base);
        $normalised = [];

        foreach ($rates as $currency => $rate) {
            $currency = strtoupper((string) $currency);

            if ($rate <= 0) {
                throw new InvalidArgumentException("The exchange rate for {$currency} must be greater than zero.");
            }

            if ($currency !== $base) {
                $normalised[$currency] = $rate;
            }
        }

        return new self($base, $normalised, $on);
    }

    /**
     * Whether an amount in this currency can be converted.
     */
    public function has(string $currency): bool
    {
        $currency = strtoupper($currency);

        return $currency === $this->base || isset($this->rates[$currency]);
    }

    /**
     * The scaled rate for a currency. The base currency is its own unit, so it
     * is always exactly {@see self::SCALE}.
     *
     * @throws MissingExchangeRateException
     */
    public function rateFor(string $currency): int
    {
        $currency = strtoupper($currency);

        if ($currency === $this->base) {
            return self::SCALE;
        }

        return $this->rates[$currency]
            ?? throw MissingExchangeRateException::for($currency, $this->base, $this->on);
    }

    /**
     * Convert an amount typed in some currency into the base currency.
     *
     * @throws MissingExchangeRateException
     */
    public function toBase(Money $amount, string $currency): Money
    {
        if (strtoupper($currency) === $this->base) {
            return $amount;
        }

        return $amount->multipliedByFraction($this->rateFor($currency), self::SCALE);
    }

    /**
     * Convert a base-currency amount for display in another currency. Display
     * only — never store the result.
     *
     * @throws MissingExchangeRateException
     */
    public function fromBase(Money $amount, string $currency): Money
    {
        if (strtoupper($currency) === $this->base) {
            return $amount;
        }

        return $amount->multipliedByFraction(self::SCALE, $this->rateFor($currency));
    }

    /**
     * Every currency an amount can be entered or viewed in, base first.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        return [$this->base, ...array_keys($this->rates)];
    }

    /**
     * Parse a rate as typed — "1320.5" — into its scaled integer.
     *
     * Done on the string rather than through a float, for the same reason
     * `Money::fromDecimal()` is: 1320.05 must not become 1320.049999.
     *
     * @throws InvalidArgumentException
     */
    public static function rateFromDecimal(string|int|float $rate): int
    {
        $rate = trim((string) $rate);

        if (preg_match('/^(?<major>\d+)(?:\.(?<minor>\d*))?$/', $rate, $matches) !== 1) {
            throw new InvalidArgumentException("Cannot read [{$rate}] as an exchange rate.");
        }

        $fraction = $matches['minor'] ?? '';

        if (strlen(rtrim($fraction, '0')) > self::RATE_PRECISION) {
            throw new InvalidArgumentException(
                "[{$rate}] carries more precision than ".self::RATE_PRECISION.' decimal places.'
            );
        }

        $scaled = (int) $matches['major'] * self::SCALE
            + (int) str_pad(substr($fraction, 0, self::RATE_PRECISION), self::RATE_PRECISION, '0');

        if ($scaled <= 0) {
            throw new InvalidArgumentException('An exchange rate must be greater than zero.');
        }

        return $scaled;
    }

    /**
     * Render a scaled rate as a decimal string with trailing zeros trimmed:
     * 1_320_500_000 becomes "1320.5".
     */
    public static function rateToDecimal(int $rate): string
    {
        $fraction = rtrim(
            str_pad((string) ($rate % self::SCALE), self::RATE_PRECISION, '0', STR_PAD_LEFT),
            '0'
        );

        return intdiv($rate, self::SCALE).($fraction === '' ? '' : '.'.$fraction);
    }
}
