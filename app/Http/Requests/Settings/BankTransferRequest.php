<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving money between two of the business's own accounts.
 *
 * The amount is always positive and always runs from → to; moving it back is
 * the same form filled in the other way round, not a negative figure.
 */
class BankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    use ConvertsToBaseCurrency;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currency = ['nullable', Rule::in($this->enterableCurrencies())];

        return [
            'from_bank_id' => ['required', 'integer', Rule::exists('banks', 'id')],

            // An account cannot pay itself. Nothing would move, and a row
            // saying it did is a typo that reads as real money.
            'to_bank_id' => [
                'required',
                'integer',
                'different:from_bank_id',
                Rule::exists('banks', 'id'),
            ],

            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'amount_currency' => $currency,
            'currency' => $currency,

            'occurred_on' => ['required', 'date'],

            // Optional, unlike an adjustment's: the two accounts and the date
            // already identify a transfer.
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_bank_id.required' => 'Say which account the money left.',
            'to_bank_id.required' => 'Say which account the money went to.',
            'to_bank_id.different' => 'Pick a different account to move the money to.',
            'from_bank_id.exists' => 'That bank is not on the list.',
            'to_bank_id.exists' => 'That bank is not on the list.',
            'amount.gt' => 'A transfer has to be more than nothing.',
            'amount_currency.in' => 'There is no exchange rate on record for that currency.',
        ];
    }

    /**
     * Money moved converts at the rate in force on the day it moved.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('occurred_on');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'from_bank_id' => $this->integer('from_bank_id'),
            'to_bank_id' => $this->integer('to_bank_id'),
            'amount' => $this->baseMoney('amount', '0'),
            'occurred_on' => $this->date('occurred_on')->toDateString(),
            'reason' => $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
        ];
    }
}
