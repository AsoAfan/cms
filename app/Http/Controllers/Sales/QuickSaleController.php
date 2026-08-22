<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\QuickSaleAction;
use App\Exceptions\SaleLedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\QuickSaleRequest;
use App\Models\Product;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Sell one product straight from the catalogue. Writes an ordinary sale at
 * `ordered`, so everything downstream sees an ordinary sale — including the
 * fact that its goods have not left and its money has not arrived.
 */
class QuickSaleController extends Controller
{
    public function __invoke(
        QuickSaleRequest $request,
        Product $product,
        QuickSaleAction $sell,
    ): RedirectResponse {
        try {
            $sale = $sell->handle(
                product: $product,
                quantity: $request->quantity(),
                unitPrice: $request->unitPrice(),
                paymentMethod: $request->paymentMethod(),
                soldOn: $request->soldOn(),
                bankId: $request->bankId(),
                customerId: $request->customerId(),
                amountPaid: $request->amountPaid(),
                currency: $request->currency(),
                exchangeRate: $request->exchangeRate(),
            );
        } catch (SaleLedgerException $exception) {
            // Being short is a problem with the quantity that was typed, so it
            // belongs on that field where it can be corrected, not in a toast
            // that closes the dialog and loses the rest of the entry.
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        // Says what was written AND what it did not do: the stock is still on
        // the shelf and nothing has been taken, and both happen on the sale.
        Flash::success(sprintf(
            'Ordered %d × %s on %s. Stock leaves when you mark it On the way.',
            $request->quantity(),
            $product->name,
            $sale->number,
        ));

        return back();
    }
}
