<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a `bigInteger` minor-unit column to and from a {@see Money}.
 *
 * Reach for it as `'total' => Money::class` in a model's `casts` method —
 * `Money` is Castable and points here.
 *
 * Assignment accepts a Money, an integer of minor units, or a decimal string;
 * the generic stays `mixed` because Eloquent hands through whatever was
 * assigned, and anything else has to be rejected at runtime.
 *
 * @implements CastsAttributes<Money, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromMinorUnits((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->minorUnits,
            is_int($value) => $value,
            is_string($value) => Money::fromDecimal($value)->minorUnits,
            default => throw new InvalidArgumentException(
                "Cannot store [{$key}] as money: expected a Money, an integer of minor units, or a decimal string."
            ),
        };
    }
}
