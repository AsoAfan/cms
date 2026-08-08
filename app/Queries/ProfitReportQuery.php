<?php

namespace App\Queries;

use App\Support\Money;
use App\Support\ReportPeriod;

/**
 * The bottom line for a period.
 *
 *     gross profit = revenue − cost of goods sold
 *     net profit   = gross profit − expenses
 *
 * Expenses enter at that second subtraction and nowhere else. Rent is a cost
 * the moment it is paid; goods only become a cost when they sell. Folding
 * expenses into COGS would mistime the first and double-count the second, so
 * this class composes the two reports rather than merging their SQL.
 *
 * Buying stock does not appear at all. Money spent on inventory is not a cost
 * until the inventory sells — until then it has changed shape, not left the
 * business. `PurchaseReportQuery` reports it separately for that reason.
 */
final class ProfitReportQuery
{
    public function __construct(
        private readonly SalesReportQuery $sales,
        private readonly ExpenseReportQuery $expenses,
    ) {}

    /**
     * @return array{
     *     revenue: Money,
     *     cost_of_goods_sold: Money,
     *     gross_profit: Money,
     *     expenses: Money,
     *     net_profit: Money,
     *     invoice_count: int,
     *     units: int,
     *     days: int,
     *     averages: array{
     *         income: array{per_day: Money, per_week: Money, per_month: Money},
     *         outcome: array{per_day: Money, per_week: Money, per_month: Money},
     *         net_profit: array{per_day: Money, per_week: Money, per_month: Money},
     *     },
     * }
     */
    public function get(ReportPeriod $period): array
    {
        $sales = $this->sales->get($period);
        $expenses = $this->expenses->total($period);

        $revenue = $sales['revenue'];
        $cost = $sales['cost_of_goods_sold'];
        $gross = $sales['gross_profit'];
        $net = $gross->minus($expenses);

        // What the takings cost to earn: the goods themselves plus the running
        // costs of the period. Income less outcome is net profit, so the three
        // averages below always reconcile with each other.
        $outcome = $cost->plus($expenses);

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cost,
            'gross_profit' => $gross,
            'expenses' => $expenses,
            'net_profit' => $net,
            'invoice_count' => $sales['invoice_count'],
            'units' => $sales['units'],
            'days' => $period->days(),
            'averages' => [
                'income' => $period->averages($revenue),
                'outcome' => $period->averages($outcome),
                'net_profit' => $period->averages($net),
            ],
        ];
    }

    /**
     * Revenue, cost, expenses and both profits per bucket.
     *
     * @return list<array{
     *     bucket: string,
     *     revenue: int,
     *     cost_of_goods_sold: int,
     *     gross_profit: int,
     *     expenses: int,
     *     net_profit: int,
     * }>
     */
    public function series(ReportPeriod $period): array
    {
        $spend = array_column($this->expenses->series($period), 'total', 'bucket');

        return array_map(
            static function (array $row) use ($spend): array {
                $expenses = $spend[$row['bucket']] ?? 0;

                return [
                    ...$row,
                    'expenses' => $expenses,
                    'net_profit' => $row['gross_profit'] - $expenses,
                ];
            },
            $this->sales->series($period),
        );
    }
}
