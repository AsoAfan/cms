import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import {
    ActivityTable,
    combineActivity,
} from '@/components/reports/activity-table';
import { BankBalances } from '@/components/reports/bank-balances';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    Activity,
    BankBalances as BankBalancesProps,
    CashFlow,
    GoodsOwed,
    PeriodProps,
} from '@/types/reports';

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
    owed,
    goodsOwed,
    bankBalances,
}: PeriodProps & {
    cashFlow: CashFlow;
    previous: CashFlow;
    activity: Activity;
    /** What customers owe today — see `CustomerBalanceQuery`. */
    owed: number;
    /** What the shop owes in goods — see `GoodsOwedQuery`. */
    goodsOwed: GoodsOwed;
    /** What the accounts hold today — see `BankBalanceQuery`. */
    bankBalances: BankBalancesProps;
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
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <StatTile
                            label="Income"
                            value={cashFlow.income}
                            money
                            previous={previous.income}
                            hint="Invoiced on delivered sales"
                        />
                        {/* Two income figures, because on credit terms they are
                            two different questions: what was sold, and what
                            actually came through the door. */}
                        <StatTile
                            label="Collected"
                            value={cashFlow.collected}
                            money
                            previous={previous.collected}
                            hint="Taken at the till, plus repayments in"
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
                        {/* Not period arithmetic: what is unpaid today. It has no
                            comparison figure for the same reason. */}
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
                        {/* The mirror of it, and the reason it is here: goods
                            sold that were never bought. Shown only when there
                            are some — a permanent zero is not news, and a shop
                            that owes nothing has nothing to act on. */}
                        {goodsOwed.items > 0 && (
                            <StatTile
                                label="Owed by you"
                                value={goodsOwed.value}
                                money
                                hint={`${goodsOwed.items} ${
                                    goodsOwed.items === 1 ? 'item' : 'items'
                                } sold and not in stock`}
                            />
                        )}
                        {/* A position too, and shown only once there is an
                            account to show — a zero tile on a shop that deals
                            in cash is a figure about nothing. */}
                        {bankBalances.accounts.length > 0 && (
                            <StatTile
                                label="In the bank"
                                value={bankBalances.total}
                                money
                                colored
                                hint={`Across ${bankBalances.accounts.length} ${
                                    bankBalances.accounts.length === 1
                                        ? 'account'
                                        : 'accounts'
                                }`}
                            />
                        )}
                    </div>

                    <BankBalances {...bankBalances} />

                    <ActivityTable
                        tab="all"
                        rows={combined}
                        emptyDescription="Documents appear here once their goods have moved in this period."
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
