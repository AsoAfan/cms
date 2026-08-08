<?php

namespace App\Queries;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The documents behind the cash figures: what was sold, bought and spent in a
 * period, newest first.
 *
 * `CashFlowQuery` says how much; this says what it was made of. The two are
 * scoped identically — the document's own date, posted only — so the rows a
 * screen lists are exactly the rows its totals were added up from.
 *
 * The dashboard asks for a handful of rows and asks for drafts as well, since
 * an unfinished invoice is work still to do rather than a figure. Nothing a
 * draft contributes appears in any total.
 *
 * Totals are summed in SQL rather than by hydrating every line, so a year of
 * trading is three queries and not three thousand.
 */
final class ActivityQuery
{
    /**
     * A sale line's takings, matching `CashFlowQuery` exactly.
     */
    private const string SALE_LINE_NET = 'sale_lines.quantity * sale_lines.unit_price - sale_lines.discount';

    /**
     * A purchase line's spend, on the same footing.
     */
    private const string PURCHASE_LINE_NET = 'purchase_lines.quantity * purchase_lines.unit_cost - purchase_lines.discount';

    /**
     * One row per document, in the shape every activity table renders.
     *
     * @return array{
     *     sales: list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>,
     *     purchases: list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>,
     *     expenses: list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>,
     * }
     */
    public function get(ReportPeriod $period, ?int $limit = null, bool $drafts = false): array
    {
        return [
            'sales' => $this->sales($period, $limit, $drafts),
            'purchases' => $this->purchases($period, $limit, $drafts),
            'expenses' => $this->expenses($period, $limit),
        ];
    }

    /**
     * @return list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>
     */
    private function sales(ReportPeriod $period, ?int $limit, bool $drafts): array
    {
        $query = Sale::query()
            ->select(['id', 'number', 'sold_on', 'status', 'payment_method'])
            ->withSum('lines as net_minor_units', DB::raw(self::SALE_LINE_NET))
            ->unless($drafts, fn (Builder $query): Builder => $query->where('status', SaleStatus::Posted->value));

        return $this->newestFirst($query, 'sold_on', $period, $limit)
            ->map(fn (Sale $sale): array => [
                'kind' => 'sale',
                'id' => $sale->id,
                'date' => $sale->sold_on->toDateString(),
                'label' => $sale->number,
                'detail' => $sale->payment_method->label(),
                'draft' => $sale->isDraft(),
                'total' => Money::fromMinorUnits((int) $sale->net_minor_units),
            ])
            ->all();
    }

    /**
     * Freight and duty are part of what an invoice came to, so they are part
     * of the figure listed against it — as in `CashFlowQuery`.
     *
     * @return list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>
     */
    private function purchases(ReportPeriod $period, ?int $limit, bool $drafts): array
    {
        $query = Purchase::query()
            ->select(['id', 'supplier_id', 'number', 'invoiced_on', 'status'])
            ->with('supplier:id,name')
            ->withSum('lines as goods_minor_units', DB::raw(self::PURCHASE_LINE_NET))
            ->withSum('additionalCosts as additional_minor_units', 'amount')
            ->unless($drafts, fn (Builder $query): Builder => $query->where('status', PurchaseStatus::Posted->value));

        return $this->newestFirst($query, 'invoiced_on', $period, $limit)
            ->map(fn (Purchase $purchase): array => [
                'kind' => 'purchase',
                'id' => $purchase->id,
                'date' => $purchase->invoiced_on->toDateString(),
                'label' => $purchase->number,
                'detail' => $purchase->supplier?->name,
                'draft' => $purchase->isDraft(),
                'total' => Money::fromMinorUnits(
                    (int) $purchase->goods_minor_units + (int) $purchase->additional_minor_units
                ),
            ])
            ->all();
    }

    /**
     * Expenses have no draft state — an expense is recorded once it is paid —
     * so every one in the window counts.
     *
     * @return list<array{kind: string, id: int, date: string, label: string, detail: string|null, draft: bool, total: Money}>
     */
    private function expenses(ReportPeriod $period, ?int $limit): array
    {
        $query = Expense::query()
            ->select(['id', 'expense_category_id', 'title', 'amount', 'spent_on'])
            ->with('category:id,name');

        return $this->newestFirst($query, 'spent_on', $period, $limit)
            ->map(fn (Expense $expense): array => [
                'kind' => 'expense',
                'id' => $expense->id,
                'date' => $expense->spent_on->toDateString(),
                'label' => $expense->title,
                'detail' => $expense->category->name,
                'draft' => false,
                'total' => $expense->amount,
            ])
            ->all();
    }

    /**
     * Scope to the period by the document's own date and order newest first.
     *
     * Date columns are stored `Y-m-d 00:00:00`, so this compares dates rather
     * than putting a `whereBetween` on raw strings — the latter silently drops
     * the last day of the period.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private function newestFirst(Builder $query, string $column, ReportPeriod $period, ?int $limit): Collection
    {
        [$from, $to] = $period->toDateStrings();

        return $query
            ->whereDate($column, '>=', $from)
            ->whereDate($column, '<=', $to)
            ->orderByDesc($column)
            ->orderByDesc('id')
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get();
    }
}
