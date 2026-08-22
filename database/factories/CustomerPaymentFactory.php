<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\ExchangeRates;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    /**
     * Amounts are base-currency minor units, as everywhere below a Form Request.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'amount' => Money::fromMinorUnits(fake()->numberBetween(1_000, 500_000)),
            'received_on' => fake()->dateTimeBetween('-2 months')->format('Y-m-d'),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'currency' => config('money.currency'),
            'exchange_rate' => ExchangeRates::SCALE,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * A payment of a stated amount, as a base-currency decimal string.
     */
    public function of(string $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => Money::fromDecimal($amount),
        ]);
    }
}
