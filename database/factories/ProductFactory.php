<?php

namespace Database\Factories;

use App\Models\Product;
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
        return [
            'name' => ucfirst(fake()->unique()->word()).' '.ucfirst(fake()->word()),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * A plain product with the single item every product needs to be
     * sellable, carrying no options.
     */
    public function simple(): static
    {
        return $this->afterCreating(function (Product $product): void {
            ProductVariantFactory::new()->for($product)->create();
        });
    }
}
