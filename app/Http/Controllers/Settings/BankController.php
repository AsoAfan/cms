<?php

namespace App\Http\Controllers\Settings;

use App\Enums\BankAdjustmentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BankRequest;
use App\Models\Bank;
use App\Models\BankAdjustment;
use App\Models\BankTransfer;
use App\Queries\BankBalanceQuery;
use App\Support\Flash;
use App\Support\Money;
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
 *
 * Each account also carries what it holds. That figure is never stored:
 * `BankBalanceQuery` derives it from everything that moved through the account,
 * and the manual movements listed underneath are the part of it no document
 * explains — the balance it opened with, cash deposited, interest, charges.
 */
class BankController extends Controller
{
    /** How many hand-written movements the screen lists, both kinds together. */
    private const int RECENT_MOVEMENTS = 25;

    public function __construct(private readonly BankBalanceQuery $balances) {}

    public function index(): Response
    {
        $banks = Bank::query()
            ->withCount([
                'sales', 'purchases', 'expenses', 'customerPayments',
                'adjustments', 'transfersOut', 'transfersIn',
            ])
            ->orderBy('name')
            ->get();

        $balances = $this->balances->get();

        return Inertia::render('settings/banks', [
            'banks' => $banks->map(fn (Bank $bank): array => [
                'id' => $bank->id,
                'name' => $bank->name,
                'account_number' => $bank->account_number,
                'notes' => $bank->notes,
                'sales_count' => $bank->sales_count,
                'purchases_count' => $bank->purchases_count,
                'expenses_count' => $bank->expenses_count,
                'payments_count' => $bank->customer_payments_count,
                'adjustments_count' => $bank->adjustments_count,
                // Both directions together: the screen only asks whether money
                // has moved between accounts, not which way.
                'transfers_count' => $bank->transfers_out_count + $bank->transfers_in_count,
                // Base-currency minor units, like every figure on the wire.
                'balance' => ($balances[$bank->id] ?? Money::zero())->minorUnits,
            ])->all(),
            'balanceTotal' => $this->balances->total($balances)->minorUnits,
            'movements' => $this->movements(),
            'directions' => BankAdjustmentDirection::options(),
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

    /**
     * Everything moved by hand behind the balances, newest first.
     *
     * The two kinds are normalised into one row shape — as `ActivityQuery`
     * does for documents — because somebody checking a figure wants one
     * history, not two lists to read in parallel.
     *
     * Capped rather than paginated: this is the recent history a balance is
     * checked against, and an account with hundreds of hand-written movements
     * is an account whose trade should be recorded as documents instead.
     *
     * @return list<array<string, mixed>>
     */
    private function movements(): array
    {
        $adjustments = BankAdjustment::query()
            ->with('bank:id,name')
            ->latest('occurred_on')
            ->latest('id')
            ->limit(self::RECENT_MOVEMENTS)
            ->get()
            ->map(fn (BankAdjustment $adjustment): array => [
                'kind' => 'adjustment',
                'id' => $adjustment->id,
                'label' => $adjustment->reason,
                'detail' => $adjustment->bank->name,
                // Signed minor units: negative is money out, and the screen
                // reads the direction off the sign rather than a second field
                // that could disagree with it.
                'amount' => $adjustment->amount->minorUnits,
                'occurred_on' => $adjustment->occurred_on->toDateString(),
            ]);

        $transfers = BankTransfer::query()
            ->with(['fromBank:id,name', 'toBank:id,name'])
            ->latest('occurred_on')
            ->latest('id')
            ->limit(self::RECENT_MOVEMENTS)
            ->get()
            ->map(fn (BankTransfer $transfer): array => [
                'kind' => 'transfer',
                'id' => $transfer->id,
                'label' => $transfer->reason ?? 'Moved between accounts',
                'detail' => $transfer->route(),
                // Never signed: a transfer takes nothing out of the business,
                // so the figure belongs to the pair rather than to one side.
                'amount' => $transfer->amount->minorUnits,
                'occurred_on' => $transfer->occurred_on->toDateString(),
            ]);

        return array_values(
            $adjustments
                ->concat($transfers)
                ->sortByDesc(fn (array $movement): string => $movement['occurred_on'].str_pad(
                    (string) $movement['id'], 10, '0', STR_PAD_LEFT
                ))
                ->take(self::RECENT_MOVEMENTS)
                ->values()
                ->all()
        );
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        // A bank with money against it is never deleted and never quietly
        // detached from its history — the database refuses it anyway, so say so
        // plainly rather than letting the FK surface as a 500.
        if ($bank->isInUse()) {
            Flash::error("{$bank->name} has money recorded against it, so it cannot be removed.");

            return back();
        }

        $bank->delete();

        Flash::success("{$bank->name} removed.");

        return back();
    }
}
