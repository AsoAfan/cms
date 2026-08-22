<?php

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\SaleLedgerException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CurrencyService;
use App\Support\ExchangeRates;

/**
 * Selling one product in one step, from the catalogue screen.
 *
 * A real sale with a single line, which then runs ordered → on the way →
 * proceed like any other.
 *
 * **It lands at `ordered`, so it moves no stock and takes no money.** What the
 * catalogue records is that somebody asked for the goods; the sale screen is
 * where it is sent out — which is what issues the stock FIFO — and where what
 * the customer handed over is recorded. Booking it paid in full here would say
 * the money arrived for goods still on the shelf.
 */
final class QuickSaleAction
{
    public function __construct(private readonly SaveSaleAction $save) {}

    /**
     * @param  string  $unitPrice  A decimal string in the BASE currency, e.g. '58080.00'.
     * @param  string  $soldOn  A Y-m-d date.
     * @param  int|null  $bankId  The account the money will come into — null on cash.
     * @param  string|null  $amountPaid  Anything taken up front. Nothing, unless the dialog says otherwise.
     * @param  string  $currency  What the customer is paying in, recorded on the sale.
     * @param  int  $exchangeRate  The scaled rate it was converted at — see App\Support\ExchangeRates.
     *
     * @throws SaleLedgerException
     */
    public function handle(
        Product $product,
        int $quantity,
        string $unitPrice,
        string $paymentMethod,
        string $soldOn,
        ?int $bankId = null,
        ?int $customerId = null,
        ?string $amountPaid = null,
        ?string $currency = null,
        ?int $exchangeRate = null,
    ): Sale {
        return $this->save->handle(
            header: [
                // Counter trade goes to the walk-in customer unless somebody is
                // named — every sale has a buyer, and this one asks for nothing.
                'customer_id' => $customerId ?? Customer::walkIn()->id,
                'sold_on' => $soldOn,
                'status' => SaleStatus::Ordered,
                'payment_method' => $paymentMethod,
                'bank_id' => $bankId,
                // Nothing has been handed over on an order. Money against goods
                // still on the shelf is a deposit, and only the sale screen has
                // room to say one was taken.
                'amount_paid' => $amountPaid ?? '0',
                'notes' => null,
                'currency' => $currency ?? app(CurrencyService::class)->base(),
                'exchange_rate' => $exchangeRate ?? ExchangeRates::SCALE,
            ],
            lines: [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => '0',
            ]],
        );
    }
}
