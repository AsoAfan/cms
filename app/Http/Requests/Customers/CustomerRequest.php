<?php

namespace App\Http\Requests\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the name is required — a customer is often a name and a phone number
     * when you first write them down, and the form must not stand in the way of
     * recording that.
     *
     * The name is unique because it is what a customer is picked by on the sale
     * screen and recognised by at the top of a statement. A second Ahmed gets his
     * phone number in the name; two rows nobody can tell apart help no one.
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
                Rule::unique('customers', 'name')->ignore($this->route('customer')),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a customer with that name. Add their phone number to tell them apart.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'phone' => $this->optional('phone'),
            'email' => $this->optional('email'),
            'address' => $this->optional('address'),
            'notes' => $this->optional('notes'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    private function optional(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
