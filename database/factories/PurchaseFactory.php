<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'number' => Purchase::nextNumber(),
            'invoiced_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'status' => PurchaseStatus::Draft,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
