<?php

namespace App\Http\Controllers;

use App\Enums\ReportPreset;
use App\Http\Concerns\InteractsWithReports;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Queries\InventoryReportQuery;
use App\Queries\ProductProfitabilityQuery;
use App\Queries\ProfitReportQuery;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithReports;

    /**
     * How many recent documents the activity feed shows. Enough to recognise
     * today's work, not so many that it becomes a list screen.
     */
    private const int RECENT_LIMIT = 6;

    public function __construct(
        private readonly ProfitReportQuery $profit,
        private readonly ProductProfitabilityQuery $products,
        private readonly InventoryReportQuery $inventory,
    ) {}

    /**
     * The trading position at a glance.
     *
     * Defaults to the last 30 days but honours the same `from`/`to`/`preset`
     * parameters every report screen reads, so the dashboard and the reports
     * are never answering for different windows.
     */
    public function index(): Response
    {
        $period = $this->reportPeriod(ReportPreset::Last30Days);
        $inventory = $this->inventory->get($period);

        return Inertia::render('dashboard', [
            ...$this->periodProps($period),
            'profit' => $this->profit->get($period),
            'previous' => $this->profit->get($period->previous()),
            'series' => $this->profit->series($period),
            'topProducts' => array_slice($this->products->get($period), 0, 5),
            'inventory' => [
                'total_value' => $inventory['total_value'],
                'total_units' => $inventory['total_units'],
                'dead_value' => $inventory['dead_value'],
                'dead_count' => $inventory['dead_count'],
            ],
            'recent' => $this->recentActivity(),
        ]);
    }

    /**
     * The last few documents of each kind, newest first — what was recorded,
     * rather than what it came to.
     *
     * @return array{
     *     sales: list<array{id: int, number: string, date: string, status: string, total: Money}>,
     *     purchases: list<array{id: int, number: string, date: string, status: string, supplier: string, total: Money}>,
     *     expenses: list<array{id: int, category: string, date: string, reference: string|null, total: Money}>,
     * }
     */
    private function recentActivity(): array
    {
        return [
            'sales' => array_values(Sale::query()
                ->with('lines')
                ->latest('sold_on')
                ->latest('id')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'date' => $sale->sold_on->toDateString(),
                    'status' => $sale->status->value,
                    'total' => $sale->total(),
                ])
                ->all()),

            'purchases' => array_values(Purchase::query()
                ->with(['lines', 'additionalCosts', 'supplier:id,name'])
                ->latest('invoiced_on')
                ->latest('id')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (Purchase $purchase): array => [
                    'id' => $purchase->id,
                    'number' => $purchase->number,
                    'date' => $purchase->invoiced_on->toDateString(),
                    'status' => $purchase->status->value,
                    'supplier' => $purchase->supplier->name,
                    'total' => $purchase->total(),
                ])
                ->all()),

            'expenses' => array_values(Expense::query()
                ->with('category:id,name')
                ->latest('spent_on')
                ->latest('id')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'category' => $expense->category->name,
                    'date' => $expense->spent_on->toDateString(),
                    'reference' => $expense->reference,
                    'total' => $expense->amount,
                ])
                ->all()),
        ];
    }
}
