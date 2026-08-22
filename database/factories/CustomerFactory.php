<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * A customer taken down in a hurry — just a name.
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

    /**
     * The customer counter trade is recorded against.
     */
    public function walkIn(): static
    {
        return $this->nameOnly()->state(fn (array $attributes): array => [
            'name' => Customer::WALK_IN,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
