<?php

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickPurchaseRequest extends FormRequest
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
            'unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'unit_cost_currency' => $currency,
            'currency' => $currency,
            'invoiced_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_cost.decimal' => 'Use at most two decimal places.',
            'unit_cost_currency.in' => 'There is no exchange rate on record for that currency.',
        ];
    }

    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('invoiced_on');
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }

    /**
     * The cost in the base currency, whatever it was typed in.
     */
    public function unitCost(): string
    {
        return (string) $this->baseMoney('unit_cost', '0');
    }

    public function currency(): string
    {
        return $this->documentCurrency();
    }

    public function exchangeRate(): int
    {
        return $this->documentRate();
    }

    public function invoicedOn(): string
    {
        return $this->date('invoiced_on')->toDateString();
    }
}
