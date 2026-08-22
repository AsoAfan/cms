<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\SaleLedgerException;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Moves a sale along — asked for, sent out, received — and brings the stock
 * ledger with it.
 *
 * After this runs, the goods are off the shelf if and only if the sale says
 * they have left the shop. Marking a sale on its way issues the stock; moving
 * it back to `ordered` puts it back; `proceed` only records that the customer
 * has it, and moves nothing either way.
 */
final class SetSaleStatusAction
{
    public function __construct(
        private readonly IssueSaleAction $issue,
        private readonly RevertSaleAction $revert,
    ) {}

    /**
     * @throws SaleLedgerException when there is not enough on the shelf
     */
    public function handle(Sale $sale, SaleStatus $status): Sale
    {
        return DB::transaction(function () use ($sale, $status): Sale {
            $sale->forceFill(['status' => $status])->save();

            return $status->releasesStock()
                ? $this->issue->handle($sale)
                : $this->revert->handle($sale);
        });
    }
}
