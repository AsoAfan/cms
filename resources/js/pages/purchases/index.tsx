import { Head, Link, router } from '@inertiajs/react';
import { Plus, ShoppingCart } from 'lucide-react';

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
import { create, edit, show } from '@/routes/purchases';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { PurchaseListRow, SupplierOption } from '@/types/purchasing';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Purchases' }];

const ANY = 'any';

export default function PurchasesIndex({
    rows,
    table,
    suppliers,
}: {
    rows: Paginated<PurchaseListRow>;
    table: TableState;
    suppliers: SupplierOption[];
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

    const columns: Column<PurchaseListRow>[] = [
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
            key: 'supplier',
            header: 'Supplier',
            cell: (row) =>
                row.supplier ?? (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'invoiced_on',
            header: 'Date',
            sortable: true,
            hideOnMobile: true,
            cell: (row) => row.invoiced_on,
        },
        {
            key: 'lines_count',
            header: 'Lines',
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
            <Head title="Purchases" />

            <PageHeader
                title="Purchases"
                description="What you bought, and what it cost once freight is counted."
                actions={
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        New purchase
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
                            value={table.filters.supplier_id ?? ANY}
                            onValueChange={(value) =>
                                applyFilter('supplier_id', String(value))
                            }
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label="Filter by supplier"
                            >
                                <SelectValue placeholder="Supplier" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    All suppliers
                                </SelectItem>
                                <SelectItem value="none">
                                    No supplier
                                </SelectItem>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.id}
                                        value={String(supplier.id)}
                                    >
                                        {supplier.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </>
                }
                empty={
                    <EmptyState
                        icon={ShoppingCart}
                        title={table.search ? 'No matches' : 'No purchases yet'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Record what you bought to bring stock in.'
                        }
                        action={
                            <Button render={<Link href={create()} />}>
                                <Plus data-icon="inline-start" />
                                New purchase
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

PurchasesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
