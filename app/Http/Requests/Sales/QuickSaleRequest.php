<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Http\Requests\Concerns\NamesPayingBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickSaleRequest extends FormRequest
{
    use ConvertsToBaseCurrency;
    use NamesPayingBank;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currency = ['nullable', Rule::in($this->enterableCurrencies())];

        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'unit_price_currency' => $currency,
            'currency' => $currency,
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_id' => $this->bankRules(),
            'sold_on' => ['required', 'date'],

            // Optional here alone: leaving it off means counter trade, which the
            // action files under the walk-in customer. The full sale screen
            // requires a buyer because it has room to ask for one.
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],

            // Blank means paid in full — over the counter the goods and the money
            // change hands together. Anything less is a loan from the off.
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'amount_paid_currency' => $currency,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_price.decimal' => 'Use at most two decimal places.',
            'unit_price_currency.in' => 'There is no exchange rate on record for that currency.',
            ...$this->bankMessages(),
        ];
    }

    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('sold_on');
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }

    /**
     * Who bought it, or null for counter trade.
     */
    public function customerId(): ?int
    {
        return $this->filled('customer_id') ? $this->integer('customer_id') : null;
    }

    /**
     * What they handed over, in the base currency — or null for paid in full.
     */
    public function amountPaid(): ?string
    {
        return $this->filled('amount_paid') ? (string) $this->baseMoney('amount_paid', '0') : null;
    }

    /**
     * The price in the base currency, whatever it was typed in.
     */
    public function unitPrice(): string
    {
        return (string) $this->baseMoney('unit_price', '0');
    }

    public function currency(): string
    {
        return $this->documentCurrency();
    }

    public function exchangeRate(): int
    {
        return $this->documentRate();
    }

    public function paymentMethod(): string
    {
        return $this->string('payment_method')->toString();
    }

    public function soldOn(): string
    {
        return $this->date('sold_on')->toDateString();
    }
}
