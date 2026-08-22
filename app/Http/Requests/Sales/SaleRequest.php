<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Requests\Concerns\ConvertsToBaseCurrency;
use App\Http\Requests\Concerns\NamesPayingBank;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
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
            'sold_on' => ['required', 'date'],

            // Every sale names a buyer. Counter trade is the walk-in customer's,
            // which is what the form opens on, so this costs no keystrokes.
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],

            // Where the order stands. Set on the way in, so a sale handed over
            // at the counter is one save away from being done with.
            'status' => ['required', Rule::enum(SaleStatus::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],

            // Which account the money came into. Required on card and transfer,
            // refused on cash — see `NamesPayingBank`.
            'bank_id' => $this->bankRules(),

            // Paid in full is the ordinary case, and the amount then comes from
            // the lines rather than the client — exact, and nothing to convert.
            'paid_in_full' => ['boolean'],

            // What they handed over now. Anything short of the total is their
            // loan against this invoice, and `after()` refuses more than it.
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'amount_paid_currency' => $currency,

            'notes' => ['nullable', 'string', 'max:2000'],

            // What the customer actually handed over. Dinars mostly, dollars
            // sometimes — that is the fact this records.
            'currency' => $currency,

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id'), 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.unit_price_currency' => $currency,
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount_currency' => $currency,
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
            'currency.in' => 'There is no exchange rate on record for that currency.',
            'customer_id.required' => 'Say who bought it.',
            ...$this->bankMessages(),
        ];
    }

    /**
     * Nobody can pay more than the invoice comes to.
     *
     * Overpaying would put a customer's balance below zero, and a negative debt
     * is not a thing this application has any way to describe. Checked here
     * rather than in the action because the total is the lines that arrived with
     * it, and this is the layer that has them.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->boolean('paid_in_full')) {
                    return;
                }

                $paid = Money::fromDecimal($this->baseMoney('amount_paid', '0') ?? '0');
                $total = $this->linesTotal();

                if ($paid->isGreaterThan($total)) {
                    $validator->errors()->add(
                        'amount_paid',
                        "This sale comes to {$total->toDecimal()}. Nobody can pay more than that.",
                    );
                }
            },
        ];
    }

    /**
     * What the lines come to, in the base currency.
     *
     * The invoice total is never stored — it is the sum of its parts — so both
     * the overpayment check and "paid in full" work it out from the lines that
     * arrived with the request.
     */
    private function linesTotal(): Money
    {
        $total = Money::zero();

        foreach ($this->lines() as $line) {
            $total = $total->plus(
                Money::fromDecimal($line['unit_price'])
                    ->multipliedBy($line['quantity'])
                    ->minus(Money::fromDecimal($line['discount']))
            );
        }

        return $total;
    }

    /**
     * A sale converts at the rate in force on the day it was sold, so a sale
     * entered late is still costed at the rate of the day the money changed
     * hands.
     */
    protected function currencyDate(): ?string
    {
        return $this->dateOrNull('sold_on');
    }

    /**
     * Named `saleHeader` to avoid colliding with Request::header().
     *
     * @return array{customer_id: int, sold_on: string, status: SaleStatus, payment_method: string, bank_id: int|null, amount_paid: string, notes: string|null, currency: string, exchange_rate: int}
     */
    public function saleHeader(): array
    {
        return [
            'customer_id' => $this->integer('customer_id'),
            'sold_on' => $this->date('sold_on')->toDateString(),
            'status' => $this->enum('status', SaleStatus::class) ?? SaleStatus::Ordered,
            'payment_method' => $this->string('payment_method')->toString(),
            'bank_id' => $this->bankId(),
            // Paid in full is settled from the lines, so the stored figure is
            // exactly the invoice and never a client's rounding of it.
            'amount_paid' => $this->boolean('paid_in_full')
                ? $this->linesTotal()->toDecimal()
                : (string) ($this->baseMoney('amount_paid', '0') ?? '0'),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'currency' => $this->documentCurrency(),
            'exchange_rate' => $this->documentRate(),
        ];
    }

    /**
     * Prices in the base currency, whatever they were typed in.
     *
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
                'unit_price' => $this->baseMoneyIn($line, 'unit_price'),
                'discount' => $this->baseMoneyIn($line, 'discount'),
            ];
        }

        return $lines;
    }
}
