<?php

namespace Database\Factories;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(100, 50_000);

        return [
            'name' => ucfirst(fake()->unique()->word()).' '.ucfirst(fake()->word()),
            'code' => mb_strtoupper(fake()->unique()->bothify('???-####')),
            'description' => fake()->optional()->sentence(),
            'default_cost_price' => Money::fromMinorUnits($cost),
            // A plausible margin, so seeded reports are not nonsense.
            'default_selling_price' => Money::fromMinorUnits(
                (int) round($cost * fake()->randomFloat(2, 1.2, 2.0))
            ),
            'is_active' => true,
        ];
    }

    /**
     * Not yet priced — both defaults null, which is a real state in the
     * catalogue rather than a zero standing in for one.
     */
    public function unpriced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'default_cost_price' => null,
            'default_selling_price' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
