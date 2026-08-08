<?php

namespace App\Queries;

use App\Enums\SaleStatus;
use App\Models\SaleLine;
use App\Models\StockBatchConsumption;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * What each product made over a period.
 *
 * Revenue comes from the sale lines and cost from the batches those lines
 * consumed, so a product bought cheaply in January and dearly in March shows
 * the cost of the units that actually left the shelf — not an average, and not
 * today's catalogue price.
 *
 * Products that sold nothing are left out. This answers "what is worth
 * selling", and a page of zeroes buries the answer; `InventoryReportQuery`
 * covers the opposite question of what is sitting there not selling.
 */
final class ProductProfitabilityQuery
{
    private const string LINE_NET = 'sale_lines.quantity * sale_lines.unit_price - sale_lines.discount';

    private const string CONSUMPTION_COST = 'stock_batch_consumptions.quantity * stock_batches.unit_cost';

    /**
     * Most profitable first.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     units: int,
     *     revenue: Money,
     *     cost_of_goods_sold: Money,
     *     gross_profit: Money,
     *     average_unit_price: Money,
     *     average_unit_cost: Money,
     * }>
     */
    public function get(ReportPeriod $period): array
    {
        $sold = $this->lines($period)
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->groupBy('sale_lines.product_id', 'products.name', 'products.code')
            ->selectRaw('sale_lines.product_id as product_id, products.name as name, products.code as code')
            ->selectRaw('COALESCE(SUM('.self::LINE_NET.'), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sale_lines.quantity), 0) as units')
            ->get();

        $costs = $this->consumptions($period)
            ->groupBy('sale_lines.product_id')
            ->selectRaw('sale_lines.product_id as product_id, COALESCE(SUM('.self::CONSUMPTION_COST.'), 0) as cost')
            ->pluck('cost', 'product_id');

        $rows = $sold->map(function (object $row) use ($costs): array {
            $revenue = Money::fromMinorUnits((int) $row->revenue);
            $cost = Money::fromMinorUnits((int) ($costs[$row->product_id] ?? 0));
            $units = (int) $row->units;

            return [
                'id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'units' => $units,
                'revenue' => $revenue,
                'cost_of_goods_sold' => $cost,
                'gross_profit' => $revenue->minus($cost),
                'average_unit_price' => self::per($revenue, $units),
                'average_unit_cost' => self::per($cost, $units),
            ];
        });

        return array_values($rows
            ->sortByDesc(fn (array $row): int => $row['gross_profit']->minorUnits)
            ->all());
    }

    private function lines(ReportPeriod $period): Builder
    {
        return $this->scopeToPeriod(
            SaleLine::query()
                ->toBase()
                ->join('sales', 'sales.id', '=', 'sale_lines.sale_id'),
            $period,
        );
    }

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

    private static function per(Money $total, int $count): Money
    {
        return $count > 0 ? $total->multipliedByFraction(1, $count) : Money::zero();
    }
}
