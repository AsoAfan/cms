<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BankAdjustmentRequest;
use App\Models\Bank;
use App\Models\BankAdjustment;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/**
 * Setting an account's balance by hand.
 *
 * Trade explains most of what an account holds, and `BankBalanceQuery` derives
 * that. It cannot know the balance the account already had on the day the
 * business started using this system, nor cash walked to the bank, interest or
 * charges — so this is where those go, one dated row at a time.
 *
 * Plain Eloquent rather than an Action: this writes one row and touches nothing
 * else, exactly like a product. The arithmetic is all in the query that reads
 * these back.
 *
 * There is no update. A movement is what happened to the account, so a wrong
 * one is deleted and recorded again — the same treatment a customer repayment
 * gets, and for the same reason.
 */
class BankAdjustmentController extends Controller
{
    public function store(BankAdjustmentRequest $request, Bank $bank): RedirectResponse
    {
        $adjustment = $bank->adjustments()->create($request->payload());

        Flash::success($adjustment->amount->isNegative()
            ? "Taken off {$bank->name}."
            : "Added to {$bank->name}.");

        return back();
    }

    public function destroy(BankAdjustment $adjustment): RedirectResponse
    {
        $adjustment->delete();

        Flash::success('Balance movement removed.');

        return back();
    }
}
