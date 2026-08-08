<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'title' => fake()->randomElement([
                'Shop rent',
                'Staff wages',
                'Van diesel',
                'Electricity bill',
                'Printed flyers',
                'Cleaning supplies',
            ]),
            'amount' => Money::fromMinorUnits(fake()->numberBetween(500, 250_000)),
            'spent_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
