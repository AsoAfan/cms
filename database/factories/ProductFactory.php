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
     * Dinar-scale figures: a few thousand to a few hundred thousand, which is
     * what the goods this catalogue holds actually cost.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(2_500, 250_000) * Money::SUBUNITS;

        return [
            'name' => ucfirst(fake()->unique()->word()).' '.ucfirst(fake()->word()),
            'description' => fake()->optional()->sentence(),
            'cost_price' => Money::fromMinorUnits($cost),
            // A plausible margin, so seeded reports are not nonsense.
            'selling_price' => Money::fromMinorUnits(
                (int) round($cost * fake()->randomFloat(2, 1.2, 2.0))
            ),
        ];
    }

    /**
     * A product priced exactly, given as it would be typed: `->priced('18000', '32000')`.
     */
    public function priced(string|int $cost, string|int $selling): static
    {
        return $this->state(fn (array $attributes): array => [
            'cost_price' => Money::fromDecimal((string) $cost),
            'selling_price' => Money::fromDecimal((string) $selling),
        ]);
    }
}
