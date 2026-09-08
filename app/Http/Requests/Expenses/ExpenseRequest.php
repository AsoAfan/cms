<?php

namespace App\Http\Requests\Expenses;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Http\Requests\Concerns\NamesPayingBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
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
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'amount_currency' => $currency,
            'currency' => $currency,
            'spent_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_id' => $this->bankRules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Say what the money went on.',
            'amount.gt' => 'An expense has to be more than nothing.',
            'amount_currency.in' => 'There is no exchange rate on record for that currency.',
            ...$this->bankMessages(),
        ];
    }

    /**
     * Spending converts at the rate in force on the day it was spent.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('spent_on');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'expense_category_id' => $this->integer('expense_category_id'),
            'title' => $this->string('title')->toString(),
            'amount' => $this->baseMoney('amount', '0'),
            'spent_on' => $this->date('spent_on')->toDateString(),
            'payment_method' => $this->string('payment_method')->toString(),
            'bank_id' => $this->bankId(),
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
