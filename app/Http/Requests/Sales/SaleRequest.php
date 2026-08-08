<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
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
            'sold_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id'), 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
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
        ];
    }

    /**
     * Named `saleHeader` to avoid colliding with Request::header().
     *
     * @return array{sold_on: string, payment_method: string, notes: string|null}
     */
    public function saleHeader(): array
    {
        return [
            'sold_on' => $this->date('sold_on')->toDateString(),
            'payment_method' => $this->string('payment_method')->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }

    /**
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
                'unit_price' => (string) $line['unit_price'],
                'discount' => (string) ($line['discount'] ?? '0') ?: '0',
            ];
        }

        return $lines;
    }
}
