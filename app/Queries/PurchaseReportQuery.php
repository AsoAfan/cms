<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseAdditionalCost;
use App\Models\PurchaseLine;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * What was bought over a period, and what it landed at.
 *
 * Posted invoices only, for the same reason sales are: a draft has not put
 * anything on the shelf.
 *
 * **Buying is not a cost.** What is spent here becomes inventory, and only
 * turns into an expense when the goods sell — which is what `SalesReportQuery`
 * reports as cost of goods sold. Nothing in this report belongs in a profit
 * calculation, and `ProfitReportQuery` deliberately does not use it.
 */
final class PurchaseReportQuery
{
    private const string LINE_NET = 'purchase_lines.quantity * purchase_lines.unit_cost - purchase_lines.discount';

    /**
     * @return array{
     *     goods: Money,
     *     additional_costs: Money,
     *     total: Money,
     *     invoice_count: int,
     *     supplier_count: int,
     *     units: int,
     *     average_invoice: Money,
     *     average_unit_cost: Money,
     * }
     */
    public function get(ReportPeriod $period): array
    {
        $totals = $this->lines($period)
            ->selectRaw('COALESCE(SUM('.self::LINE_NET.'), 0) as goods')
            ->selectRaw('COALESCE(SUM(purchase_lines.quantity), 0) as units')
            ->first();

        $counts = $this->invoices($period)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COUNT(DISTINCT purchases.supplier_id) as supplier_count')
            ->first();

        $goods = Money::fromMinorUnits((int) ($totals->goods ?? 0));
        $additional = $this->additionalCosts($period);
        $total = $goods->plus($additional);
        $units = (int) ($totals->units ?? 0);
        $invoices = (int) ($counts->invoice_count ?? 0);

        return [
            'goods' => $goods,
            'additional_costs' => $additional,
            'total' => $total,
            'invoice_count' => $invoices,
            'supplier_count' => (int) ($counts->supplier_count ?? 0),
            'units' => $units,
            'average_invoice' => self::per($total, $invoices),
            // Landed, not list: freight and duty are part of what a unit cost,
            // which is the whole point of allocating them at posting time.
            'average_unit_cost' => self::per($total, $units),
        ];
    }

    /**
     * Spend per bucket, for a trend chart.
     *
     * @return list<array{bucket: string, goods: int, additional_costs: int, total: int}>
     */
    public function series(ReportPeriod $period): array
    {
        $goods = $period->fold(
            $this->lines($period)
                ->groupBy('purchases.invoiced_on')
                ->selectRaw('purchases.invoiced_on as day, COALESCE(SUM('.self::LINE_NET.'), 0) as total')
                ->pluck('total', 'day')
        );

        $additional = $period->fold(
            $this->additionalCostRows($period)
                ->groupBy('purchases.invoiced_on')
                ->selectRaw('purchases.invoiced_on as day, COALESCE(SUM(purchase_additional_costs.amount), 0) as total')
                ->pluck('total', 'day')
        );

        return array_map(
            static fn (string $bucket): array => [
                'bucket' => $bucket,
                'goods' => $goods[$bucket],
                'additional_costs' => $additional[$bucket],
                'total' => $goods[$bucket] + $additional[$bucket],
            ],
            $period->buckets(),
        );
    }

    public function additionalCosts(ReportPeriod $period): Money
    {
        return Money::fromMinorUnits((int) $this->additionalCostRows($period)
            ->selectRaw('COALESCE(SUM(purchase_additional_costs.amount), 0) as total')
            ->value('total'));
    }

    private function invoices(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(Purchase::query()->toBase(), $period);
    }

    private function lines(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(
            PurchaseLine::query()
                ->toBase()
                ->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id'),
            $period,
        );
    }

    private function additionalCostRows(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(
            PurchaseAdditionalCost::query()
                ->toBase()
                ->join('purchases', 'purchases.id', '=', 'purchase_additional_costs.purchase_id'),
            $period,
        );
    }

    private function scopeToPeriod(Builder $query, ReportPeriod $period): Builder
    {
        [$from, $to] = $period->toDateStrings();

        return $query
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->whereDate('purchases.invoiced_on', '>=', $from)
            ->whereDate('purchases.invoiced_on', '<=', $to);
    }

    private static function per(Money $total, int $count): Money
    {
        return $count > 0 ? $total->multipliedByFraction(1, $count) : Money::zero();
    }
}
