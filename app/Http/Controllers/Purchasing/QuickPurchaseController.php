<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\QuickPurchaseAction;
use App\Exceptions\PurchaseLedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\QuickPurchaseRequest;
use App\Models\Product;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Order one product straight from the catalogue. Writes an ordinary invoice at
 * `ordered`, so everything downstream sees an ordinary purchase — including
 * the fact that its goods are not on the shelf yet.
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
                quantity: $request->quantity(),
                unitCost: $request->unitCost(),
                invoicedOn: $request->invoicedOn(),
                currency: $request->currency(),
                exchangeRate: $request->exchangeRate(),
            );
        } catch (PurchaseLedgerException $exception) {
            Flash::error($exception->getMessage());

            return back();
        }

        // Says what was written AND what it did not do: somebody who expected
        // the shelf to go up by ten needs to know it will when they mark the
        // invoice arrived, not wonder why it did not.
        Flash::success(sprintf(
            'Ordered %d × %s on %s. Stock arrives when you mark it Proceed.',
            $request->quantity(),
            $product->name,
            $purchase->number,
        ));

        return back();
    }
}
