<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
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
            'number' => Purchase::nextNumber(),
            'invoiced_on' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'status' => PurchaseStatus::Ordered,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * On its way from the supplier — still no stock, still editable.
     */
    public function onTheWay(): static
    {
        return $this->state(['status' => PurchaseStatus::OnTheWay]);
    }

    /**
     * Arrived.
     *
     * Sets the status only. The goods reach the ledger through
     * `ReceivePurchaseAction`, never by a factory writing `committed_at` — a
     * purchase marked arrived with no stock behind it is a fixture that proves
     * nothing.
     */
    public function arrived(): static
    {
        return $this->state(['status' => PurchaseStatus::Proceed]);
    }
}
