import { Head } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
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
    PaymentMethodTotal,
    PeriodProps,
    SalesReport,
    SalesSeriesRow,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: summary.url() },
    { title: 'Sales' },
];

export default function SalesReportPage({
    period,
    presets,
    sales,
    previous,
    series,
    paymentMethods,
}: PeriodProps & {
    sales: SalesReport;
    previous: SalesReport;
    series: SalesSeriesRow[];
    paymentMethods: PaymentMethodTotal[];
}) {
    const margin = formatPercent(sales.gross_profit, sales.revenue);

    return (
        <>
            <Head title="Sales report" />

            <PageHeader
                title="Sales"
                description="Posted invoices only — a draft has taken nothing off the shelf."
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="sales"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Revenue"
                    value={sales.revenue}
                    money
                    previous={previous.revenue}
                    hint={`${sales.invoice_count} invoices`}
                />
                <StatTile
                    label="Gross profit"
                    value={sales.gross_profit}
                    money
                    colored
                    previous={previous.gross_profit}
                    hint={margin ? `${margin} margin` : undefined}
                />
                <StatTile
                    label="Average invoice"
                    value={sales.average_invoice}
                    money
                    previous={previous.average_invoice}
                />
                <StatTile
                    label="Average selling price"
                    value={sales.average_unit_price}
                    money
                    previous={previous.average_unit_price}
                    hint={`${sales.units} units`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Takings and profit</CardTitle>
                    <CardDescription>
                        Cost comes from the batches each sale actually drew on,
                        so the gap between the two lines is real margin.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <TrendChart
                        interval={period.interval}
                        buckets={series.map((row) => row.bucket)}
                        series={[
                            {
                                key: 'revenue',
                                label: 'Revenue',
                                shape: 'area',
                                values: series.map((row) => row.revenue),
                            },
                            {
                                key: 'gross_profit',
                                label: 'Gross profit',
                                shape: 'line',
                                values: series.map((row) => row.gross_profit),
                            },
                        ]}
                    />
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>How it was paid for</CardTitle>
                        <CardDescription>
                            With no named buyer to analyse by, this is one of
                            the three ways sales are read — the others being
                            product and period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Method</TableHead>
                                        <TableHead className="text-right">
                                            Invoices
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Share
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paymentMethods.map((row) => (
                                        <TableRow key={row.method}>
                                            <TableCell className="font-medium">
                                                {row.label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.invoice_count}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={row.revenue}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {formatPercent(
                                                    row.revenue,
                                                    sales.revenue,
                                                ) ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Per unit</CardTitle>
                        <CardDescription>
                            What the average unit sold for, cost, and made.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        {sales.units === 0 ? (
                            <EmptyState
                                title="Nothing sold"
                                description="No posted sales fall in this period."
                            />
                        ) : (
                            <>
                                <Row
                                    label="Average selling price"
                                    amount={sales.average_unit_price}
                                />
                                <Row
                                    label="Average cost"
                                    amount={sales.average_unit_cost}
                                />
                                <Row
                                    label="Average profit"
                                    amount={sales.average_unit_profit}
                                    strong
                                />
                                <Row
                                    label="Cost of goods sold"
                                    amount={sales.cost_of_goods_sold}
                                />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Row({
    label,
    amount,
    strong = false,
}: {
    label: string;
    amount: number;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'flex items-center justify-between border-t pt-2 font-medium'
                    : 'flex items-center justify-between'
            }
        >
            <span className={strong ? undefined : 'text-muted-foreground'}>
                {label}
            </span>
            <MoneyDisplay amount={amount} />
        </div>
    );
}

SalesReportPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
