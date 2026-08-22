<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The name is the identity — it is what every payment dropdown shows and
     * what a figure is attributed to — so it is required and unique. Everything
     * else is filing, and a bank often starts life as nothing but a name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('banks', 'name')->ignore($this->route('bank')),
            ],
            'account_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the bank a name.',
            'name.unique' => 'That bank is already on the list.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'account_number' => $this->filled('account_number')
                ? $this->string('account_number')->trim()->toString()
                : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
