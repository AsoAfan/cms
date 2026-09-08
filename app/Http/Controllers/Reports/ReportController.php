<?php

namespace App\Http\Controllers\Reports;

use App\Http\Concerns\InteractsWithReports;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Queries\ActivityQuery;
use App\Queries\BankBalanceQuery;
use App\Queries\CashFlowQuery;
use App\Queries\CustomerBalanceQuery;
use App\Queries\GoodsOwedQuery;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole reporting section: money in, money out, what is left, and every
 * document those three figures were added up from.
 *
 * Every figure is derived from the transactions in the window, so a report can
 * never disagree with the documents behind it. `Money` serializes to minor
 * units, so the query result is handed to Inertia as it comes back and the
 * frontend formats it.
 *
 * The activity lists are unlimited on purpose — the period is the bound, and a
 * report that showed only the first few would not be a report. They are posted
 * only, matching the totals, so every row on screen is behind the figures
 * above it.
 */
class ReportController extends Controller
{
    use InteractsWithReports;

    public function __construct(
        private readonly CashFlowQuery $cashFlow,
        private readonly ActivityQuery $activity,
        private readonly CustomerBalanceQuery $balances,
        private readonly GoodsOwedQuery $goodsOwed,
        private readonly BankBalanceQuery $bankBalances,
    ) {}

    public function __invoke(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/index', [
            ...$this->periodProps($period),
            'cashFlow' => $this->cashFlow->get($period),
            // The same figures for the stretch immediately before, so each
            // tile can say whether it is up or down on a like-for-like window.
            'previous' => $this->cashFlow->get($period->previous()),
            'activity' => $this->activity->get($period),
            // What customers owe is a position, not a flow: it is what is unpaid
            // today, whatever window the rest of the screen is showing. That is
            // why it sits outside the period query rather than inside it.
            'owed' => $this->balances->total()->minorUnits,
            // The mirror of it: goods sold that are not on the shelf, valued
            // at what they will cost to buy in. A loan the business is carrying
            // rather than one it is owed — see `GoodsOwedQuery`.
            'goodsOwed' => $this->owedInGoods(),
            // What the accounts hold, for the same reason and on the same
            // footing: a balance is what is there now, not what moved in the
            // window.
            'bankBalances' => $this->accountBalances(),
        ]);
    }

    /**
     * Every account and what it holds, plus the one figure for the tile.
     *
     * Accounts with nothing through them are listed at zero rather than left
     * out: an account the user set up and cannot find on the report reads as a
     * bug, and zero is the true answer.
     *
     * @return array{accounts: list<array{id: int, name: string, balance: int}>, total: int}
     */
    private function accountBalances(): array
    {
        $balances = $this->bankBalances->get();

        return [
            'accounts' => array_values(
                Bank::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Bank $bank): array => [
                        'id' => $bank->id,
                        'name' => $bank->name,
                        'balance' => ($balances[$bank->id] ?? Money::zero())->minorUnits,
                    ])
                    ->all()
            ),
            'total' => $this->bankBalances->total($balances)->minorUnits,
        ];
    }

    /**
     * What the business owes in goods, for the tile.
     *
     * A position like the receivable beside it — what has been sold and not
     * bought — so it takes no period either.
     *
     * @return array{value: int, items: int, products: int}
     */
    private function owedInGoods(): array
    {
        $summary = GoodsOwedQuery::summarise($this->goodsOwed->get());

        return [
            'value' => $summary['value']->minorUnits,
            'items' => $summary['items'],
            'products' => $summary['products'],
        ];
    }
}
