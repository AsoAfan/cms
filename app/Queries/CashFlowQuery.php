<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Models\SaleLine;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * Money in and money out over a period.
 *
 *     income  = what was sold
 *     outcome = what was bought + what was spent running the place
 *     net     = income − outcome
 *
 * This is a **cash view, not a profit and loss**. Outcome is what left the
 * bank in the window, so a month with a big stock order reads as a loss even
 * where every sale was profitable — the goods are on the shelf, not in the
 * figures. Cost of goods sold does not appear anywhere here, and nothing in
 * this class touches the FIFO batch allocations that would derive it.
 *
 * That is the whole of the reporting engine on purpose: three figures a shop
 * owner can check daily beat six screens nobody opens.
 *
 * Posted documents only, scoped by the document's own date — `sales.sold_on`,
 * `purchases.invoiced_on`, `expenses.spent_on`. A draft has not put anything
 * on a shelf and nobody has paid for it.
 */
final class CashFlowQuery
{
    /**
     * A sale line's takings: what was charged, less the discount given on it.
     */
    private const string SALE_LINE_NET = 'sale_lines.quantity * sale_lines.unit_price - sale_lines.discount';

    /**
     * A purchase line's spend, on the same footing.
     */
    private const string PURCHASE_LINE_NET = 'purchase_lines.quantity * purchase_lines.unit_cost - purchase_lines.discount';

    /**
     * @return array{
     *     income: Money,
     *     purchases: Money,
     *     expenses: Money,
     *     outcome: Money,
     *     net: Money,
     *     days: int,
     *     averages: array{
     *         income: array{per_day: Money, per_week: Money, per_month: Money},
     *         outcome: array{per_day: Money, per_week: Money, per_month: Money},
     *         net: array{per_day: Money, per_week: Money, per_month: Money},
     *     },
     * }
     */
    public function get(ReportPeriod $period): array
    {
        $income = $this->income($period);
        $purchases = $this->purchases($period);
        $expenses = $this->expenses($period);

        $outcome = $purchases->plus($expenses);
        $net = $income->minus($outcome);

        return [
            'income' => $income,
            // Kept apart so the outcome tile can say what the money went on.
            'purchases' => $purchases,
            'expenses' => $expenses,
            'outcome' => $outcome,
            'net' => $net,
            'days' => $period->days(),
            // Income less outcome is net, so the three sets of averages
            // reconcile with each other at every horizon.
            'averages' => [
                'income' => $period->averages($income),
                'outcome' => $period->averages($outcome),
                'net' => $period->averages($net),
            ],
        ];
    }

    /**
     * Takings on posted sales invoiced in the period.
     */
    private function income(ReportPeriod $period): Money
    {
        $query = SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', SaleStatus::Posted->value);

        return $this->total(
            $this->within($query, 'sales.sold_on', $period),
            self::SALE_LINE_NET,
        );
    }

    /**
     * What posted purchases landed at: the goods, plus the freight and duty
     * invoiced with them. Both are money that has left the business.
     */
    private function purchases(ReportPeriod $period): Money
    {
        $goods = PurchaseLine::query()
            ->toBase()
            ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
            ->where('purchases.status', PurchaseStatus::Posted->value);

        $additional = PurchaseAdditionalCost::query()
            ->toBase()
            ->join('purchases', 'purchases.id', '=', 'purchase_additional_costs.purchase_id')
            ->where('purchases.status', PurchaseStatus::Posted->value);

        return $this->total(
            $this->within($goods, 'purchases.invoiced_on', $period),
            self::PURCHASE_LINE_NET,
        )->plus($this->total(
            $this->within($additional, 'purchases.invoiced_on', $period),
            'purchase_additional_costs.amount',
        ));
    }

    /**
     * The running costs of the period. Expenses have no draft state — an
     * expense is recorded once it has been paid.
     */
    private function expenses(ReportPeriod $period): Money
    {
        return $this->total(
            $this->within(Expense::query()->toBase(), 'expenses.spent_on', $period),
            'expenses.amount',
        );
    }

    /**
     * Date columns are stored `Y-m-d 00:00:00`, so this compares dates rather
     * than putting a `whereBetween` on raw strings — the latter silently drops
     * the last day of the period.
     */
    private function within(Builder $query, string $column, ReportPeriod $period): Builder
    {
        [$from, $to] = $period->toDateStrings();

        return $query
            ->whereDate($column, '>=', $from)
            ->whereDate($column, '<=', $to);
    }

    private function total(Builder $query, string $expression): Money
    {
        return Money::fromMinorUnits(
            (int) $query->selectRaw("COALESCE(SUM({$expression}), 0) as total")->value('total')
        );
    }
}
