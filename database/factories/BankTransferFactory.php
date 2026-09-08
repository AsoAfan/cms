<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\BankTransfer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransfer>
 */
class BankTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_bank_id' => Bank::factory(),
            'to_bank_id' => Bank::factory(),
            'amount' => Money::fromDecimal('500.00'),
            'occurred_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'reason' => null,
        ];
    }

    /**
     * The two accounts it runs between, named.
     */
    public function between(Bank $from, Bank $to): static
    {
        return $this->state([
            'from_bank_id' => $from->id,
            'to_bank_id' => $to->id,
        ]);
    }

    /**
     * A transfer of a stated amount, as a base-currency decimal string.
     */
    public function of(string $amount): static
    {
        return $this->state(['amount' => Money::fromDecimal($amount)]);
    }
}
