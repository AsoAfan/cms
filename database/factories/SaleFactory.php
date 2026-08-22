<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\Money;
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
            'customer_id' => Customer::factory(),
            'number' => Sale::nextNumber(),
            'sold_on' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'status' => SaleStatus::Ordered,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            // Nothing paid unless a test says so. A sale whose lines are added
            // afterwards has no total yet to be paid in full against.
            'amount_paid' => Money::zero(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(['customer_id' => $customer->id]);
    }

    /**
     * Money handed over at the time of sale, as a base-currency decimal string.
     * Anything short of the total is the customer's loan against this invoice.
     */
    public function paid(string $amount): static
    {
        return $this->state(['amount_paid' => Money::fromDecimal($amount)]);
    }

    /**
     * Sent out to the customer — the point at which the stock leaves.
     *
     * Sets the status only. The goods leave the ledger through
     * `IssueSaleAction`, never by a factory writing `committed_at`.
     */
    public function sentOut(): static
    {
        return $this->state(['status' => SaleStatus::OnTheWay]);
    }

    /**
     * The customer has it.
     */
    public function delivered(): static
    {
        return $this->state(['status' => SaleStatus::Proceed]);
    }
}
