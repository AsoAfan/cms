<?php

namespace App\Actions\Purchasing;

use App\Exceptions\PurchaseNotPostableException;
use App\Models\Product;
use App\Models\Purchase;
use App\Support\ExchangeRates;
use Illuminate\Support\Facades\DB;

/**
 * Buying one product in one step, from the catalogue screen.
 *
 * This is not a shortcut around the invoice: it writes a real purchase with a
 * single line and posts it, so the goods land on the shelf through exactly the
 * same path a typed-up supplier invoice takes. There are no additional costs,
 * so the landed cost is the line total and nothing needs spreading.
 *
 * Save and post share one transaction. A post that fails must not leave a
 * mystery draft behind that nobody asked for.
 */
final class QuickPurchaseAction
{
    public function __construct(
        private readonly SavePurchaseAction $save,
        private readonly PostPurchaseAction $post,
    ) {}

    /**
     * @param  int|null  $supplierId  Who it was bought from, if that was worth recording.
     * @param  string  $unitCost  A decimal string in the BASE currency, e.g. '23760.00'.
     * @param  string  $invoicedOn  A Y-m-d date.
     * @param  string  $currency  What was actually paid in, recorded on the invoice.
     * @param  int  $exchangeRate  The scaled rate it was converted at — see App\Support\ExchangeRates.
     *
     * @throws PurchaseNotPostableException
     */
    public function handle(
        Product $product,
        ?int $supplierId,
        int $quantity,
        string $unitCost,
        string $invoicedOn,
        ?string $currency = null,
        ?int $exchangeRate = null,
    ): Purchase {
        $currency ??= (string) config('money.currency');
        $exchangeRate ??= ExchangeRates::SCALE;

        return DB::transaction(function () use ($product, $supplierId, $quantity, $unitCost, $invoicedOn, $currency, $exchangeRate): Purchase {
            $purchase = $this->save->handle(
                header: [
                    'supplier_id' => $supplierId,
                    'invoiced_on' => $invoicedOn,
                    'notes' => null,
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                ],
                lines: [[
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'discount' => '0',
                ]],
                costs: [],
            );

            return $this->post->handle($purchase);
        });
    }
}
