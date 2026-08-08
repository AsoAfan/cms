<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\CostAllocationMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'invoiced_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id'), 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],

            'additional_costs' => ['array'],
            'additional_costs.*.label' => ['required', 'string', 'max:100'],
            'additional_costs.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'additional_costs.*.allocation_method' => ['required', Rule::enum(CostAllocationMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'A purchase needs at least one line.',
            'lines.min' => 'A purchase needs at least one line.',
            'lines.*.product_id.distinct' => 'That product is already on this purchase.',
            'lines.*.quantity.min' => 'Quantity must be at least one.',
        ];
    }

    /**
     * Named `invoiceHeader` to avoid colliding with Request::header().
     *
     * @return array{supplier_id: int, invoiced_on: string, notes: string|null}
     */
    public function invoiceHeader(): array
    {
        return [
            'supplier_id' => $this->integer('supplier_id'),
            'invoiced_on' => $this->date('invoiced_on')->toDateString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int, unit_cost: string, discount: string}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->array('lines') as $line) {
            $line = (array) $line;

            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
                'discount' => (string) ($line['discount'] ?? '0') ?: '0',
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{label: string, amount: string, allocation_method: string}>
     */
    public function additionalCosts(): array
    {
        $costs = [];

        foreach ($this->array('additional_costs') as $cost) {
            $cost = (array) $cost;

            $costs[] = [
                'label' => (string) $cost['label'],
                'amount' => (string) $cost['amount'],
                'allocation_method' => (string) $cost['allocation_method'],
            ];
        }

        return $costs;
    }
}
