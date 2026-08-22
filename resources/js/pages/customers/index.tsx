import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFormatMoney } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import { create, show } from '@/routes/customers';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { CustomerListRow } from '@/types/customers';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Customers' }];

const ANY = 'any';

export default function CustomersIndex({
    rows,
    table,
    owedTotal,
}: {
    rows: Paginated<CustomerListRow>;
    table: TableState;
    owedTotal: number;
}) {
    const format = useFormatMoney();

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

    const columns: Column<CustomerListRow>[] = [
        {
            key: 'name',
            header: 'Customer',
            sortable: true,
            cell: (row) => (
                <Link
                    href={show(row.id)}
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
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.email ?? '—'}
                </span>
            ),
        },
        {
            // Derived from their delivered sales and payments, so it is neither
            // sortable nor a column on the table.
            key: 'balance',
            header: 'Owes',
            align: 'right',
            cell: (row) =>
                row.balance === 0 ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <MoneyDisplay
                        amount={row.balance}
                        className="font-medium"
                    />
                ),
        },
        {
            key: 'is_active',
            header: 'Status',
            align: 'right',
            hideOnMobile: true,
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
            <Head title="Customers" />

            <PageHeader
                title="Customers"
                description={
                    owedTotal === 0
                        ? 'Who you sell to. Nobody owes anything.'
                        : `Who you sell to. ${format(owedTotal)} is out on loan.`
                }
                actions={
                    <Button render={<Link href={create()} />}>
                        <Plus data-icon="inline-start" />
                        New customer
                    </Button>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search customers"
                toolbar={
                    <>
                        <OptionSelect
                            className="w-36"
                            aria-label="Filter by balance"
                            value={table.filters.balance ?? ANY}
                            options={[
                                { value: ANY, label: 'Anyone' },
                                { value: 'owing', label: 'Owes money' },
                                { value: 'settled', label: 'Owes nothing' },
                            ]}
                            onChange={(value) => applyFilter('balance', value)}
                            placeholder="Balance"
                        />

                        <OptionSelect
                            className="w-36"
                            aria-label="Filter by status"
                            value={table.filters.status ?? ANY}
                            options={[
                                { value: ANY, label: 'Any status' },
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Archived' },
                            ]}
                            onChange={(value) => applyFilter('status', value)}
                            placeholder="Status"
                        />
                    </>
                }
                empty={
                    <EmptyState
                        icon={Users}
                        title={table.search ? 'No matches' : 'No customers yet'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Add the first customer you sell to.'
                        }
                        action={
                            <Button render={<Link href={create()} />}>
                                <Plus data-icon="inline-start" />
                                New customer
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

CustomersIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
