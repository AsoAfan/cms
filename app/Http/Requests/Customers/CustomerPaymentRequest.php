<?php

namespace App\Http\Requests\Customers;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Http\Requests\Concerns\NamesPayingBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerPaymentRequest extends FormRequest
{
    use ConvertsToBaseCurrency;
    use NamesPayingBank;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The shape of a payment: how much came in, when, how, and which invoices it
     * settles. Whether those parts add up — and whether each invoice can take
     * what is aimed at it — is business, and belongs to
     * `RecordCustomerPaymentAction`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currency = ['nullable', Rule::in($this->enterableCurrencies())];

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'amount_currency' => $currency,
            'currency' => $currency,
            'received_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_id' => $this->bankRules(),
            'notes' => ['nullable', 'string', 'max:2000'],

            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sale_id' => ['required', 'integer', Rule::exists('sales', 'id'), 'distinct'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'allocations.*.amount_currency' => $currency,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'A payment has to be more than nothing.',
            'allocations.required' => 'Say which invoices this payment settles.',
            'allocations.min' => 'Say which invoices this payment settles.',
            'allocations.*.sale_id.distinct' => 'That invoice is on this payment twice.',
            'amount_currency.in' => 'There is no exchange rate on record for that currency.',
            ...$this->bankMessages(),
        ];
    }

    /**
     * A payment converts at the rate in force on the day the money came in, so a
     * receipt typed up late still costs at the rate of its own day.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('received_on');
    }

    /**
     * The payment itself, in base-currency minor units.
     *
     * @return array{amount: string, received_on: string, payment_method: string, bank_id: int|null, currency: string, exchange_rate: int, notes: string|null}
     */
    public function payment(): array
    {
        return [
            'amount' => (string) $this->baseMoney('amount', '0'),
            'received_on' => $this->date('received_on')->toDateString(),
            'payment_method' => $this->string('payment_method')->toString(),
            'bank_id' => $this->bankId(),
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }

    /**
     * What is applied to each invoice, keyed by sale id, in the base currency.
     *
     * Blank rows come back as zero and the action drops them: the dialog offers
     * every open invoice, and leaving most of them empty is how someone says
     * which one they are paying.
     *
     * @return array<int, string>
     */
    public function allocations(): array
    {
        $allocations = [];

        foreach ($this->array('allocations') as $allocation) {
            $allocation = (array) $allocation;

            $allocations[(int) $allocation['sale_id']] = $this->baseMoneyIn($allocation, 'amount');
        }

        return $allocations;
    }
}
