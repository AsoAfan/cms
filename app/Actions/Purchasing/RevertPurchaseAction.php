<?php

namespace App\Actions\Purchasing;

use App\Exceptions\StockAlreadyConsumedException;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Takes an invoice's goods back out of stock, when it moves off `proceed` or
 * is edited or deleted after arriving.
 *
 * The goods stop existing rather than being written off: a delivery that is
 * being un-marked never arrived, so leaving valued stock behind would overstate
 * inventory by exactly the invoice total.
 *
 * Refused outright once any of it has been sold on — see
 * `StockAlreadyConsumedException`. Idempotent, like its opposite number.
 *
 * @throws StockAlreadyConsumedException
 */
final class RevertPurchaseAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Purchase $purchase): Purchase
    {
        if (! $purchase->isCommitted()) {
            return $purchase;
        }

        $purchase->load('lines');

        return DB::transaction(function () use ($purchase): Purchase {
            $purchase->lines->each(
                fn (PurchaseLine $line) => $this->inventory->rollback($line)
            );

            $purchase->forceFill(['committed_at' => null])->save();

            return $purchase->refresh();
        });
    }
}
