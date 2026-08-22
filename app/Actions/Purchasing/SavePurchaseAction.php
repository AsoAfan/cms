<?php

namespace App\Actions\Purchasing;

use App\Enums\CostAllocationMethod;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseLedgerException;
use App\Exceptions\StockAlreadyConsumedException;
use App\Models\Purchase;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an invoice with its lines and additional costs.
 *
 * Lines and costs are replaced wholesale rather than diffed. They are the
 * invoice as typed, and rewriting them cannot leave a half-updated document
 * behind the way a partial diff can.
 *
 * An invoice whose goods have already arrived can still be edited — a typo on
 * a delivered order is exactly the thing that most needs fixing. Its stock is
 * taken back out before the rewrite and put back afterwards, all in one
 * transaction, so what lands in the ledger is the corrected invoice and never
 * a mix of the two. If any of the goods have been sold on in the meantime the
 * rollback refuses, and the edit fails with them.
 */
final class SavePurchaseAction
{
    public function __construct(
        private readonly ReceivePurchaseAction $receive,
        private readonly RevertPurchaseAction $revert,
    ) {}

    /**
     * Every amount here is already in the base currency — the Form Request is the
     * one place that converts. `currency` and `exchange_rate` on the header record
     * what the invoice was written in and at what rate, and are the only currency
     * this action knows about.
     *
     * @param  array{invoiced_on: string, status: PurchaseStatus, notes: string|null, currency?: string, exchange_rate?: int}  $header
     * @param  list<array{product_id: int, quantity: int, unit_cost: string, discount: string}>  $lines
     * @param  list<array{label: string, amount: string, allocation_method: string}>  $costs
     *
     * @throws PurchaseLedgerException
     * @throws StockAlreadyConsumedException
     */
    public function handle(array $header, array $lines, array $costs, ?Purchase $purchase = null): Purchase
    {
        return DB::transaction(function () use ($header, $lines, $costs, $purchase): Purchase {
            $purchase ??= new Purchase(['number' => Purchase::nextNumber()]);

            if ($purchase->exists) {
                $this->revert->handle($purchase);
            }

            $purchase->fill($header)->save();

            $purchase->lines()->delete();
            $purchase->additionalCosts()->delete();

            foreach ($lines as $line) {
                $purchase->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => Money::fromDecimal($line['unit_cost']),
                    'discount' => Money::fromDecimal($line['discount']),
                ]);
            }

            foreach ($costs as $cost) {
                $purchase->additionalCosts()->create([
                    'label' => $cost['label'],
                    'amount' => Money::fromDecimal($cost['amount']),
                    'allocation_method' => CostAllocationMethod::from($cost['allocation_method']),
                ]);
            }

            $purchase->refresh();

            return $purchase->shouldHoldStock()
                ? $this->receive->handle($purchase)
                : $purchase;
        });
    }
}
