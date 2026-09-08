<?php

namespace App\Support;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact monetary amount, held as a whole number of minor units (cents).
 *
 * Every operation is integer arithmetic — floats never touch a figure that
 * reaches the database or a report. Values serialize to their raw minor-unit
 * integer, which the `formatMoney` TypeScript helper renders for display.
 */
final readonly class Money implements Castable, JsonSerializable, Stringable
{
    /**
     * Decimal places in the minor unit (cents).
     */
    public const int SCALE = 2;

    /**
     * Minor units per major unit.
     */
    public const int SUBUNITS = 100;

    private function __construct(public int $minorUnits) {}

    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Build from a decimal string such as "1234.56".
     *
     * Parsing is done on the string itself rather than through a float, so
     * "0.29" is 29 cents and never 28. Anything with more precision than the
     * minor unit is rejected outright — silently dropping a fraction of a cent
     * is how ledgers stop reconciling.
     *
     * @throws InvalidArgumentException
     */
    public static function fromDecimal(string|int $amount): self
    {
        $amount = trim((string) $amount);

        if (preg_match('/^(?<sign>[+-]?)(?<major>\d+)(?:\.(?<minor>\d*))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException("Cannot read [{$amount}] as a monetary amount.");
        }

        $fraction = $matches['minor'] ?? '';

        if (strlen(rtrim($fraction, '0')) > self::SCALE) {
            throw new InvalidArgumentException(
                "[{$amount}] carries more precision than ".self::SCALE.' decimal places.'
            );
        }

        $minorUnits = (int) $matches['major'] * self::SUBUNITS
            + (int) str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');

        return new self($matches['sign'] === '-' ? -$minorUnits : $minorUnits);
    }

    public static function sum(self ...$amounts): self
    {
        return array_reduce(
            $amounts,
            static fn (self $carry, self $amount): self => $carry->plus($amount),
            self::zero()
        );
    }

    public function plus(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function minus(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    /**
     * Multiply by a whole number — exact, so no rounding decision arises.
     *
     * This is the quantity case: 7 units at $3.45.
     */
    public function multipliedBy(int $multiplier): self
    {
        return new self($this->minorUnits * $multiplier);
    }

    /**
     * Scale by the exact fraction numerator/denominator, rounding half away
     * from zero.
     *
     * Percentages stay exact by passing them as a fraction — 12.5% is
     * `multipliedByFraction(125, 1000)`, never 0.125.
     *
     * @throws InvalidArgumentException
     */
    public function multipliedByFraction(int $numerator, int $denominator): self
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot scale a monetary amount by a zero denominator.');
        }

        $product = $this->minorUnits * $numerator;
        $sign = ($product < 0) !== ($denominator < 0) ? -1 : 1;

        return new self($sign * intdiv(abs($product) * 2 + abs($denominator), abs($denominator) * 2));
    }

    public function negated(): self
    {
        return new self(-$this->minorUnits);
    }

    public function absolute(): self
    {
        return new self(abs($this->minorUnits));
    }

    /**
     * Split across the given weights so the parts sum back to exactly this
     * amount.
     *
     * Uses largest-remainder allocation: every part gets its whole share, then
     * the leftover minor units go one at a time to the parts furthest from
     * their exact share, ties breaking toward the last. Allocating $100.00
     * across three equal weights yields 3333/3333/3334 — never 3333/3333/3333
     * with a cent unaccounted for.
     *
     * @param  array<array-key, int>  $weights  non-negative, at least one greater than zero
     * @return list<self> one amount per weight, in the same order
     *
     * @throws InvalidArgumentException
     */
    public function allocate(array $weights): array
    {
        $weights = array_values($weights);

        if ($weights === []) {
            throw new InvalidArgumentException('Cannot allocate a monetary amount across zero weights.');
        }

        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Allocation weights cannot be negative.');
            }
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            throw new InvalidArgumentException('Allocation weights must not all be zero.');
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $index => $weight) {
            $exact = $this->minorUnits * $weight;
            $share = intdiv($exact, $totalWeight);

            $shares[$index] = $share;
            $remainders[$index] = abs($exact - $share * $totalWeight);
            $allocated += $share;
        }

        $residual = $this->minorUnits - $allocated;
        $step = $residual <=> 0;

        $order = array_keys($shares);
        usort(
            $order,
            static fn (int $a, int $b): int => [$remainders[$b], $b] <=> [$remainders[$a], $a]
        );

        for ($i = 0; $i < abs($residual); $i++) {
            $shares[$order[$i % count($order)]] += $step;
        }

        return array_values(array_map(static fn (int $share): self => new self($share), $shares));
    }

    /**
     * Split evenly into the given number of parts, remainder to the last.
     *
     * @return list<self>
     *
     * @throws InvalidArgumentException
     */
    public function split(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot split a monetary amount into fewer than one part.');
        }

        return $this->allocate(array_fill(0, $parts, 1));
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    public function compareTo(self $other): int
    {
        return $this->minorUnits <=> $other->minorUnits;
    }

    public function isLessThan(self $other): bool
    {
        return $this->minorUnits < $other->minorUnits;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->minorUnits > $other->minorUnits;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        return $this->minorUnits <= $other->minorUnits;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->minorUnits >= $other->minorUnits;
    }

    /**
     * Render as a plain decimal string, e.g. "-1234.56". No thousands
     * separators or symbol — that is the display layer's job.
     */
    public function toDecimal(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);

        return $sign.intdiv($absolute, self::SUBUNITS)
            .'.'.str_pad((string) ($absolute % self::SUBUNITS), self::SCALE, '0', STR_PAD_LEFT);
    }

    /**
     * @param  string[]  $arguments
     * @return class-string<MoneyCast>
     */
    public static function castUsing(array $arguments): string
    {
        return MoneyCast::class;
    }

    /**
     * Serialize to raw minor units so the frontend receives an exact integer.
     */
    public function jsonSerialize(): int
    {
        return $this->minorUnits;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
