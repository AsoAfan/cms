<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\QuickPurchaseAction;
use App\Exceptions\PurchaseNotPostableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\QuickPurchaseRequest;
use App\Models\Product;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Buy one product straight from the catalogue. Writes a posted invoice, so
 * everything downstream sees an ordinary purchase.
 */
class QuickPurchaseController extends Controller
{
    public function __invoke(
        QuickPurchaseRequest $request,
        Product $product,
        QuickPurchaseAction $buy,
    ): RedirectResponse {
        try {
            $purchase = $buy->handle(
                product: $product,
                supplierId: $request->supplierId(),
                quantity: $request->quantity(),
                unitCost: $request->unitCost(),
                invoicedOn: $request->invoicedOn(),
                currency: $request->currency(),
                exchangeRate: $request->exchangeRate(),
            );
        } catch (PurchaseNotPostableException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        Flash::success(sprintf(
            'Bought %d × %s on %s.',
            $request->quantity(),
            $product->name,
            $purchase->number,
        ));

        return back();
    }
}
