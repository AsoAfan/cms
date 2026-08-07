import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';

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
import { create, edit } from '@/routes/products';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { ProductListRow } from '@/types/catalog';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Products' }];

const ANY = 'any';

type ProductsIndexProps = {
    rows: Paginated<ProductListRow>;
    table: TableState;
};

export default function ProductsIndex({ rows, table }: ProductsIndexProps) {
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

    const columns: Column<ProductListRow>[] = [
        {
            key: 'name',
            header: 'Product',
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
            key: 'variants_count',
            header: 'Items',
            sortable: true,
            align: 'right',
            cell: (row) => (
                <Badge variant="secondary">{row.variants_count}</Badge>
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
            <Head title="Products" />

            <PageHeader
                title="Products"
                description="Everything you buy and sell. Each product is stocked as one or more items."
                actions={
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        New product
                    </Button>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search products"
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
                        icon={Package}
                        title={
                            table.search ? 'Nothing matched' : 'No products yet'
                        }
                        description={
                            table.search
                                ? `No product matches "${table.search}".`
                                : 'Add your first product to start purchasing and selling.'
                        }
                        action={
                            <Button render={<Link href={create()} />}>
                                <Plus data-icon="inline-start" />
                                New product
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

ProductsIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
