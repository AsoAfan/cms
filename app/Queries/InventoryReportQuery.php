<?php

namespace App\Queries;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\SaleLine;
use App\Models\StockMovement;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/**
 * What is on the shelf at the end of a period, what it is worth, and what is
 * not moving.
 *
 * Valuation rewinds to the period's end date rather than reading today, so a
 * report run in April for March agrees with the accounts as they stood in
 * March. `InventoryValuationQuery` does the rewinding; this puts a period, the
 * sales in it and the products around it.
 *
 * **Dead stock** is stock on hand at the end of the period that nothing sold
 * out of during it. Money is sitting on that shelf doing nothing, and it is
 * the one inventory figure that changes what a buyer does next.
 */
final class InventoryReportQuery
{
    public function __construct(
        private readonly StockOnHandQuery $onHand,
        private readonly InventoryValuationQuery $valuation,
    ) {}

    /**
     * @return array{
     *     total_value: Money,
     *     total_units: int,
     *     stocked_count: int,
     *     dead_value: Money,
     *     dead_units: int,
     *     dead_count: int,
     *     products: list<array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         is_active: bool,
     *         on_hand: int,
     *         value: Money,
     *         units_sold: int,
     *         last_sold_on: string|null,
     *         is_dead: bool,
     *     }>,
     * }
     */
    public function get(ReportPeriod $period): array
    {
        $quantities = $this->onHand->get($period->to);
        $values = $this->valuation->get($period->to);
        $sold = $this->unitsSold($period);
        $lastSold = $this->lastSoldDates();

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active'])
            ->map(function (Product $product) use ($quantities, $values, $sold, $lastSold): array {
                $onHand = (int) ($quantities[$product->id] ?? 0);
                $unitsSold = (int) ($sold[$product->id] ?? 0);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'is_active' => $product->is_active,
                    'on_hand' => $onHand,
                    'value' => $values[$product->id] ?? Money::zero(),
                    'units_sold' => $unitsSold,
                    'last_sold_on' => $lastSold[$product->id] ?? null,
                    'is_dead' => $onHand > 0 && $unitsSold === 0,
                ];
            })
            // Most valuable first: that is where the money is tied up.
            ->sortByDesc(fn (array $row): int => $row['value']->minorUnits)
            ->values();

        $stocked = $products->filter(fn (array $row): bool => $row['on_hand'] > 0);
        $dead = $products->filter(fn (array $row): bool => $row['is_dead']);

        return [
            'total_value' => self::sumValue($products),
            'total_units' => (int) $stocked->sum('on_hand'),
            'stocked_count' => $stocked->count(),
            'dead_value' => self::sumValue($dead),
            'dead_units' => (int) $dead->sum('on_hand'),
            'dead_count' => $dead->count(),
            'products' => array_values($products->all()),
        ];
    }

    /**
     * Units sold per product inside the period, from posted sales only.
     *
     * @return Collection<int, int>
     */
    private function unitsSold(ReportPeriod $period): Collection
    {
        [$from, $to] = $period->toDateStrings();

        return SaleLine::query()
            ->toBase()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', SaleStatus::Posted->value)
            ->whereDate('sales.sold_on', '>=', $from)
            ->whereDate('sales.sold_on', '<=', $to)
            ->groupBy('sale_lines.product_id')
            ->selectRaw('sale_lines.product_id as product_id, COALESCE(SUM(sale_lines.quantity), 0) as units')
            ->pluck('units', 'product_id')
            ->map(fn (mixed $units): int => (int) $units);
    }

    /**
     * When each product last went out of the door, at any time — not only
     * inside the period. "Dead for three months" is a different problem from
     * "never sold", and the date is what tells them apart.
     *
     * @return Collection<int, string>
     */
    private function lastSoldDates(): Collection
    {
        return StockMovement::query()
            ->toBase()
            ->where('type', StockMovementType::Sale->value)
            ->groupBy('product_id')
            ->selectRaw('product_id, MAX(occurred_at) as last_sold_at')
            ->pluck('last_sold_at', 'product_id')
            ->map(fn (mixed $date): string => substr((string) $date, 0, 10));
    }

    /**
     * @param  iterable<array-key, array{value: Money, ...}>  $rows
     */
    private static function sumValue(iterable $rows): Money
    {
        $total = Money::zero();

        foreach ($rows as $row) {
            $total = $total->plus($row['value']);
        }

        return $total;
    }
}
