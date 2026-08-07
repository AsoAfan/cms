import { Head, Link, router } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
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
import { create, edit } from '@/routes/suppliers';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { SupplierListRow } from '@/types/suppliers';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Suppliers' }];

const ANY = 'any';

export default function SuppliersIndex({
    rows,
    table,
}: {
    rows: Paginated<SupplierListRow>;
    table: TableState;
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

    const columns: Column<SupplierListRow>[] = [
        {
            key: 'name',
            header: 'Supplier',
            sortable: true,
            cell: (row) => (
                <Link
                    href={edit(row.id)}
                    className="font-medium hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'phone',
            header: 'Phone',
            cell: (row) =>
                row.phone ?? <span className="text-muted-foreground">—</span>,
        },
        {
            key: 'email',
            header: 'Email',
            hideOnMobile: true,
            cell: (row) =>
                row.email ? (
                    <span className="text-muted-foreground">{row.email}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'is_active',
            header: 'Status',
            align: 'right',
            cell: (row) =>
                row.is_active ? (
                    <Badge variant="secondary">Active</Badge>
                ) : (
                    <Badge variant="outline">Archived</Badge>
                ),
        },
    ];

    return (
        <>
            <Head title="Suppliers" />

            <PageHeader
                title="Suppliers"
                description="Who you buy from."
                actions={
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        New supplier
                    </Button>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search suppliers"
                toolbar={
                    <Select
                        value={table.filters.status ?? ANY}
                        onValueChange={(value) =>
                            applyFilter('status', String(value))
                        }
                    >
                        <SelectTrigger
                            className="w-36"
                            aria-label="Filter by status"
                        >
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Archived</SelectItem>
                        </SelectContent>
                    </Select>
                }
                empty={
                    <EmptyState
                        icon={Truck}
                        title={table.search ? 'No matches' : 'No suppliers yet'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Add the first supplier you buy from.'
                        }
                        action={
                            <Button render={<Link href={create()} />}>
                                <Plus data-icon="inline-start" />
                                New supplier
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

SuppliersIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
