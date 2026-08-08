import { Head } from '@inertiajs/react';

import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { ReportNav } from '@/components/reports/report-nav';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { TrendChart } from '@/components/reports/trend-chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatPercent } from '@/lib/money';
import { summary } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type {
    Averages,
    ExpenseReport,
    ExpenseSeriesRow,
    PeriodProps,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: summary.url() },
    { title: 'Expenses' },
];

export default function ExpenseReportPage({
    period,
    presets,
    expenses,
    previous,
    series,
    averages,
}: PeriodProps & {
    expenses: ExpenseReport;
    previous: number;
    series: ExpenseSeriesRow[];
    averages: Averages;
}) {
    return (
        <>
            <Head title="Expenses report" />

            <PageHeader
                title="Expenses"
                description="What it cost to trade. Buying stock is a purchase, not an expense."
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="expenses"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Total expenses"
                    value={expenses.total}
                    money
                    previous={previous}
                    hint={`${expenses.count} entries`}
                />
                <StatTile
                    label="Largest category"
                    value={expenses.largest_category ?? '—'}
                    hint={
                        expenses.categories[0] &&
                        expenses.largest_category !== null
                            ? (formatPercent(
                                  expenses.categories[0].total,
                                  expenses.total,
                              ) ?? undefined)
                            : undefined
                    }
                />
                <StatTile
                    label="Average per week"
                    value={averages.per_week}
                    money
                />
                <StatTile
                    label="Average per month"
                    value={averages.per_month}
                    money
                    hint="A month being the mean 30.44 days"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Spend over time</CardTitle>
                    <CardDescription>
                        Rent is a cost the day it is paid, which is why expenses
                        are dated by when they were spent.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <TrendChart
                        interval={period.interval}
                        buckets={series.map((row) => row.bucket)}
                        series={[
                            {
                                key: 'total',
                                label: 'Expenses',
                                shape: 'line',
                                values: series.map((row) => row.total),
                            },
                        ]}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>By category</CardTitle>
                    <CardDescription>
                        Every category is listed. A zero is an answer; a missing
                        row looks like an oversight.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Category</TableHead>
                                    <TableHead className="text-right">
                                        Entries
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Share
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.categories.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell className="font-medium">
                                            {category.name}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {category.count}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <MoneyDisplay
                                                amount={category.total}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {formatPercent(
                                                category.total,
                                                expenses.total,
                                            ) ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

ExpenseReportPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
