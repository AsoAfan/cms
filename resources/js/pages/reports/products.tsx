import { Head } from '@inertiajs/react';
import { Package } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { ReportNav } from '@/components/reports/report-nav';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
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
import { inventory, summary } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type { PeriodProps, ProductProfitability } from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: summary.url() },
    { title: 'Products' },
];

export default function ProductReportPage({
    period,
    presets,
    products,
}: PeriodProps & { products: ProductProfitability[] }) {
    const revenue = products.reduce((total, row) => total + row.revenue, 0);
    const profit = products.reduce((total, row) => total + row.gross_profit, 0);
    const units = products.reduce((total, row) => total + row.units, 0);
    const best = products[0];

    return (
        <>
            <Head title="Product profitability" />

            <PageHeader
                title="Products"
                description="What each product made, costed from the batches its sales actually drew on."
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="products"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Products sold"
                    value={String(products.length)}
                    hint={`${units} units`}
                />
                <StatTile label="Revenue" value={revenue} money />
                <StatTile
                    label="Gross profit"
                    value={profit}
                    money
                    colored
                    hint={
                        formatPercent(profit, revenue)
                            ? `${formatPercent(profit, revenue)} margin`
                            : undefined
                    }
                />
                <StatTile
                    label="Best seller"
                    value={best?.code ?? '—'}
                    hint={best ? best.name : undefined}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Profit per product</CardTitle>
                    <CardDescription>
                        Most profitable first. Products that sold nothing are
                        not listed —{' '}
                        <a
                            href={inventory.url({
                                query: period.preset
                                    ? { preset: period.preset }
                                    : { from: period.from, to: period.to },
                            })}
                            className="underline underline-offset-4"
                        >
                            the inventory report
                        </a>{' '}
                        covers what is sitting still.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {products.length === 0 ? (
                        <EmptyState
                            icon={Package}
                            title="Nothing sold"
                            description="No posted sales fall in this period."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-right">
                                            Units
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Avg price
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Avg cost
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead className="hidden text-right lg:table-cell">
                                            Cost
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Profit
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Margin
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {row.name}
                                                    </span>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {row.code}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.units}
                                            </TableCell>
                                            <TableCell className="hidden text-right sm:table-cell">
                                                <MoneyDisplay
                                                    amount={
                                                        row.average_unit_price
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="hidden text-right sm:table-cell">
                                                <MoneyDisplay
                                                    amount={
                                                        row.average_unit_cost
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={row.revenue}
                                                />
                                            </TableCell>
                                            <TableCell className="hidden text-right lg:table-cell">
                                                <MoneyDisplay
                                                    amount={
                                                        row.cost_of_goods_sold
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                <MoneyDisplay
                                                    amount={row.gross_profit}
                                                    colored
                                                />
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {formatPercent(
                                                    row.gross_profit,
                                                    row.revenue,
                                                ) ?? '—'}
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

ProductReportPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
