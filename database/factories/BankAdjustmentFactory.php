<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\BankAdjustment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAdjustment>
 */
class BankAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'reason' => 'Opening balance',
            'amount' => Money::fromDecimal('1000.00'),
            'occurred_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
        ];
    }

    /**
     * Money taken back out — the amount stored negative, as the direction
     * enum writes it.
     */
    public function out(string $amount = '250.00'): static
    {
        return $this->state([
            'reason' => 'Cash withdrawn',
            'amount' => Money::fromDecimal($amount)->negated(),
        ]);
    }
}
