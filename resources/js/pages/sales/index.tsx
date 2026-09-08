import { Head, Link, router } from '@inertiajs/react';
import { Plus, Receipt } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { StatusBadge } from '@/components/document-status';
import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { SaleDrawer } from '@/components/sales/sale-drawer';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { show as showCustomer } from '@/routes/customers';
import { show } from '@/routes/sales';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { BankOption } from '@/types/banks';
import type { SaleCustomer } from '@/types/customers';
import type {
    PaymentMethodOption,
    SaleListRow,
    SaleStatusOption,
    SellableProduct,
} from '@/types/sales';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sales' }];

const ANY = 'any';

/**
 * The list is the whole sales screen. Ringing one up rises from the bottom of
 * it, as adding a product does on the catalogue, and a row opens the invoice.
 */
export default function SalesIndex({
    rows,
    table,
    nextNumber,
    products,
    paymentMethods,
    banks,
    statuses,
    customers,
}: {
    rows: Paginated<SaleListRow>;
    table: TableState;
    nextNumber: string;
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    statuses: SaleStatusOption[];
    customers: SaleCustomer[];
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

    const columns: Column<SaleListRow>[] = [
        {
            key: 'number',
            header: 'Invoice',
            sortable: true,
            cell: (row) => (
                <span className="font-mono font-medium">{row.number}</span>
            ),
        },
        {
            key: 'customer',
            header: 'Customer',
            // The row opens the sale, so the one link that goes somewhere else
            // has to keep its click to itself.
            cell: (row) => (
                <Link
                    href={showCustomer(row.customer_id)}
                    className="hover:underline"
                    onClick={(event) => event.stopPropagation()}
                >
                    {row.customer}
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
                    {row.bank && ` · ${row.bank}`}
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
            // Zero until the goods are the customer's, so an order on its way
            // reads as owing nothing — because nothing is owed on it yet.
            key: 'outstanding',
            header: 'Owed',
            align: 'right',
            hideOnMobile: true,
            cell: (row) =>
                row.outstanding === 0 ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <MoneyDisplay
                        amount={row.outstanding}
                        className="font-medium"
                    />
                ),
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
            New sale
        </Button>
    );

    return (
        <>
            <Head title="Sales" />

            <PageHeader
                title="Sales"
                description="What you sold, and what it made."
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
                    <>
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

                        <OptionSelect
                            className="w-40"
                            aria-label="Filter by payment"
                            value={table.filters.payment_method ?? ANY}
                            options={[
                                { value: ANY, label: 'Any payment' },
                                ...paymentMethods,
                            ]}
                            onChange={(value) =>
                                applyFilter('payment_method', value)
                            }
                            placeholder="Payment"
                        />

                        {banks.length > 0 && (
                            <OptionSelect
                                className="w-40"
                                aria-label="Filter by bank"
                                value={table.filters.bank_id ?? ANY}
                                options={[
                                    { value: ANY, label: 'Any bank' },
                                    ...banks.map((bank) => ({
                                        value: String(bank.id),
                                        label: bank.name,
                                    })),
                                ]}
                                onChange={(value) =>
                                    applyFilter('bank_id', value)
                                }
                                placeholder="Bank"
                            />
                        )}
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
                        action={newButton}
                    />
                }
            />

            <SaleDrawer
                open={creating}
                onOpenChange={setCreating}
                products={products}
                paymentMethods={paymentMethods}
                banks={banks}
                statuses={statuses}
                customers={customers}
                nextNumber={nextNumber}
            />
        </>
    );
}

SalesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
