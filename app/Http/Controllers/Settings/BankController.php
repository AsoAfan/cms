<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BankRequest;
use App\Models\Bank;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The accounts non-cash money moves through.
 *
 * Configuration rather than trade, which is why it sits in Settings beside the
 * currencies: a business sets its accounts up once and then names one on every
 * card or transfer it records.
 *
 * The list is short by nature — a business holds a handful of accounts, not a
 * page of them — so it is sent whole rather than through `InteractsWithTables`.
 * The counts beside each are how many documents would be orphaned by removing
 * it, which is the only question this screen is asked about a bank.
 */
class BankController extends Controller
{
    public function index(): Response
    {
        $banks = Bank::query()
            ->withCount(['sales', 'expenses', 'customerPayments'])
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/banks', [
            'banks' => $banks->map(fn (Bank $bank): array => [
                'id' => $bank->id,
                'name' => $bank->name,
                'account_number' => $bank->account_number,
                'notes' => $bank->notes,
                'sales_count' => $bank->sales_count,
                'expenses_count' => $bank->expenses_count,
                'payments_count' => $bank->customer_payments_count,
            ])->all(),
        ]);
    }

    public function store(BankRequest $request): RedirectResponse
    {
        $bank = Bank::query()->create($request->payload());

        Flash::success("{$bank->name} added. It can be named on card and transfer payments now.");

        return back();
    }

    public function update(BankRequest $request, Bank $bank): RedirectResponse
    {
        $bank->update($request->payload());

        Flash::success("{$bank->name} updated.");

        return back();
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        // A bank with money against it is never deleted and never quietly
        // detached from its history — the database refuses it anyway, so say so
        // plainly rather than letting the FK surface as a 500.
        if ($bank->isInUse()) {
            Flash::error("{$bank->name} has payments against it, so it cannot be removed.");

            return back();
        }

        $bank->delete();

        Flash::success("{$bank->name} removed.");

        return back();
    }
}
