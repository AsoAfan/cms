import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import {
    ActivityTable,
    combineActivity,
} from '@/components/reports/activity-table';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Activity, CashFlow, PeriodProps } from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports' }];

/** The tabs this screen offers, in the order it shows them. */
const TABS = ['totals', 'sale', 'purchase', 'expense'] as const;

type ReportTab = (typeof TABS)[number];

/**
 * The tab named in the URL, so the dashboard's "View all" lands on the one the
 * user was already looking at. Anything unrecognised falls back to the totals.
 */
function requestedTab(url: string): ReportTab {
    const asked = new URLSearchParams(url.split('?')[1] ?? '').get('tab');

    return TABS.includes(asked as ReportTab) ? (asked as ReportTab) : 'totals';
}

export default function Reports({
    period,
    presets,
    cashFlow,
    previous,
    activity,
}: PeriodProps & {
    cashFlow: CashFlow;
    previous: CashFlow;
    activity: Activity;
}) {
    const url = usePage().url;
    const combined = useMemo(() => combineActivity(activity), [activity]);

    return (
        <>
            <Head title="Reports" />

            <PageHeader
                title="Reports"
                description={`Money in and out over ${period.label.toLowerCase()}.`}
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportable
                    />
                }
            />

            <Tabs defaultValue={requestedTab(url)} className="gap-4">
                <TabsList variant="line">
                    <TabsTrigger value="totals">Totals</TabsTrigger>
                    <TabsTrigger value="sale">Sales</TabsTrigger>
                    <TabsTrigger value="purchase">Purchases</TabsTrigger>
                    <TabsTrigger value="expense">Expenses</TabsTrigger>
                </TabsList>

                <TabsContent value="totals" className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatTile
                            label="Income"
                            value={cashFlow.income}
                            money
                            previous={previous.income}
                            hint="Taken on posted sales"
                        />
                        <StatTile
                            label="Outcome"
                            value={cashFlow.outcome}
                            money
                            previous={previous.outcome}
                            hint={
                                <span className="inline-flex flex-wrap gap-x-1">
                                    <MoneyDisplay amount={cashFlow.purchases} />{' '}
                                    of stock ·
                                    <MoneyDisplay
                                        amount={cashFlow.expenses}
                                    />{' '}
                                    of expenses
                                </span>
                            }
                        />
                        <StatTile
                            label="Net"
                            value={cashFlow.net}
                            money
                            colored
                            previous={previous.net}
                            hint="Income less outcome"
                        />
                    </div>

                    <ActivityTable
                        tab="all"
                        rows={combined}
                        emptyDescription="Posted documents appear here once there are some in this period."
                    />
                </TabsContent>

                <TabsContent value="sale">
                    <ActivityTable
                        tab="sale"
                        rows={activity.sales}
                        total={cashFlow.income}
                    />
                </TabsContent>

                <TabsContent value="purchase">
                    <ActivityTable
                        tab="purchase"
                        rows={activity.purchases}
                        total={cashFlow.purchases}
                    />
                </TabsContent>

                <TabsContent value="expense">
                    <ActivityTable
                        tab="expense"
                        rows={activity.expenses}
                        total={cashFlow.expenses}
                    />
                </TabsContent>
            </Tabs>
        </>
    );
}

Reports.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
