import { Head, router } from '@inertiajs/react';
import { Plus, ShoppingCart } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { StatusBadge } from '@/components/document-status';
import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { PurchaseDrawer } from '@/components/purchases/purchase-drawer';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/purchases';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type {
    AllocationMethodOption,
    ProductOption,
    PurchaseListRow,
    PurchaseStatusOption,
} from '@/types/purchasing';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Purchases' }];

const ANY = 'any';

/**
 * The list is the whole purchases screen. Writing an invoice rises from the
 * bottom of it, as adding a product does on the catalogue, and a row opens the
 * invoice itself.
 */
export default function PurchasesIndex({
    rows,
    table,
    nextNumber,
    products,
    allocationMethods,
    statuses,
}: {
    rows: Paginated<PurchaseListRow>;
    table: TableState;
    nextNumber: string;
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    statuses: PurchaseStatusOption[];
}) {
    const [creating, setCreating] = useState(false);

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
            header: 'Invoice',
            sortable: true,
            cell: (row) => (
                <span className="font-mono font-medium">{row.number}</span>
            ),
        },
        {
            key: 'invoiced_on',
            header: 'Date',
            sortable: true,
            cell: (row) => (
                <span className="text-muted-foreground">{row.invoiced_on}</span>
            ),
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
            cell: (row) => (
                <div className="flex justify-end">
                    <StatusBadge status={row.status} statuses={statuses} />
                </div>
            ),
        },
    ];

    const newButton = (
        <Button onClick={() => setCreating(true)}>
            <Plus data-icon="inline-start" />
            New purchase
        </Button>
    );

    return (
        <>
            <Head title="Purchases" />

            <PageHeader
                title="Purchases"
                description="What you ordered, what is on its way, and what has arrived."
                actions={newButton}
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search number or notes"
                onRowClick={(row) => router.visit(show.url(row.id))}
                toolbar={
                    <OptionSelect
                        className="w-36"
                        aria-label="Filter by status"
                        value={table.filters.status ?? ANY}
                        options={[
                            { value: ANY, label: 'Any status' },
                            ...statuses,
                        ]}
                        onChange={(value) => applyFilter('status', value)}
                        placeholder="Status"
                    />
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
                        action={newButton}
                    />
                }
            />

            <PurchaseDrawer
                open={creating}
                onOpenChange={setCreating}
                products={products}
                allocationMethods={allocationMethods}
                statuses={statuses}
                nextNumber={nextNumber}
            />
        </>
    );
}

PurchasesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
