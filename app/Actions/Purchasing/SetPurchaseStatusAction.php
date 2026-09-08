<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseLedgerException;
use App\Exceptions\StockAlreadyConsumedException;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

/**
 * Moves an invoice along — ordered, on its way, arrived — and brings the stock
 * ledger with it.
 *
 * The rule is the whole of the class: after this runs, the goods are in stock
 * if and only if the status says they are here. Which direction the status
 * moved does not matter, so the same call handles marking a delivery arrived
 * and un-marking one that never did.
 */
final class SetPurchaseStatusAction
{
    public function __construct(
        private readonly ReceivePurchaseAction $receive,
        private readonly RevertPurchaseAction $revert,
    ) {}

    /**
     * @throws PurchaseLedgerException when the goods cannot be taken in
     * @throws StockAlreadyConsumedException when they cannot be taken back out
     */
    public function handle(Purchase $purchase, PurchaseStatus $status): Purchase
    {
        return DB::transaction(function () use ($purchase, $status): Purchase {
            $purchase->forceFill(['status' => $status])->save();

            return $status->holdsStock()
                ? $this->receive->handle($purchase)
                : $this->revert->handle($purchase);
        });
    }
}
