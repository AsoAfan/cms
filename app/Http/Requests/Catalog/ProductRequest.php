<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->ignore($this->route('product')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],

            // Both required: a product without a price is a product that cannot
            // be sold, and "not priced yet" as a stored state meant every screen
            // and every prefill had to handle a null that helped nobody.
            'cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'cost_price_currency' => $currency,
            'selling_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'selling_price_currency' => $currency,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A product with that name already exists.',
            'cost_price.required' => 'What does it cost you?',
            'cost_price.decimal' => 'Use at most two decimal places.',
            'selling_price.required' => 'What do you sell it for?',
            'selling_price.gt' => 'A selling price has to be more than nothing.',
            'selling_price.decimal' => 'Use at most two decimal places.',
            'cost_price_currency.in' => 'There is no exchange rate on record for that currency.',
            'selling_price_currency.in' => 'There is no exchange rate on record for that currency.',
        ];
    }

    /**
     * The validated payload, with money as base-currency decimal strings the
     * `Money` cast accepts.
     *
     * A product is not a dated document, so a price quoted in dollars converts
     * at today's rate. It is a figure to type over when it goes out of date, the
     * same as when a supplier puts their prices up.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description')
                ? $this->string('description')->toString()
                : null,
            'cost_price' => $this->baseMoney('cost_price', '0'),
            'selling_price' => $this->baseMoney('selling_price', '0'),
        ];
    }
}
