import { Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';

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
import { summary } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type {
    PeriodProps,
    PurchaseReport,
    PurchaseSeriesRow,
    SupplierSummary,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: summary.url() },
    { title: 'Purchases' },
];

export default function PurchaseReportPage({
    period,
    presets,
    purchases,
    previous,
    series,
    suppliers,
}: PeriodProps & {
    purchases: PurchaseReport;
    previous: PurchaseReport;
    series: PurchaseSeriesRow[];
    suppliers: SupplierSummary[];
}) {
    return (
        <>
            <Head title="Purchases report" />

            <PageHeader
                title="Purchases"
                description="What was bought in, at landed cost. None of it is a cost until it sells."
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="purchases"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Total purchases"
                    value={purchases.total}
                    money
                    previous={previous.total}
                    hint={`${purchases.invoice_count} invoices from ${purchases.supplier_count} suppliers`}
                />
                <StatTile
                    label="Goods"
                    value={purchases.goods}
                    money
                    previous={previous.goods}
                    hint={`${purchases.units} units`}
                />
                <StatTile
                    label="Freight and duty"
                    value={purchases.additional_costs}
                    money
                    previous={previous.additional_costs}
                    hint="Spread across the goods at posting"
                />
                <StatTile
                    label="Average buying cost"
                    value={purchases.average_unit_cost}
                    money
                    previous={previous.average_unit_cost}
                    hint="Landed, per unit"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Spend on stock</CardTitle>
                    <CardDescription>
                        The invoice total against the goods alone — the gap is
                        the freight and duty that ends up inside unit cost.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <TrendChart
                        interval={period.interval}
                        buckets={series.map((row) => row.bucket)}
                        series={[
                            {
                                key: 'total',
                                label: 'Invoice total',
                                shape: 'area',
                                values: series.map((row) => row.total),
                            },
                            {
                                key: 'goods',
                                label: 'Goods',
                                shape: 'line',
                                values: series.map((row) => row.goods),
                            },
                        ]}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>By supplier</CardTitle>
                    <CardDescription>
                        Freight and duty count against the supplier that charged
                        them, because that is what their goods cost.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {suppliers.length === 0 ? (
                        <EmptyState
                            icon={Truck}
                            title="Nothing bought"
                            description="No posted purchase invoices fall in this period."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Supplier</TableHead>
                                        <TableHead className="text-right">
                                            Invoices
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Units
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Goods
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Freight and duty
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                        <TableHead className="hidden text-right lg:table-cell">
                                            Last invoiced
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {suppliers.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-medium">
                                                {row.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.invoice_count}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums sm:table-cell">
                                                {row.units}
                                            </TableCell>
                                            <TableCell className="hidden text-right sm:table-cell">
                                                <MoneyDisplay
                                                    amount={row.goods}
                                                />
                                            </TableCell>
                                            <TableCell className="hidden text-right sm:table-cell">
                                                <MoneyDisplay
                                                    amount={
                                                        row.additional_costs
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                <MoneyDisplay
                                                    amount={row.total}
                                                />
                                            </TableCell>
                                            <TableCell className="hidden text-right text-muted-foreground lg:table-cell">
                                                {row.last_invoiced_on ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

PurchaseReportPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
