<?php

namespace App\Actions\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\SaleLedgerException;
use App\Models\Sale;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a sale with its lines.
 *
 * Lines are replaced wholesale rather than diffed: they are the sale as rung
 * up, and rewriting them cannot leave half a sale behind the way a partial
 * diff can.
 *
 * A sale that has already gone out can still be corrected. Its stock is put
 * back on the shelf before the rewrite and taken out again afterwards, in one
 * transaction, so the ledger ends up matching the corrected sale exactly — and
 * an edit that would leave the shop short of stock fails whole rather than
 * halfway.
 */
final class SaveSaleAction
{
    public function __construct(
        private readonly IssueSaleAction $issue,
        private readonly RevertSaleAction $revert,
    ) {}

    /**
     * Every amount here is already in the base currency — the Form Request is the
     * one place that converts. `currency` and `exchange_rate` on the header record
     * what the money changed hands in and at what rate.
     *
     * @param  array{customer_id: int, sold_on: string, status: SaleStatus, payment_method: string, bank_id?: int|null, amount_paid?: string, notes: string|null, currency?: string, exchange_rate?: int}  $header
     * @param  list<array{product_id: int, quantity: int, unit_price: string, discount: string}>  $lines
     *
     * @throws SaleLedgerException
     */
    public function handle(array $header, array $lines, ?Sale $sale = null): Sale
    {
        return DB::transaction(function () use ($header, $lines, $sale): Sale {
            $sale ??= new Sale(['number' => Sale::nextNumber()]);

            if ($sale->exists) {
                $this->revert->handle($sale);
            }

            $sale->fill([
                'customer_id' => $header['customer_id'],
                'sold_on' => $header['sold_on'],
                'status' => $header['status'],
                'payment_method' => PaymentMethod::from($header['payment_method']),
                // Which account took the money. Null on cash, and the Form
                // Request is what makes sure it is not null on anything else.
                'bank_id' => $header['bank_id'] ?? null,
                // What was handed over at the time. Whatever is short of the
                // total is the customer's loan against this invoice, and it is
                // derived from here — never stored as a balance.
                'amount_paid' => Money::fromDecimal($header['amount_paid'] ?? '0'),
                'notes' => $header['notes'],
                'currency' => $header['currency'] ?? app(CurrencyService::class)->base(),
                'exchange_rate' => $header['exchange_rate'] ?? ExchangeRates::SCALE,
            ])->save();

            $sale->lines()->delete();

            foreach ($lines as $line) {
                $sale->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::fromDecimal($line['unit_price']),
                    'discount' => Money::fromDecimal($line['discount']),
                ]);
            }

            $sale->refresh();

            return $sale->shouldReleaseStock()
                ? $this->issue->handle($sale)
                : $sale;
        });
    }
}
