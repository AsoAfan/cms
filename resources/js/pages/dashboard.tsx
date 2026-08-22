import { Head, Link } from '@inertiajs/react';
import { ArrowRight, LineChart } from 'lucide-react';
import { useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import {
    ActivityTable,
    combineActivity,
} from '@/components/reports/activity-table';
import type { ActivityTab } from '@/components/reports/activity-table';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import purchases from '@/routes/purchases';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type { Activity, CashFlow, PeriodProps } from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard' }];

/** How many rows the combined tab shows, matching the per-kind server limit. */
const COMBINED_LIMIT = 6;

export default function Dashboard({
    period,
    presets,
    cashFlow,
    previous,
    recent,
    owed,
}: PeriodProps & {
    cashFlow: CashFlow;
    previous: CashFlow;
    recent: Activity;
    /** What customers owe today — see `CustomerBalanceQuery`. */
    owed: number;
}) {
    const [tab, setTab] = useState<ActivityTab>('all');

    const combined = useMemo(
        () => combineActivity(recent, COMBINED_LIMIT),
        [recent],
    );

    const nothingYet =
        cashFlow.income === 0 &&
        cashFlow.outcome === 0 &&
        combined.length === 0;

    /** The window as the report screen reads it, so both show the same period. */
    const periodQuery = period.preset
        ? { preset: period.preset }
        : { from: period.from, to: period.to };

    return (
        <>
            <Head title="Dashboard" />

            <PageHeader
                title="Dashboard"
                description={`Trading over ${period.label.toLowerCase()}.`}
                actions={
                    <>
                        <ReportPeriodFilter period={period} presets={presets} />
                        <Button
                            variant="outline"
                            render={
                                <Link
                                    href={reportsIndex.url({
                                        query: periodQuery,
                                    })}
                                />
                            }
                        >
                            Reports
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </>
                }
            />

            {nothingYet ? (
                <Card>
                    <CardContent>
                        <EmptyState
                            icon={LineChart}
                            title="Nothing to show yet"
                            description="Figures appear once a purchase arrives and something sells."
                            action={
                                <Button
                                    render={<Link href={purchases.index()} />}
                                >
                                    Record a purchase
                                </Button>
                            }
                        />
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile
                            label="Income"
                            value={cashFlow.income}
                            money
                            previous={previous.income}
                            hint={
                                <span>
                                    <MoneyDisplay amount={cashFlow.collected} />{' '}
                                    of it collected
                                </span>
                            }
                        />
                        <StatTile
                            label="Outcome"
                            value={cashFlow.outcome}
                            money
                            previous={previous.outcome}
                            hint="Stock bought and expenses paid"
                        />
                        <StatTile
                            label="Net"
                            value={cashFlow.net}
                            money
                            colored
                            previous={previous.net}
                            hint={
                                <span>
                                    <MoneyDisplay
                                        amount={cashFlow.averages.net.per_day}
                                    />{' '}
                                    a day
                                </span>
                            }
                        />
                        {/* A position, not a flow: what is unpaid today, whatever
                            window the other three are showing. No comparison
                            figure for the same reason. */}
                        <StatTile
                            label="Owed to you"
                            value={owed}
                            money
                            hint={
                                owed === 0
                                    ? 'Nobody owes anything'
                                    : 'Out on customer loans'
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent activity</CardTitle>
                            <CardDescription>
                                The latest documents of each kind, including
                                what is still on its way.
                            </CardDescription>
                            <CardAction>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    render={
                                        <Link
                                            href={reportsIndex.url({
                                                query: {
                                                    ...periodQuery,
                                                    tab: reportTab(tab),
                                                },
                                            })}
                                        />
                                    }
                                >
                                    View all
                                    <ArrowRight data-icon="inline-end" />
                                </Button>
                            </CardAction>
                        </CardHeader>

                        <CardContent>
                            <Tabs
                                value={tab}
                                onValueChange={(value) =>
                                    setTab(value as ActivityTab)
                                }
                                className="gap-4"
                            >
                                <TabsList variant="line">
                                    <TabsTrigger value="all">All</TabsTrigger>
                                    <TabsTrigger value="sale">
                                        Sales
                                    </TabsTrigger>
                                    <TabsTrigger value="purchase">
                                        Purchases
                                    </TabsTrigger>
                                    <TabsTrigger value="expense">
                                        Expenses
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="all">
                                    <ActivityTable tab="all" rows={combined} />
                                </TabsContent>
                                <TabsContent value="sale">
                                    <ActivityTable
                                        tab="sale"
                                        rows={recent.sales}
                                    />
                                </TabsContent>
                                <TabsContent value="purchase">
                                    <ActivityTable
                                        tab="purchase"
                                        rows={recent.purchases}
                                    />
                                </TabsContent>
                                <TabsContent value="expense">
                                    <ActivityTable
                                        tab="expense"
                                        rows={recent.expenses}
                                    />
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                </>
            )}
        </>
    );
}

/**
 * The report tab that answers for a dashboard tab. The combined view lives
 * under the report's totals rather than in a tab of its own.
 */
function reportTab(tab: ActivityTab): string {
    return tab === 'all' ? 'totals' : tab;
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
