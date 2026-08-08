<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickSaleRequest extends FormRequest
{
    use ConvertsToBaseCurrency;

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
            'sold_on' => ['required', 'date'],
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
