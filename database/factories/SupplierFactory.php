<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * A supplier taken down in a hurry — just a name.
     */
    public function nameOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
