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
import type { BreadcrumbItem } from '@/types';
import type {
    InventorySummary,
    PeriodProps,
    ProfitReport,
    ProfitSeriesRow,
    PurchaseReport,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports' }];

export default function ReportsSummary({
    period,
    presets,
    profit,
    previous,
    series,
    purchases,
    inventory,
}: PeriodProps & {
    profit: ProfitReport;
    previous: ProfitReport;
    series: ProfitSeriesRow[];
    purchases: PurchaseReport;
    inventory: InventorySummary;
}) {
    const grossMargin = formatPercent(profit.gross_profit, profit.revenue);
    const netMargin = formatPercent(profit.net_profit, profit.revenue);

    return (
        <>
            <Head title="Reports" />

            <PageHeader
                title="Reports"
                description={`Every figure derived from the transactions in ${period.label.toLowerCase()}.`}
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="summary"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Revenue"
                    value={profit.revenue}
                    money
                    previous={previous.revenue}
                    hint={`${profit.invoice_count} invoices`}
                />
                <StatTile
                    label="Cost of goods sold"
                    value={profit.cost_of_goods_sold}
                    money
                    previous={previous.cost_of_goods_sold}
                    hint={`${profit.units} units`}
                />
                <StatTile
                    label="Gross profit"
                    value={profit.gross_profit}
                    money
                    colored
                    previous={previous.gross_profit}
                    hint={grossMargin ? `${grossMargin} margin` : undefined}
                />
                <StatTile
                    label="Net profit"
                    value={profit.net_profit}
                    money
                    colored
                    previous={previous.net_profit}
                    hint={netMargin ? `${netMargin} margin` : undefined}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Trend</CardTitle>
                    <CardDescription>
                        Takings against what was left after the goods and the
                        running costs.
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
                                key: 'net_profit',
                                label: 'Net profit',
                                shape: 'line',
                                values: series.map((row) => row.net_profit),
                            },
                        ]}
                    />
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Profit and loss</CardTitle>
                        <CardDescription>
                            Expenses come off at the end. Buying stock never
                            appears — it becomes a cost only when it sells.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <Line label="Revenue" amount={profit.revenue} />
                        <Line
                            label="Less cost of goods sold"
                            amount={-profit.cost_of_goods_sold}
                        />
                        <Line
                            label="Gross profit"
                            amount={profit.gross_profit}
                            strong
                        />
                        <Line label="Less expenses" amount={-profit.expenses} />
                        <Line
                            label="Net profit"
                            amount={profit.net_profit}
                            strong
                            colored
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Averages</CardTitle>
                        <CardDescription>
                            Over {profit.days}{' '}
                            {profit.days === 1 ? 'day' : 'days'}. A month is the
                            mean 30.44 days, so these hold whatever length of
                            period they were measured over.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Per</TableHead>
                                        <TableHead className="text-right">
                                            Income
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Outcome
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Net profit
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(
                                        [
                                            'per_day',
                                            'per_week',
                                            'per_month',
                                        ] as const
                                    ).map((key) => (
                                        <TableRow key={key}>
                                            <TableCell className="text-muted-foreground">
                                                {key.replace('per_', '')}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={
                                                        profit.averages.income[
                                                            key
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={
                                                        profit.averages.outcome[
                                                            key
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                <MoneyDisplay
                                                    amount={
                                                        profit.averages
                                                            .net_profit[key]
                                                    }
                                                    colored
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Outcome is the goods sold plus the running costs, so
                            income less outcome is exactly net profit.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Bought in"
                    value={purchases.total}
                    money
                    hint={`${purchases.invoice_count} invoices · ${purchases.units} units`}
                />
                <StatTile
                    label="Expenses"
                    value={profit.expenses}
                    money
                    previous={previous.expenses}
                />
                <StatTile
                    label="Stock on hand"
                    value={inventory.total_value}
                    money
                    hint="At cost, at the end of the period"
                />
                <StatTile
                    label="Dead stock"
                    value={inventory.dead_value}
                    money
                    hint={`${inventory.dead_count} products with nothing sold`}
                />
            </div>
        </>
    );
}

function Line({
    label,
    amount,
    strong = false,
    colored = false,
}: {
    label: string;
    amount: number;
    strong?: boolean;
    colored?: boolean;
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
            <MoneyDisplay amount={amount} colored={colored} />
        </div>
    );
}

ReportsSummary.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
