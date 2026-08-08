<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => Sale::nextNumber(),
            'sold_on' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'status' => SaleStatus::Draft,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
