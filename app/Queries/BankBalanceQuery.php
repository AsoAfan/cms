<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\BankAdjustment;
use App\Models\BankTransfer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * What each account holds, derived from everything that moved through it.
 *
 *     balance = money in − money out
 *
 *     in  = taken at the till on delivered sales + repayments received
 *           + manual money in + transfers in from another account
 *     out = invoices paid out of it + expenses paid out of it
 *           + manual money out + transfers out to another account
 *
 * There is no balance column, for the reason there is no stock balance column
 * and no customer balance column: every sale, invoice, expense, repayment and
 * correction moves it, so a stored figure is the one most certain to drift.
 * Summing is always right.
 *
 * **A balance is a position, not a flow.** It is what the account holds now,
 * whatever window a report happens to be showing — which is why this takes no
 * period, and why the report screen shows it beside what customers owe rather
 * than among the period tiles.
 *
 * **Scoped exactly as `CashFlowQuery` is**, and that is the point: a sale
 * counts from `proceed`, an invoice from the status that puts its goods in the
 * ledger, so an account's balance and the report above it can never describe
 * different sets of documents. The trade that comes with it: money transferred
 * to a supplier for an order that has not arrived is not off the balance yet.
 * Recording it as a manual movement is what says otherwise, and the account is
 * then right for the same reason the report is.
 *
 * Trade alone starts every account at zero on the day the business began using
 * this system. `BankAdjustment` is what carries the balance it already had.
 *
 * **A transfer moves a balance but never a total.** `BankTransfer` is one row
 * covering both sides, read here twice — off the account it left and on to the
 * account it reached — so the sum across every account is exactly what it was
 * before the money moved. Nothing in reporting counts a transfer at all: no
 * money entered or left the business.
 */
final class BankBalanceQuery
{
    /**
     * A sale line's takings, matching `CashFlowQuery`.
     */
    private const string PURCHASE_LINE_NET = 'purchase_lines.quantity * purchase_lines.unit_cost - purchase_lines.discount';

    /**
     * What every account holds, keyed by bank id.
     *
     * Accounts nothing has ever moved through are absent rather than zero;
     * callers read through a default, exactly as they do for stock on hand.
     *
     * @return Collection<int, Money>
     */
    public function get(): Collection
    {
        $totals = [];

        foreach ($this->movements() as $movement) {
            foreach ($movement as $bankId => $minorUnits) {
                $totals[$bankId] = ($totals[$bankId] ?? 0) + $minorUnits;
            }
        }

        return (new Collection($totals))->map(
            static fn (int $minorUnits): Money => Money::fromMinorUnits($minorUnits)
        );
    }

    /**
     * What the accounts hold between them — the one figure a report tile shows.
     *
     * Takes the balances back when the caller already has them, so a screen
     * showing both the total and the accounts behind it runs the sums once.
     *
     * @param  Collection<int, Money>|null  $balances
     */
    public function total(?Collection $balances = null): Money
    {
        return Money::sum(...($balances ?? $this->get())->values()->all());
    }

    /**
     * Every source of movement, as bank id => signed minor units.
     *
     * @return list<array<int, int>>
     */
    private function movements(): array
    {
        return [
            $this->takenAtTheTill(),
            $this->repaymentsReceived(),
            $this->manualMovements(),
            $this->transfersIn(),
            $this->outward($this->expensesPaid()),
            $this->outward($this->invoicesPaid()),
            $this->outward($this->transfersOut()),
        ];
    }

    /**
     * What customers handed over at the time of sale, on sales they have
     * received. Money on an undelivered sale is a deposit — the same line
     * `CashFlowQuery::collected()` and `CustomerBalanceQuery` both draw.
     *
     * @return array<int, int>
     */
    private function takenAtTheTill(): array
    {
        return $this->sumByBank(
            Sale::query()
                ->toBase()
                ->where('status', SaleStatus::Proceed->value)
                ->whereNotNull('sales.bank_id'),
            'sales.bank_id',
            'sales.amount_paid',
        );
    }

    /**
     * Repayments against customer loans, whichever invoice they settled.
     *
     * @return array<int, int>
     */
    private function repaymentsReceived(): array
    {
        return $this->sumByBank(
            CustomerPayment::query()->toBase()->whereNotNull('customer_payments.bank_id'),
            'customer_payments.bank_id',
            'customer_payments.amount',
        );
    }

    /**
     * Opening balances and every other movement trade does not explain. The
     * amount is already signed, so this is the one source that needs no
     * direction applied to it.
     *
     * @return array<int, int>
     */
    private function manualMovements(): array
    {
        return $this->sumByBank(
            BankAdjustment::query()->toBase(),
            'bank_adjustments.bank_id',
            'bank_adjustments.amount',
        );
    }

    /**
     * Money that arrived from another of the business's own accounts.
     *
     * @return array<int, int>
     */
    private function transfersIn(): array
    {
        return $this->sumByBank(
            BankTransfer::query()->toBase(),
            'bank_transfers.to_bank_id',
            'bank_transfers.amount',
        );
    }

    /**
     * The other half of the same rows: money that left for another account.
     *
     * Read off the one row rather than a matching second one, which is what
     * makes the two sides impossible to disagree.
     *
     * @return array<int, int>
     */
    private function transfersOut(): array
    {
        return $this->sumByBank(
            BankTransfer::query()->toBase(),
            'bank_transfers.from_bank_id',
            'bank_transfers.amount',
        );
    }

    /**
     * The running costs paid out of each account.
     *
     * @return array<int, int>
     */
    private function expensesPaid(): array
    {
        return $this->sumByBank(
            Expense::query()->toBase()->whereNotNull('expenses.bank_id'),
            'expenses.bank_id',
            'expenses.amount',
        );
    }

    /**
     * Stock paid for out of each account: the goods, plus the freight and duty
     * invoiced with them, on invoices whose goods are in the ledger.
     *
     * A purchase carries no part-payment — an invoice named against an account
     * was paid out of it in full — so this is the whole invoice, which is the
     * same figure the report counts as outcome.
     *
     * @return array<int, int>
     */
    private function invoicesPaid(): array
    {
        $goods = $this->sumByBank(
            PurchaseLine::query()
                ->toBase()
                ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
                ->whereIn('purchases.status', PurchaseStatus::inLedger())
                ->whereNotNull('purchases.bank_id'),
            'purchases.bank_id',
            self::PURCHASE_LINE_NET,
        );

        $additional = $this->sumByBank(
            PurchaseAdditionalCost::query()
                ->toBase()
                ->join('purchases', 'purchases.id', '=', 'purchase_additional_costs.purchase_id')
                ->whereIn('purchases.status', PurchaseStatus::inLedger())
                ->whereNotNull('purchases.bank_id'),
            'purchases.bank_id',
            'purchase_additional_costs.amount',
        );

        foreach ($additional as $bankId => $minorUnits) {
            $goods[$bankId] = ($goods[$bankId] ?? 0) + $minorUnits;
        }

        return $goods;
    }

    /**
     * Money leaving the account, which is the same figure with the other sign.
     *
     * @param  array<int, int>  $totals
     * @return array<int, int>
     */
    private function outward(array $totals): array
    {
        return array_map(static fn (int $minorUnits): int => -$minorUnits, $totals);
    }

    /**
     * `$bankColumn` and `$expression` are literal by contract: every caller
     * passes a class constant or a column written out here, never anything
     * from a request.
     *
     * @param  literal-string  $bankColumn
     * @param  literal-string  $expression
     * @return array<int, int>
     */
    private function sumByBank(Builder $query, string $bankColumn, string $expression): array
    {
        /** @var array<int, int> $totals */
        $totals = $query
            ->groupBy($bankColumn)
            ->selectRaw("{$bankColumn} as bank_id, COALESCE(SUM({$expression}), 0) as total")
            ->pluck('total', 'bank_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $totals;
    }
}
