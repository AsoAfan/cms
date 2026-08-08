import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    LineChart,
    Receipt,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { ReportPeriodFilter } from '@/components/reports/report-period-filter';
import { StatTile } from '@/components/reports/stat-tile';
import { TrendChart } from '@/components/reports/trend-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatPercent } from '@/lib/money';
import expenses from '@/routes/expenses';
import purchases from '@/routes/purchases';
import { products as productsReport, summary } from '@/routes/reports';
import sales from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type {
    PeriodProps,
    ProductProfitability,
    ProfitReport,
    ProfitSeriesRow,
    RecentActivity,
} from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard' }];

export default function Dashboard({
    period,
    presets,
    profit,
    previous,
    series,
    topProducts,
    inventory,
    recent,
}: PeriodProps & {
    profit: ProfitReport;
    previous: ProfitReport;
    series: ProfitSeriesRow[];
    topProducts: ProductProfitability[];
    inventory: {
        total_value: number;
        total_units: number;
        dead_value: number;
        dead_count: number;
    };
    recent: RecentActivity;
}) {
    const nothingYet =
        profit.revenue === 0 &&
        profit.expenses === 0 &&
        recent.sales.length === 0 &&
        recent.purchases.length === 0;

    const margin = formatPercent(profit.net_profit, profit.revenue);

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
                                    href={summary.url({
                                        query: period.preset
                                            ? { preset: period.preset }
                                            : {
                                                  from: period.from,
                                                  to: period.to,
                                              },
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
                            description="Figures appear once you post a purchase and make a sale."
                            action={
                                <Button
                                    render={<Link href={purchases.create()} />}
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
                            label="Revenue"
                            value={profit.revenue}
                            money
                            previous={previous.revenue}
                            hint={`${profit.invoice_count} invoices`}
                        />
                        <StatTile
                            label="Gross profit"
                            value={profit.gross_profit}
                            money
                            colored
                            previous={previous.gross_profit}
                            hint={`${profit.units} units sold`}
                        />
                        <StatTile
                            label="Expenses"
                            value={profit.expenses}
                            money
                            previous={previous.expenses}
                        />
                        <StatTile
                            label="Net profit"
                            value={profit.net_profit}
                            money
                            colored
                            previous={previous.net_profit}
                            hint={margin ? `${margin} margin` : undefined}
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Trend</CardTitle>
                            <CardDescription>
                                Takings against what was left once the goods and
                                the running costs came off.
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
                                        values: series.map(
                                            (row) => row.revenue,
                                        ),
                                    },
                                    {
                                        key: 'net_profit',
                                        label: 'Net profit',
                                        shape: 'line',
                                        values: series.map(
                                            (row) => row.net_profit,
                                        ),
                                    },
                                ]}
                            />
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile
                            label="Stock on hand"
                            value={inventory.total_value}
                            money
                            hint={`${inventory.total_units} units at cost`}
                        />
                        <StatTile
                            label="Dead stock"
                            value={inventory.dead_value}
                            money
                            hint={`${inventory.dead_count} products not selling`}
                        />
                        <StatTile
                            label="Average income per day"
                            value={profit.averages.income.per_day}
                            money
                        />
                        <StatTile
                            label="Average profit per day"
                            value={profit.averages.net_profit.per_day}
                            money
                            colored
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Best sellers</CardTitle>
                                <CardDescription>
                                    By profit, costed from the batches each sale
                                    drew on.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {topProducts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nothing sold in this period.
                                    </p>
                                ) : (
                                    topProducts.map((product) => (
                                        <div
                                            key={product.id}
                                            className="flex items-center justify-between gap-4 text-sm"
                                        >
                                            <div className="flex min-w-0 flex-col">
                                                <span className="truncate font-medium">
                                                    {product.name}
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {product.code} ·{' '}
                                                    {product.units} sold
                                                </span>
                                            </div>
                                            <MoneyDisplay
                                                amount={product.gross_profit}
                                                colored
                                                className="shrink-0"
                                            />
                                        </div>
                                    ))
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="self-start"
                                    render={
                                        <Link href={productsReport.url()} />
                                    }
                                >
                                    All products
                                    <ArrowRight data-icon="inline-end" />
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Recent activity</CardTitle>
                                <CardDescription>
                                    The last few documents of each kind.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <ActivityGroup
                                    icon={Receipt}
                                    title="Sales"
                                    href={sales.index.url()}
                                    empty="No sales yet."
                                    items={recent.sales
                                        .slice(0, 3)
                                        .map((sale) => ({
                                            key: sale.id,
                                            href: sales.show.url(sale.id),
                                            primary: sale.number,
                                            secondary: sale.date,
                                            draft: sale.status === 'draft',
                                            total: sale.total,
                                        }))}
                                />
                                <ActivityGroup
                                    icon={ShoppingCart}
                                    title="Purchases"
                                    href={purchases.index.url()}
                                    empty="No purchases yet."
                                    items={recent.purchases
                                        .slice(0, 3)
                                        .map((purchase) => ({
                                            key: purchase.id,
                                            href: purchases.show.url(
                                                purchase.id,
                                            ),
                                            primary: purchase.number,
                                            secondary: `${purchase.date} · ${purchase.supplier}`,
                                            draft: purchase.status === 'draft',
                                            total: purchase.total,
                                        }))}
                                />
                                <ActivityGroup
                                    icon={Wallet}
                                    title="Expenses"
                                    href={expenses.index.url()}
                                    empty="No expenses yet."
                                    items={recent.expenses
                                        .slice(0, 3)
                                        .map((expense) => ({
                                            key: expense.id,
                                            primary: expense.category,
                                            secondary:
                                                expense.reference ??
                                                expense.date,
                                            total: expense.total,
                                        }))}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </>
    );
}

type ActivityItem = {
    key: number;
    href?: string;
    primary: string;
    secondary: string;
    draft?: boolean;
    total: number;
};

function ActivityGroup({
    icon: Icon,
    title,
    href,
    items,
    empty,
}: {
    icon: LucideIcon;
    title: string;
    href: string;
    items: ActivityItem[];
    empty: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Icon className="size-3.5" />
                    {title}
                </span>
                <Link
                    href={href}
                    className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                >
                    View all
                </Link>
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                items.map((item) => (
                    <Row key={`${title}-${item.key}`} item={item} />
                ))
            )}
        </div>
    );
}

function Row({ item }: { item: ActivityItem }) {
    const body: ReactNode = (
        <>
            <span className="flex min-w-0 items-center gap-2">
                <span className="truncate font-medium">{item.primary}</span>
                {item.draft && (
                    <Badge variant="outline" className="shrink-0">
                        Draft
                    </Badge>
                )}
                <span className="truncate text-xs text-muted-foreground">
                    {item.secondary}
                </span>
            </span>
            <MoneyDisplay amount={item.total} className="shrink-0" />
        </>
    );

    return item.href ? (
        <Link
            href={item.href}
            className="flex items-center justify-between gap-4 rounded-md px-1 py-0.5 text-sm hover:bg-accent"
        >
            {body}
        </Link>
    ) : (
        <span className="flex items-center justify-between gap-4 px-1 py-0.5 text-sm">
            {body}
        </span>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
