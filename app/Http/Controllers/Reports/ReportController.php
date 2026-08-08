<?php

namespace App\Http\Controllers\Reports;

use App\Http\Concerns\InteractsWithReports;
use App\Http\Controllers\Controller;
use App\Queries\ActivityQuery;
use App\Queries\CashFlowQuery;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole reporting section: money in, money out, what is left, and every
 * document those three figures were added up from.
 *
 * Every figure is derived from the transactions in the window, so a report can
 * never disagree with the documents behind it. `Money` serializes to minor
 * units, so the query result is handed to Inertia as it comes back and the
 * frontend formats it.
 *
 * The activity lists are unlimited on purpose — the period is the bound, and a
 * report that showed only the first few would not be a report. They are posted
 * only, matching the totals, so every row on screen is behind the figures
 * above it.
 */
class ReportController extends Controller
{
    use InteractsWithReports;

    public function __construct(
        private readonly CashFlowQuery $cashFlow,
        private readonly ActivityQuery $activity,
    ) {}

    public function __invoke(): Response
    {
        $period = $this->reportPeriod();

        return Inertia::render('reports/index', [
            ...$this->periodProps($period),
            'cashFlow' => $this->cashFlow->get($period),
            // The same figures for the stretch immediately before, so each
            // tile can say whether it is up or down on a like-for-like window.
            'previous' => $this->cashFlow->get($period->previous()),
            'activity' => $this->activity->get($period),
        ]);
    }
}
