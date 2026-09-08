<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use App\Support\ExchangeRates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency' => 'USD',
            'rate' => ExchangeRates::rateFromDecimal((string) fake()->numberBetween(1_300, 1_500)),
            'effective_on' => today()->toDateString(),
        ];
    }

    /**
     * A specific rate, given as it would read: `->at('1320.5')`.
     */
    public function at(string|int $rate): static
    {
        return $this->state(fn (array $attributes): array => [
            'rate' => ExchangeRates::rateFromDecimal($rate),
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_on' => $date,
        ]);
    }
}
