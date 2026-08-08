<?php

namespace App\Http\Requests\Expenses;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'spent_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'An expense has to be more than nothing.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'expense_category_id' => $this->integer('expense_category_id'),
            'amount' => $this->string('amount')->toString(),
            'spent_on' => $this->date('spent_on')->toDateString(),
            'payment_method' => $this->string('payment_method')->toString(),
            'reference' => $this->filled('reference') ? $this->string('reference')->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
