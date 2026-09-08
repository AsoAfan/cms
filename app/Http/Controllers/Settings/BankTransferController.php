<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BankTransferRequest;
use App\Models\BankTransfer;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Moving money between the business's own accounts.
 *
 * Not income, not outcome, and nothing in reporting counts it: the money never
 * left the business. It moves one balance down and another up by the same
 * figure, so the total across the accounts is exactly what it was.
 *
 * Plain Eloquent rather than an Action, and one row rather than a pair of
 * adjustments: both sides of the transfer ARE the row, so there is no
 * multi-step write to wrap in a transaction and no way to half-write one.
 *
 * There is no update, as with a repayment or a hand-written movement. A wrong
 * transfer is deleted — which unwinds both accounts at once — and recorded
 * again.
 */
class BankTransferController extends Controller
{
    public function store(BankTransferRequest $request): RedirectResponse
    {
        $transfer = BankTransfer::query()->create($request->payload());

        $transfer->load(['fromBank:id,name', 'toBank:id,name']);

        Flash::success("Moved {$transfer->fromBank->name} → {$transfer->toBank->name}.");

        return back();
    }

    public function destroy(BankTransfer $transfer): RedirectResponse
    {
        $transfer->delete();

        Flash::success('Transfer removed. Both balances are back as they were.');

        return back();
    }
}
