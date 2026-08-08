<?php

namespace App\Actions\Sales;

use App\Exceptions\SaleNotPostableException;
use App\Models\Product;
use App\Models\Sale;
use App\Support\ExchangeRates;
use Illuminate\Support\Facades\DB;

/**
 * Selling one product in one step, from the catalogue screen.
 *
 * A real sale with a single line, posted immediately, so the stock goes out
 * through the same FIFO issue as any other sale and the cost of it is recorded
 * against the batches that actually paid.
 *
 * Save and post share one transaction, so a sale short of stock leaves nothing
 * behind — the shortage is reported and the draft never existed.
 */
final class QuickSaleAction
{
    public function __construct(
        private readonly SaveSaleAction $save,
        private readonly PostSaleAction $post,
    ) {}

    /**
     * @param  string  $unitPrice  A decimal string in the BASE currency, e.g. '58080.00'.
     * @param  string  $soldOn  A Y-m-d date.
     * @param  string  $currency  What the customer handed over, recorded on the sale.
     * @param  int  $exchangeRate  The scaled rate it was converted at — see App\Support\ExchangeRates.
     *
     * @throws SaleNotPostableException
     */
    public function handle(
        Product $product,
        int $quantity,
        string $unitPrice,
        string $paymentMethod,
        string $soldOn,
        ?string $currency = null,
        ?int $exchangeRate = null,
    ): Sale {
        $currency ??= (string) config('money.currency');
        $exchangeRate ??= ExchangeRates::SCALE;

        return DB::transaction(function () use ($product, $quantity, $unitPrice, $paymentMethod, $soldOn, $currency, $exchangeRate): Sale {
            $sale = $this->save->handle(
                header: [
                    'sold_on' => $soldOn,
                    'payment_method' => $paymentMethod,
                    'notes' => null,
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                ],
                lines: [[
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => '0',
                ]],
            );

            return $this->post->handle($sale);
        });
    }
}
