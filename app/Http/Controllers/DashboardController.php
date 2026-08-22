<?php

namespace App\Http\Controllers;

use App\Enums\ReportPreset;
use App\Http\Concerns\InteractsWithReports;
use App\Queries\ActivityQuery;
use App\Queries\CashFlowQuery;
use App\Queries\CustomerBalanceQuery;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithReports;

    /**
     * How many documents of each kind the activity table shows. Enough to
     * recognise today's work, not so many that it becomes a list screen —
     * the whole period lives on the report screen.
     */
    private const int RECENT_LIMIT = 6;

    public function __construct(
        private readonly CashFlowQuery $cashFlow,
        private readonly ActivityQuery $activity,
        private readonly CustomerBalanceQuery $balances,
    ) {}

    /**
     * The trading position at a glance: the same three figures the report
     * screen leads with, over the same window, plus what was recorded lately.
     *
     * Defaults to the last 30 days but honours the same `from`/`to`/`preset`
     * parameters the report screen reads, so the dashboard and the report are
     * never answering for different windows.
     *
     * Orders still on their way are listed here and nowhere else: goods nobody
     * has yet are work still to do rather than a figure, and nothing they
     * contain reaches a total on either screen.
     */
    public function index(): Response
    {
        $period = $this->reportPeriod(ReportPreset::Last30Days);

        return Inertia::render('dashboard', [
            ...$this->periodProps($period),
            'cashFlow' => $this->cashFlow->get($period),
            'previous' => $this->cashFlow->get($period->previous()),
            'recent' => $this->activity->get($period, limit: self::RECENT_LIMIT, pending: true),
            // A position rather than a flow — what is unpaid today, whatever
            // window the tiles are showing.
            'owed' => $this->balances->total()->minorUnits,
        ]);
    }
}
