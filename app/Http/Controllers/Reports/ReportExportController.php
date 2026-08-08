<?php

namespace App\Http\Controllers\Reports;

use App\Http\Concerns\InteractsWithReports;
use App\Http\Controllers\Controller;
use App\Queries\ExpenseReportQuery;
use App\Queries\InventoryReportQuery;
use App\Queries\ProductProfitabilityQuery;
use App\Queries\ProfitReportQuery;
use App\Queries\SalesReportQuery;
use App\Queries\SupplierSummaryQuery;
use App\Support\Csv;
use App\Support\Money;
use App\Support\ReportPeriod;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The same figures the screens show, as a file someone can open in a
 * spreadsheet.
 *
 * Each export goes back to the report query rather than to whatever was last
 * rendered, so a downloaded file and the screen it came from can never
 * disagree.
 */
class ReportExportController extends Controller
{
    use InteractsWithReports;

    public function __construct(
        private readonly SalesReportQuery $sales,
        private readonly ExpenseReportQuery $expenses,
        private readonly ProfitReportQuery $profit,
        private readonly ProductProfitabilityQuery $products,
        private readonly SupplierSummaryQuery $suppliers,
        private readonly InventoryReportQuery $inventory,
    ) {}

    public function __invoke(string $report): StreamedResponse
    {
        $period = $this->reportPeriod();

        [$headings, $rows] = match ($report) {
            'summary' => $this->summaryRows($period),
            'sales' => $this->salesRows($period),
            'purchases' => $this->purchaseRows($period),
            'expenses' => $this->expenseRows($period),
            'products' => $this->productRows($period),
            'inventory' => $this->inventoryRows($period),
            default => abort(Response::HTTP_NOT_FOUND),
        };

        [$from, $to] = $period->toDateStrings();

        return response()->streamDownload(
            fn () => print Csv::encode($headings, $rows),
            "{$report}-{$from}-to-{$to}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * The profit and loss, one figure per row. A wide single row is hard to
     * read in a spreadsheet, and this is a statement rather than a table.
     *
     * @return array{list<string>, list<array<int, Money|int|string>>}
     */
    private function summaryRows(ReportPeriod $period): array
    {
        $report = $this->profit->get($period);

        return [
            ['Figure', 'Amount'],
            [
                ['Revenue', $report['revenue']],
                ['Cost of goods sold', $report['cost_of_goods_sold']],
                ['Gross profit', $report['gross_profit']],
                ['Expenses', $report['expenses']],
                ['Net profit', $report['net_profit']],
                ['Invoices', $report['invoice_count']],
                ['Units sold', $report['units']],
                ['Days in period', $report['days']],
                ['Average income per day', $report['averages']['income']['per_day']],
                ['Average income per week', $report['averages']['income']['per_week']],
                ['Average income per month', $report['averages']['income']['per_month']],
                ['Average outcome per day', $report['averages']['outcome']['per_day']],
                ['Average outcome per week', $report['averages']['outcome']['per_week']],
                ['Average outcome per month', $report['averages']['outcome']['per_month']],
                ['Average net profit per day', $report['averages']['net_profit']['per_day']],
                ['Average net profit per week', $report['averages']['net_profit']['per_week']],
                ['Average net profit per month', $report['averages']['net_profit']['per_month']],
            ],
        ];
    }

    /**
     * @return array{list<string>, list<array<int, Money|int|string>>}
     */
    private function salesRows(ReportPeriod $period): array
    {
        return [
            ['Date', 'Revenue', 'Cost of goods sold', 'Gross profit'],
            array_map(
                static fn (array $row): array => [
                    $row['bucket'],
                    Money::fromMinorUnits($row['revenue']),
                    Money::fromMinorUnits($row['cost_of_goods_sold']),
                    Money::fromMinorUnits($row['gross_profit']),
                ],
                $this->sales->series($period),
            ),
        ];
    }

    /**
     * @return array{list<string>, list<array<int, Money|int|string>>}
     */
    private function purchaseRows(ReportPeriod $period): array
    {
        return [
            ['Supplier', 'Invoices', 'Units', 'Goods', 'Freight and duty', 'Total', 'Average invoice', 'Last invoiced'],
            array_map(
                static fn (array $row): array => [
                    $row['name'],
                    $row['invoice_count'],
                    $row['units'],
                    $row['goods'],
                    $row['additional_costs'],
                    $row['total'],
                    $row['average_invoice'],
                    $row['last_invoiced_on'] ?? '',
                ],
                $this->suppliers->get($period),
            ),
        ];
    }

    /**
     * @return array{list<string>, list<array<int, Money|int|string>>}
     */
    private function expenseRows(ReportPeriod $period): array
    {
        return [
            ['Category', 'Entries', 'Total'],
            array_map(
                static fn (array $row): array => [$row['name'], $row['count'], $row['total']],
                $this->expenses->get($period)['categories'],
            ),
        ];
    }

    /**
     * @return array{list<string>, list<array<int, Money|int|string>>}
     */
    private function productRows(ReportPeriod $period): array
    {
        return [
            ['Code', 'Product', 'Units', 'Revenue', 'Cost of goods sold', 'Gross profit', 'Average price', 'Average cost'],
            array_map(
                static fn (array $row): array => [
                    $row['code'],
                    $row['name'],
                    $row['units'],
                    $row['revenue'],
                    $row['cost_of_goods_sold'],
                    $row['gross_profit'],
                    $row['average_unit_price'],
                    $row['average_unit_cost'],
                ],
                $this->products->get($period),
            ),
        ];
    }

    /**
     * @return array{list<string>, list<array<int, Money|bool|int|string>>}
     */
    private function inventoryRows(ReportPeriod $period): array
    {
        return [
            ['Code', 'Product', 'On hand', 'Value', 'Units sold', 'Last sold', 'Dead stock'],
            array_map(
                static fn (array $row): array => [
                    $row['code'],
                    $row['name'],
                    $row['on_hand'],
                    $row['value'],
                    $row['units_sold'],
                    $row['last_sold_on'] ?? '',
                    $row['is_dead'],
                ],
                $this->inventory->get($period)['products'],
            ),
        ];
    }
}
