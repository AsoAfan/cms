<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the name is required — a supplier is often just a name and a phone
     * number when you first write it down.
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
                Rule::unique('suppliers', 'name')->ignore($this->route('supplier')),
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
            'name.unique' => 'You already have a supplier with that name.',
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
