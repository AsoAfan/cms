<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
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
            'sold_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],

            // What the customer actually handed over. Dinars mostly, dollars
            // sometimes — that is the fact this records.
            'currency' => $currency,

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id'), 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.unit_price_currency' => $currency,
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount_currency' => $currency,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'A sale needs at least one line.',
            'lines.min' => 'A sale needs at least one line.',
            'lines.*.product_id.distinct' => 'That product is already on this sale.',
            'currency.in' => 'There is no exchange rate on record for that currency.',
        ];
    }

    /**
     * A sale converts at the rate in force on the day it was sold, so a sale
     * entered late is still costed at the rate of the day the money changed
     * hands.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('sold_on');
    }

    /**
     * Named `saleHeader` to avoid colliding with Request::header().
     *
     * @return array{sold_on: string, payment_method: string, notes: string|null, currency: string, exchange_rate: int}
     */
    public function saleHeader(): array
    {
        return [
            'sold_on' => $this->date('sold_on')->toDateString(),
            'payment_method' => $this->string('payment_method')->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
        ];
    }

    /**
     * Prices in the base currency, whatever they were typed in.
     *
     * @return list<array{product_id: int, quantity: int, unit_price: string, discount: string}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->array('lines') as $line) {
            $line = (array) $line;

            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_price' => $this->baseMoneyIn($line, 'unit_price'),
                'discount' => $this->baseMoneyIn($line, 'discount'),
            ];
        }

        return $lines;
    }
}
