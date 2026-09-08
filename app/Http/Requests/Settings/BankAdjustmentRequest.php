<?php

namespace App\Http\Requests\Settings;

use App\Enums\BankAdjustmentDirection;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money put into or taken out of an account by hand.
 *
 * The direction is picked on the form and the amount is typed as a positive
 * figure; the sign is applied here, once, by
 * `BankAdjustmentDirection::apply()`. A form that posted a negative number
 * would make every screen showing the field responsible for the minus.
 */
class BankAdjustmentRequest extends FormRequest
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
            'direction' => ['required', Rule::enum(BankAdjustmentDirection::class)],

            // Free text, and required: an amount and a date with nothing to
            // identify them is a row nobody can account for a month later.
            'reason' => ['required', 'string', 'max:255'],

            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'amount_currency' => $currency,
            'currency' => $currency,

            'occurred_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Say what this money was.',
            'amount.gt' => 'A balance movement has to be more than nothing.',
            'amount_currency.in' => 'There is no exchange rate on record for that currency.',
        ];
    }

    /**
     * A movement converts at the rate in force on the day it happened, so a
     * back-dated opening balance is worth what it was worth then.
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
        $direction = $this->enum('direction', BankAdjustmentDirection::class) ?? BankAdjustmentDirection::In;

        return [
            'reason' => $this->string('reason')->trim()->toString(),
            // Stored signed: positive in, negative out.
            'amount' => $direction->apply(Money::fromDecimal((string) $this->baseMoney('amount', '0')))->toDecimal(),
            'occurred_on' => $this->date('occurred_on')->toDateString(),
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
        ];
    }
}
