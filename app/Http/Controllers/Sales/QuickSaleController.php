<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\QuickSaleAction;
use App\Exceptions\SaleNotPostableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\QuickSaleRequest;
use App\Models\Product;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Sell one product straight from the catalogue. Writes a posted sale, so the
 * stock goes out FIFO and the cost of it is a fact like any other sale's.
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
                currency: $request->currency(),
                exchangeRate: $request->exchangeRate(),
            );
        } catch (SaleNotPostableException $exception) {
            // Being short is a problem with the quantity that was typed, so it
            // belongs on that field where it can be corrected, not in a toast
            // that closes the dialog and loses the rest of the entry.
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        Flash::success(sprintf(
            'Sold %d × %s on %s.',
            $request->quantity(),
            $product->name,
            $sale->number,
        ));

        return back();
    }
}
