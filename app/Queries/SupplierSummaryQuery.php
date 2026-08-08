<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Query\Builder;

/**
 * What was bought from whom over a period.
 *
 * There is no matching customer summary, and that is deliberate: sales here
 * are over the counter and are not tied to a named buyer (see Phase 2). Sales
 * are analysed by product, period and payment method instead.
 *
 * Totals are landed: the freight and duty on an invoice are part of what that
 * supplier's goods cost, so they are counted against the supplier that
 * charged them.
 */
final class SupplierSummaryQuery
{
    private const string LINE_NET = 'purchase_lines.quantity * purchase_lines.unit_cost - purchase_lines.discount';

    /**
     * Biggest spend first. Suppliers with nothing posted in the period are
     * left out — this reports what was bought, not who is on file.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     invoice_count: int,
     *     units: int,
     *     goods: Money,
     *     additional_costs: Money,
     *     total: Money,
     *     average_invoice: Money,
     *     last_invoiced_on: string|null,
     * }>
     */
    public function get(ReportPeriod $period): array
    {
        $invoices = $this->purchases($period)
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->groupBy('purchases.supplier_id', 'suppliers.name')
            ->selectRaw('purchases.supplier_id as supplier_id, suppliers.name as name')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('MAX(purchases.invoiced_on) as last_invoiced_on')
            ->get();

        $goods = $this->purchases($period)
            ->join('purchase_lines', 'purchase_lines.purchase_id', '=', 'purchases.id')
            ->groupBy('purchases.supplier_id')
            ->selectRaw('purchases.supplier_id as supplier_id')
            ->selectRaw('COALESCE(SUM('.self::LINE_NET.'), 0) as goods')
            ->selectRaw('COALESCE(SUM(purchase_lines.quantity), 0) as units')
            ->get()
            ->keyBy('supplier_id');

        $additional = $this->purchases($period)
            ->join('purchase_additional_costs', 'purchase_additional_costs.purchase_id', '=', 'purchases.id')
            ->groupBy('purchases.supplier_id')
            ->selectRaw('purchases.supplier_id as supplier_id')
            ->selectRaw('COALESCE(SUM(purchase_additional_costs.amount), 0) as extra')
            ->pluck('extra', 'supplier_id');

        return array_values($invoices
            ->map(function (object $row) use ($goods, $additional): array {
                $spend = Money::fromMinorUnits((int) ($goods[$row->supplier_id]->goods ?? 0));
                $extra = Money::fromMinorUnits((int) ($additional[$row->supplier_id] ?? 0));
                $total = $spend->plus($extra);
                $count = (int) $row->invoice_count;

                return [
                    'id' => (int) $row->supplier_id,
                    'name' => (string) $row->name,
                    'invoice_count' => $count,
                    'units' => (int) ($goods[$row->supplier_id]->units ?? 0),
                    'goods' => $spend,
                    'additional_costs' => $extra,
                    'total' => $total,
                    'average_invoice' => $count > 0
                        ? $total->multipliedByFraction(1, $count)
                        : Money::zero(),
                    'last_invoiced_on' => $row->last_invoiced_on === null
                        ? null
                        : substr((string) $row->last_invoiced_on, 0, 10),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['total']->minorUnits)
            ->all());
    }

    private function purchases(ReportPeriod $period): Builder
    {
        [$from, $to] = $period->toDateStrings();

        return Purchase::query()
            ->toBase()
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->whereDate('purchases.invoiced_on', '>=', $from)
            ->whereDate('purchases.invoiced_on', '<=', $to);
    }
}
