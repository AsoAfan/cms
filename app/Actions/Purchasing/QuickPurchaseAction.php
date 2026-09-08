<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseLedgerException;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;

/**
 * Buying one product in one step, from the catalogue screen.
 *
 * This is not a shortcut around the invoice: it writes a real purchase with a
 * single line, which then runs ordered → on the way → proceed like any other.
 *
 * **It lands at `ordered`, so it moves no stock.** Ordering from the catalogue
 * is placing an order, and goods somebody has promised are not goods on the
 * shelf — the invoice screen is where it is marked arrived, and that is what
 * takes them in. There are no additional costs on one of these, so the landed
 * cost is the line total and nothing needs spreading.
 */
final class QuickPurchaseAction
{
    public function __construct(private readonly SavePurchaseAction $save) {}

    /**
     * @param  string  $unitCost  A decimal string in the BASE currency, e.g. '23760.00'.
     * @param  string  $invoicedOn  A Y-m-d date.
     * @param  string  $currency  What was actually paid in, recorded on the invoice.
     * @param  int  $exchangeRate  The scaled rate it was converted at — see App\Support\ExchangeRates.
     *
     * @throws PurchaseLedgerException
     */
    public function handle(
        Product $product,
        int $quantity,
        string $unitCost,
        string $invoicedOn,
        ?string $currency = null,
        ?int $exchangeRate = null,
    ): Purchase {
        return $this->save->handle(
            header: [
                'invoiced_on' => $invoicedOn,
                'status' => PurchaseStatus::Ordered,
                'notes' => null,
                'currency' => $currency ?? app(CurrencyService::class)->base(),
                'exchange_rate' => $exchangeRate ?? ExchangeRates::SCALE,
            ],
            lines: [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'discount' => '0',
            ]],
            costs: [],
        );
    }
}
