<?php

namespace App\Http\Controllers\Reports;

use App\Http\Concerns\InteractsWithReports;
use App\Http\Controllers\Controller;
use App\Queries\ExpenseReportQuery;
use App\Queries\InventoryReportQuery;
use App\Queries\ProductProfitabilityQuery;
use App\Queries\ProfitReportQuery;
use App\Queries\PurchaseReportQuery;
use App\Queries\SalesReportQuery;
use App\Queries\SupplierSummaryQuery;
use App\Support\Money;
use App\Support\ReportPeriod;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every figure on these screens is derived from transactions over the window
 * in the query string. Nothing is read from a stored summary, so a report can
 * never disagree with the documents behind it.
 *
 * `Money` serializes to minor units, so query results are handed to Inertia
 * as they come back — the frontend formats them.
 */
class ReportController extends Controller
{
    use InteractsWithReports;

    public function __construct(
        private readonly SalesReportQuery $sales,
        private readonly PurchaseReportQuery $purchases,
        private readonly ExpenseReportQuery $expenses,
        private readonly ProfitReportQuery $profit,
        private readonly ProductProfitabilityQuery $products,
        private readonly SupplierSummaryQuery $suppliers,
        private readonly InventoryReportQuery $inventory,
    ) {}

    /**
     * The headline: revenue, what it cost, what was left.
     */
    public function summary(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/index', [
            ...$this->periodProps($period),
            'profit' => $this->profit->get($period),
            // The same figures for the stretch immediately before, so each
            // tile can say whether it is up or down on a like-for-like window.
            'previous' => $this->profit->get($period->previous()),
            'series' => $this->profit->series($period),
            'purchases' => $this->purchases->get($period),
            'inventory' => $this->inventorySummary($period),
        ]);
    }

    public function sales(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/sales', [
            ...$this->periodProps($period),
            'sales' => $this->sales->get($period),
            'previous' => $this->sales->get($period->previous()),
            'series' => $this->sales->series($period),
            'paymentMethods' => $this->sales->byPaymentMethod($period),
        ]);
    }

    public function purchases(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/purchases', [
            ...$this->periodProps($period),
            'purchases' => $this->purchases->get($period),
            'previous' => $this->purchases->get($period->previous()),
            'series' => $this->purchases->series($period),
            'suppliers' => $this->suppliers->get($period),
        ]);
    }

    public function expenses(): Response
    {
        $period = $this->reportPeriod();
        $report = $this->expenses->get($period);

        return Inertia::render('reports/expenses', [
            ...$this->periodProps($period),
            'expenses' => $report,
            'previous' => $this->expenses->total($period->previous()),
            'series' => $this->expenses->series($period),
            'averages' => $period->averages($report['total']),
        ]);
    }

    public function products(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/products', [
            ...$this->periodProps($period),
            'products' => $this->products->get($period),
        ]);
    }

    public function inventory(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/inventory', [
            ...$this->periodProps($period),
            'inventory' => $this->inventory->get($period),
        ]);
    }

    /**
     * Valuation for the summary screen, without the per-product rows it does
     * not show.
     *
     * @return array{total_value: Money, dead_value: Money, dead_count: int}
     */
    private function inventorySummary(ReportPeriod $period): array
    {
        $report = $this->inventory->get($period);

        return [
            'total_value' => $report['total_value'],
            'dead_value' => $report['dead_value'],
            'dead_count' => $report['dead_count'],
        ];
    }
}
