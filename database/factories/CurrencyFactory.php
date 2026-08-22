<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->word().' dollar',
            'symbol' => '$',
            'fraction_digits' => 2,
            'is_base' => false,
        ];
    }

    /**
     * The currency the books are kept in. Exactly one row may be this — use
     * `CurrencyService::makeBase()` rather than setting it on a second row.
     */
    public function base(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_base' => true,
        ]);
    }

    public function code(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => strtoupper($code),
        ]);
    }
}
