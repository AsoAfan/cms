<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * Money in and money out over a period.
 *
 *     income    = what was sold
 *     collected = what was actually taken for it
 *     outcome   = what was bought + what was spent running the place
 *     net       = income − outcome
 *
 * **Income and collected are not the same figure, and both are here on purpose.**
 * A sale on account is income the day it is delivered and cash on the day the
 * customer pays, which can be months later or never. Income says what the shop
 * sold; collected says what came through the door — the money handed over at the
 * time of sale, plus every repayment received in the window, including
 * repayments of sales invoiced long before it. Net is measured on income, so a
 * shop that sells well on credit does not read as a shop that is failing.
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
 * Scoped by the document's own date — `sales.sold_on`, `purchases.invoiced_on`,
 * `expenses.spent_on`, `customer_payments.received_on`.
 *
 * **A sale counts once it is delivered**, not when it is sent out. The money and
 * the debt begin together, at the moment the goods become the customer's, so the
 * reported income and what customers owe can never describe different sets of
 * invoices. Stock still leaves a status earlier (see `SaleStatus`) — goods with a
 * driver are off the shelf but not yet sold — which is the one place the ledger
 * and the money view deliberately part company.
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
     *     collected: Money,
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
            // What actually came through the door, which on credit terms is a
            // different figure from what was sold.
            'collected' => $this->collected($period),
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
     * What was sold in the period: the takings invoiced on delivered sales,
     * whether or not the customer has paid for them yet.
     */
    private function income(ReportPeriod $period): Money
    {
        $query = SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', SaleStatus::Proceed->value);

        return $this->total(
            $this->within($query, 'sales.sold_on', $period),
            self::SALE_LINE_NET,
        );
    }

    /**
     * What came through the door in the period.
     *
     * Two sources, and both are money: what customers handed over at the time of
     * sale, and every repayment received in the window — which may be settling
     * invoices from months before it. A repayment is scoped by the day it was
     * received, so it lands in the period the cash actually arrived.
     */
    private function collected(ReportPeriod $period): Money
    {
        $atTheTill = $this->total(
            $this->within(
                Sale::query()->toBase()->where('status', SaleStatus::Proceed->value),
                'sales.sold_on',
                $period,
            ),
            'sales.amount_paid',
        );

        $repayments = $this->total(
            $this->within(CustomerPayment::query()->toBase(), 'customer_payments.received_on', $period),
            'customer_payments.amount',
        );

        return $atTheTill->plus($repayments);
    }

    /**
     * What arrived purchases landed at: the goods, plus the freight and duty
     * invoiced with them. Both are money that has left the business.
     */
    private function purchases(ReportPeriod $period): Money
    {
        $goods = PurchaseLine::query()
            ->toBase()
            ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
            ->whereIn('purchases.status', PurchaseStatus::inLedger());

        $additional = PurchaseAdditionalCost::query()
            ->toBase()
            ->join('purchases', 'purchases.id', '=', 'purchase_additional_costs.purchase_id')
            ->whereIn('purchases.status', PurchaseStatus::inLedger());

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

    /**
     * `$expression` is literal by contract: every caller passes a class constant
     * or a column name written out here, never anything from a request.
     *
     * @param  literal-string  $expression
     */
    private function total(Builder $query, string $expression): Money
    {
        return Money::fromMinorUnits(
            (int) $query->selectRaw("COALESCE(SUM({$expression}), 0) as total")->value('total')
        );
    }
}
