import { Head } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { ReportNav } from '@/components/reports/report-nav';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { InventoryReport, PeriodProps } from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: summary.url() },
    { title: 'Inventory' },
];

export default function InventoryReportPage({
    period,
    presets,
    inventory,
}: PeriodProps & { inventory: InventoryReport }) {
    const [deadOnly, setDeadOnly] = useState(false);

    const rows = inventory.products.filter((row) =>
        deadOnly ? row.is_dead : row.on_hand > 0 || row.units_sold > 0,
    );

    return (
        <>
            <Head title="Inventory report" />

            <PageHeader
                title="Inventory"
                description={`What was on the shelf on ${period.to}, at cost, and what did not move.`}
                actions={
                    <ReportPeriodFilter
                        period={period}
                        presets={presets}
                        exportAs="inventory"
                    />
                }
            />

            <ReportNav period={period} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Stock value"
                    value={inventory.total_value}
                    money
                    hint="At the cost actually paid, freight included"
                />
                <StatTile
                    label="Units on hand"
                    value={String(inventory.total_units)}
                    hint={`Across ${inventory.stocked_count} products`}
                />
                <StatTile
                    label="Dead stock"
                    value={inventory.dead_value}
                    money
                    hint={
                        formatPercent(
                            inventory.dead_value,
                            inventory.total_value,
                        )
                            ? `${formatPercent(inventory.dead_value, inventory.total_value)} of the value`
                            : undefined
                    }
                />
                <StatTile
                    label="Not selling"
                    value={String(inventory.dead_count)}
                    hint={`${inventory.dead_units} units with nothing sold`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Stock on hand</CardTitle>
                    <CardDescription>
                        Valuation rewinds to the end of the period, so a report
                        run today for last month agrees with the accounts as
                        they stood then. Dead stock is stock on hand that
                        nothing sold out of during the period.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant={deadOnly ? 'outline' : 'secondary'}
                            size="sm"
                            onClick={() => setDeadOnly(false)}
                        >
                            All stock
                        </Button>
                        <Button
                            variant={deadOnly ? 'secondary' : 'outline'}
                            size="sm"
                            onClick={() => setDeadOnly(true)}
                        >
                            Dead stock only
                        </Button>
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            icon={Boxes}
                            title={
                                deadOnly
                                    ? 'Everything moved'
                                    : 'Nothing in stock'
                            }
                            description={
                                deadOnly
                                    ? 'Every product with stock on hand sold at least one unit in this period.'
                                    : 'Post a purchase to put stock on the shelf.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-right">
                                            On hand
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Value
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sold
                                        </TableHead>
                                        <TableHead className="hidden text-right sm:table-cell">
                                            Last sold
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Status
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
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
                                                {row.on_hand}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={row.value}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.units_sold}
                                            </TableCell>
                                            <TableCell className="hidden text-right text-muted-foreground sm:table-cell">
                                                {row.last_sold_on ?? 'Never'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {row.is_dead ? (
                                                    <Badge variant="outline">
                                                        Not moving
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
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

InventoryReportPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
