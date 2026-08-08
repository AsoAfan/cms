<?php

namespace App\Queries;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\SaleLine;
use App\Models\StockBatchConsumption;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * What was sold over a period, and what it cost.
 *
 * Only **posted** sales count. A draft has not happened: nothing has left the
 * shelf and nobody has paid, so counting it would report takings that do not
 * exist.
 *
 * Revenue and cost are both scoped by the sale's own date rather than by when
 * the ledger happened to move, so the two always describe the same set of
 * documents. That is what makes gross profit here equal the sum of the
 * per-line profits on those invoices, to the penny.
 */
final class SalesReportQuery
{
    /**
     * A line's takings: what was charged, less the discount given on it.
     */
    private const string LINE_NET = 'sale_lines.quantity * sale_lines.unit_price - sale_lines.discount';

    /**
     * One FIFO allocation's cost: what was taken, at the batch's landed cost.
     */
    private const string CONSUMPTION_COST = 'stock_batch_consumptions.quantity * stock_batches.unit_cost';

    /**
     * @return array{
     *     revenue: Money,
     *     cost_of_goods_sold: Money,
     *     gross_profit: Money,
     *     invoice_count: int,
     *     units: int,
     *     average_invoice: Money,
     *     average_unit_price: Money,
     *     average_unit_cost: Money,
     *     average_unit_profit: Money,
     * }
     */
    public function get(ReportPeriod $period): array
    {
        $totals = $this->lines($period)
            ->selectRaw('COALESCE(SUM('.self::LINE_NET.'), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sale_lines.quantity), 0) as units')
            ->selectRaw('COUNT(DISTINCT sale_lines.sale_id) as invoice_count')
            ->first();

        $revenue = Money::fromMinorUnits((int) ($totals->revenue ?? 0));
        $cost = $this->costOfGoodsSold($period);
        $profit = $revenue->minus($cost);
        $units = (int) ($totals->units ?? 0);
        $invoices = (int) ($totals->invoice_count ?? 0);

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cost,
            'gross_profit' => $profit,
            'invoice_count' => $invoices,
            'units' => $units,
            'average_invoice' => self::per($revenue, $invoices),
            'average_unit_price' => self::per($revenue, $units),
            'average_unit_cost' => self::per($cost, $units),
            'average_unit_profit' => self::per($profit, $units),
        ];
    }

    /**
     * Takings, cost and profit per bucket, for a trend chart.
     *
     * @return list<array{bucket: string, revenue: int, cost_of_goods_sold: int, gross_profit: int}>
     */
    public function series(ReportPeriod $period): array
    {
        $revenue = $period->fold(
            $this->lines($period)
                ->groupBy('sales.sold_on')
                ->selectRaw('sales.sold_on as day, COALESCE(SUM('.self::LINE_NET.'), 0) as total')
                ->pluck('total', 'day')
        );

        $cost = $period->fold(
            $this->consumptions($period)
                ->groupBy('sales.sold_on')
                ->selectRaw('sales.sold_on as day, COALESCE(SUM('.self::CONSUMPTION_COST.'), 0) as total')
                ->pluck('total', 'day')
        );

        return array_map(
            static fn (string $bucket): array => [
                'bucket' => $bucket,
                'revenue' => $revenue[$bucket],
                'cost_of_goods_sold' => $cost[$bucket],
                'gross_profit' => $revenue[$bucket] - $cost[$bucket],
            ],
            $period->buckets(),
        );
    }

    /**
     * Takings split by how they were paid for.
     *
     * With no customer to analyse by (see Phase 2), payment method is one of
     * the three axes sales are read along — the others being product and
     * period. Methods nothing came in on are listed at zero.
     *
     * @return list<array{method: string, label: string, invoice_count: int, revenue: Money}>
     */
    public function byPaymentMethod(ReportPeriod $period): array
    {
        $takings = $this->lines($period)
            ->groupBy('sales.payment_method')
            ->selectRaw('sales.payment_method as method')
            ->selectRaw('COALESCE(SUM('.self::LINE_NET.'), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT sale_lines.sale_id) as invoice_count')
            ->get()
            ->keyBy('method');

        $rows = array_map(
            static fn (PaymentMethod $method): array => [
                'method' => $method->value,
                'label' => $method->label(),
                'invoice_count' => (int) ($takings[$method->value]->invoice_count ?? 0),
                'revenue' => Money::fromMinorUnits((int) ($takings[$method->value]->revenue ?? 0)),
            ],
            PaymentMethod::cases(),
        );

        usort(
            $rows,
            static fn (array $a, array $b): int => $b['revenue']->compareTo($a['revenue'])
        );

        return $rows;
    }

    /**
     * What the goods on those invoices cost, read off the FIFO allocations the
     * ledger recorded when each sale was posted. Never an average, and never a
     * copy of the catalogue cost.
     */
    public function costOfGoodsSold(ReportPeriod $period): Money
    {
        return Money::fromMinorUnits((int) $this->consumptions($period)
            ->selectRaw('COALESCE(SUM('.self::CONSUMPTION_COST.'), 0) as total')
            ->value('total'));
    }

    /**
     * Every line on a posted sale in the period.
     */
    private function lines(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(
            SaleLine::query()
                ->toBase()
                ->join('sales', 'sales.id', '=', 'sale_lines.sale_id'),
            $period,
        );
    }

    /**
     * Every batch allocation those lines drew on.
     *
     * The chain is deliberately anchored at the sale rather than at the
     * movement's own date: it is the invoice that belongs to the period, and
     * the cost belongs to the invoice.
     */
    private function consumptions(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(
            StockBatchConsumption::query()
                ->toBase()
                ->join('stock_batches', 'stock_batches.id', '=', 'stock_batch_consumptions.stock_batch_id')
                ->join('stock_movements', 'stock_movements.id', '=', 'stock_batch_consumptions.stock_movement_id')
                ->where('stock_movements.source_type', (new SaleLine)->getMorphClass())
                ->join('sale_lines', 'sale_lines.id', '=', 'stock_movements.source_id')
                ->join('sales', 'sales.id', '=', 'sale_lines.sale_id'),
            $period,
        );
    }

    private function scopeToPeriod(Builder $query, ReportPeriod $period): Builder
    {
        [$from, $to] = $period->toDateStrings();

        return $query
            ->where('sales.status', SaleStatus::Posted->value)
            ->whereDate('sales.sold_on', '>=', $from)
            ->whereDate('sales.sold_on', '<=', $to);
    }

    /**
     * A per-unit or per-invoice average. Nothing sold means there is no
     * average to give, which is zero rather than a division by nothing.
     */
    private static function per(Money $total, int $count): Money
    {
        return $count > 0 ? $total->multipliedByFraction(1, $count) : Money::zero();
    }
}
