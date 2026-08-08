<?php

namespace App\Queries;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;

/**
 * What it cost to trade over a period, grouped by category.
 *
 * Expenses are the last subtraction in the accounts and never part of cost of
 * goods sold — see `.ai/rules/expenses.md`. Buying stock is a purchase, not an
 * expense, so nothing here touches inventory.
 *
 * Every category is listed, including the ones nothing was spent on. A zero
 * against "Marketing" is an answer; a missing row looks like an oversight.
 */
final class ExpenseReportQuery
{
    /**
     * @return array{
     *     total: Money,
     *     count: int,
     *     categories: list<array{id: int, name: string, total: Money, count: int}>,
     *     largest_category: string|null,
     * }
     */
    public function get(ReportPeriod $period): array
    {
        [$from, $to] = $period->toDateStrings();

        $within = fn (BuilderContract $query): BuilderContract => $query
            ->whereDate('spent_on', '>=', $from)
            ->whereDate('spent_on', '<=', $to);

        $categories = array_values(ExpenseCategory::query()
            ->withSum(['expenses as spend' => $within], 'amount')
            ->withCount(['expenses as spend_count' => $within])
            ->get()
            ->map(fn (ExpenseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'total' => Money::fromMinorUnits((int) ($category->spend ?? 0)),
                'count' => (int) ($category->spend_count ?? 0),
            ])
            // Biggest first: the point of the screen is what the money went on.
            ->sortByDesc(fn (array $category): int => $category['total']->minorUnits)
            ->all());

        $total = Money::sum(...array_column($categories, 'total'));
        $largest = $categories[0] ?? null;

        return [
            'total' => $total,
            'count' => array_sum(array_column($categories, 'count')),
            'categories' => $categories,
            'largest_category' => $largest !== null && $largest['total']->isPositive()
                ? $largest['name']
                : null,
        ];
    }

    /**
     * Spend per bucket, for a trend chart.
     *
     * @return list<array{bucket: string, total: int}>
     */
    public function series(ReportPeriod $period): array
    {
        $totals = $period->fold(
            $this->scoped($period)
                ->groupBy('expenses.spent_on')
                ->selectRaw('expenses.spent_on as day, COALESCE(SUM(expenses.amount), 0) as total')
                ->pluck('total', 'day')
        );

        return array_map(
            static fn (string $bucket): array => [
                'bucket' => $bucket,
                'total' => $totals[$bucket],
            ],
            $period->buckets(),
        );
    }

    public function total(ReportPeriod $period): Money
    {
        return Money::fromMinorUnits((int) $this->scoped($period)
            ->selectRaw('COALESCE(SUM(expenses.amount), 0) as total')
            ->value('total'));
    }

    private function scoped(ReportPeriod $period): Builder
    {
        [$from, $to] = $period->toDateStrings();

        return Expense::query()
            ->toBase()
            ->whereDate('expenses.spent_on', '>=', $from)
            ->whereDate('expenses.spent_on', '<=', $to);
    }
}
