<?php

namespace App\Http\Controllers\Reports;

use App\Http\Concerns\InteractsWithReports;
use App\Http\Controllers\Controller;
use App\Queries\CashFlowQuery;
use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The same figures the screen shows, as a file someone can open in a
 * spreadsheet.
 *
 * The export goes back to the query rather than to whatever was last rendered,
 * so a downloaded file and the screen it came from can never disagree.
 *
 * One figure per row: a wide single row is hard to read in a spreadsheet, and
 * this is a statement rather than a table.
 */
class ReportExportController extends Controller
{
    use InteractsWithReports;

    public function __construct(private readonly CashFlowQuery $cashFlow) {}

    public function __invoke(): StreamedResponse
    {
        $period = $this->reportPeriod();
        $report = $this->cashFlow->get($period);
        [$from, $to] = $period->toDateStrings();

        $rows = [
            ['Income', $report['income']],
            ['Purchases', $report['purchases']],
            ['Expenses', $report['expenses']],
            ['Outcome', $report['outcome']],
            ['Net', $report['net']],
            ['Days in period', $report['days']],
        ];

        foreach (['income', 'outcome', 'net'] as $figure) {
            foreach (['day', 'week', 'month'] as $horizon) {
                $rows[] = [
                    'Average '.$figure.' per '.$horizon,
                    $report['averages'][$figure]['per_'.$horizon],
                ];
            }
        }

        return response()->streamDownload(
            fn () => print Csv::encode(['Figure', 'Amount'], $rows),
            "report-{$from}-to-{$to}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
