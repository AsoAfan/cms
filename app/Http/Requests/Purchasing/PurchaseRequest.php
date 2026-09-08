<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\CostAllocationMethod;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Http\Requests\Concerns\FilesUnderAReference;
use App\Http\Requests\Concerns\NamesPayingBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    use ConvertsToBaseCurrency;
    use FilesUnderAReference;
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
            // The filing reference. Prefilled with the next in sequence, and
            // the user's to overwrite when the supplier's paperwork says
            // otherwise; cleared, the invoice keeps the number it already has.
            'number' => $this->referenceRules('purchases', $this->route('purchase')),

            'invoiced_on' => ['required', 'date'],

            // Where the order stands. Set on the way in rather than only by the
            // buttons on the invoice, so goods already in your hand are one
            // save away from being stock.
            'status' => ['required', Rule::enum(PurchaseStatus::class)],

            // How the supplier was paid, and out of which account. The same
            // pair every other document that names a bank carries, read through
            // the same concern so card and transfer require one here too.
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_id' => $this->bankRules(),

            'notes' => ['nullable', 'string', 'max:2000'],

            // What the invoice was written in. Every amount below defaults to it
            // and may override it on its own.
            'currency' => $currency,

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id'), 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.unit_cost_currency' => $currency,
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount_currency' => $currency,

            'additional_costs' => ['array'],
            'additional_costs.*.label' => ['required', 'string', 'max:100'],
            'additional_costs.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'additional_costs.*.amount_currency' => $currency,
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
            'currency.in' => 'There is no exchange rate on record for that currency.',
            ...$this->referenceMessages('invoice'),
            ...$this->bankMessages(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->trimReference();
    }

    /**
     * An invoice converts at the rate in force on its own date, not today's, so
     * entering last month's paperwork gives last month's cost.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('invoiced_on');
    }

    /**
     * Named `invoiceHeader` to avoid colliding with Request::header().
     *
     * @return array{number?: string, invoiced_on: string, status: PurchaseStatus, payment_method: string, bank_id: int|null, notes: string|null, currency: string, exchange_rate: int}
     */
    public function invoiceHeader(): array
    {
        return [
            // Left out entirely when blank, so the action can tell "file it
            // under this" from "whatever it is filed under now".
            ...($this->reference() === null ? [] : ['number' => $this->reference()]),
            'invoiced_on' => $this->date('invoiced_on')->toDateString(),
            'status' => $this->enum('status', PurchaseStatus::class) ?? PurchaseStatus::Ordered,
            'payment_method' => $this->string('payment_method')->toString(),
            'bank_id' => $this->bankId(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
        ];
    }

    /**
     * Costs in the base currency, whatever they were typed in.
     *
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
                'unit_cost' => $this->baseMoneyIn($line, 'unit_cost'),
                'discount' => $this->baseMoneyIn($line, 'discount'),
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
                'amount' => $this->baseMoneyIn($cost, 'amount'),
                'allocation_method' => (string) $cost['allocation_method'],
            ];
        }

        return $costs;
    }
}
