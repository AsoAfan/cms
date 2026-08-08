import { Head, Link, router } from '@inertiajs/react';
import { Plus, Receipt } from 'lucide-react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { create, edit, show } from '@/routes/sales';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { PaymentMethodOption, SaleListRow } from '@/types/sales';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sales' }];

const ANY = 'any';

export default function SalesIndex({
    rows,
    table,
    paymentMethods,
}: {
    rows: Paginated<SaleListRow>;
    table: TableState;
    paymentMethods: PaymentMethodOption[];
}) {
    function applyFilter(key: string, value: string) {
        const url = new URL(window.location.href);

        if (value === ANY) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }

        url.searchParams.delete('page');

        router.get(
            `${url.pathname}${url.search}`,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    const columns: Column<SaleListRow>[] = [
        {
            key: 'number',
            header: 'Number',
            sortable: true,
            cell: (row) => (
                <Link
                    href={row.status === 'posted' ? show(row.id) : edit(row.id)}
                    className="font-mono font-medium hover:underline"
                >
                    {row.number}
                </Link>
            ),
        },
        {
            key: 'sold_on',
            header: 'Date',
            sortable: true,
            cell: (row) => row.sold_on,
        },
        {
            key: 'payment_method',
            header: 'Payment',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.payment_method}
                </span>
            ),
        },
        {
            key: 'lines_count',
            header: 'Items',
            align: 'right',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.lines_count}</span>
            ),
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            cell: (row) => <MoneyDisplay amount={row.total} />,
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            align: 'right',
            cell: (row) =>
                row.status === 'posted' ? (
                    <Badge variant="secondary">Posted</Badge>
                ) : (
                    <Badge variant="outline">Draft</Badge>
                ),
        },
    ];

    return (
        <>
            <Head title="Sales" />

            <PageHeader
                title="Sales"
                description="What you sold, and what it made."
                actions={
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        New sale
                    </Button>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search number or notes"
                toolbar={
                    <>
                        <Select
                            value={table.filters.status ?? ANY}
                            onValueChange={(value) =>
                                applyFilter('status', String(value))
                            }
                        >
                            <SelectTrigger
                                className="w-32"
                                aria-label="Filter by status"
                            >
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>Any status</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="posted">Posted</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select
                            value={table.filters.payment_method ?? ANY}
                            onValueChange={(value) =>
                                applyFilter('payment_method', String(value))
                            }
                        >
                            <SelectTrigger
                                className="w-40"
                                aria-label="Filter by payment"
                            >
                                <SelectValue placeholder="Payment" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>Any payment</SelectItem>
                                {paymentMethods.map((method) => (
                                    <SelectItem
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </>
                }
                empty={
                    <EmptyState
                        icon={Receipt}
                        title={table.search ? 'No matches' : 'No sales yet'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Ring up your first sale.'
                        }
                        action={
                            <Button render={<Link href={create()} />}>
                                <Plus data-icon="inline-start" />
                                New sale
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

SalesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
