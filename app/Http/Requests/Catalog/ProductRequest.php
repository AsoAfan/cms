<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'code')->ignore($this->route('product')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'That code is already used by another product.',
            'default_cost_price.decimal' => 'Use at most two decimal places.',
            'default_selling_price.decimal' => 'Use at most two decimal places.',
        ];
    }

    /**
     * The validated payload, with money as decimal strings the Money cast
     * accepts and blanks normalised to null.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'code' => $this->string('code')->toString(),
            'description' => $this->filled('description')
                ? $this->string('description')->toString()
                : null,
            'default_cost_price' => $this->money('default_cost_price'),
            'default_selling_price' => $this->money('default_selling_price'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    private function money(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
