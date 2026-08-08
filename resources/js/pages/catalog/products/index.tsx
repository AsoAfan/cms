import { Head } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { useState } from 'react';

import { ProductCreateDrawer } from '@/components/catalog/product-create-drawer';
import { ProductDrawer } from '@/components/catalog/product-drawer';
import { QuickPurchaseDialog } from '@/components/catalog/quick-purchase-dialog';
import { QuickSaleDialog } from '@/components/catalog/quick-sale-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { ProductListRow, SupplierOption } from '@/types/catalog';
import type { PaymentMethodOption } from '@/types/sales';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Products' }];

/**
 * The catalogue is the whole product UI. Editing opens beside the list,
 * adding rises from the bottom of it, and the two things done all day —
 * buying and selling one thing — are a button on the row itself.
 */
export default function ProductsIndex({
    rows,
    table,
    suppliers,
    paymentMethods,
}: {
    rows: Paginated<ProductListRow>;
    table: TableState;
    suppliers: SupplierOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<ProductListRow | null>(null);
    const [buying, setBuying] = useState<ProductListRow | null>(null);
    const [selling, setSelling] = useState<ProductListRow | null>(null);

    const columns: Column<ProductListRow>[] = [
        {
            key: 'name',
            header: 'Product',
            sortable: true,
            cell: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.name}</span>
                    {row.description && (
                        <span className="line-clamp-1 text-xs text-muted-foreground">
                            {row.description}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'quantity',
            header: 'In stock',
            sortable: true,
            align: 'right',
            cell: (row) => (
                <span
                    className={
                        row.quantity === 0
                            ? 'text-muted-foreground tabular-nums'
                            : 'font-medium tabular-nums'
                    }
                >
                    {row.quantity}
                </span>
            ),
        },
        {
            key: 'cost_price',
            header: 'Cost',
            sortable: true,
            align: 'right',
            hideOnMobile: true,
            cell: (row) => <MoneyDisplay amount={row.cost_price} />,
        },
        {
            key: 'selling_price',
            header: 'Price',
            sortable: true,
            align: 'right',
            hideOnMobile: true,
            cell: (row) => <MoneyDisplay amount={row.selling_price} />,
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            // The row opens the drawer, so the buttons have to keep their own
            // clicks to themselves.
            cell: (row) => (
                <div
                    className="flex justify-end"
                    onClick={(event) => event.stopPropagation()}
                >
                    <ButtonGroup>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setBuying(row)}
                        >
                            Buy
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={row.quantity === 0}
                            title={
                                row.quantity === 0
                                    ? 'Nothing in stock'
                                    : undefined
                            }
                            onClick={() => setSelling(row)}
                        >
                            Sell
                        </Button>
                    </ButtonGroup>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Products" />

            <PageHeader
                title="Products"
                description="What you buy and sell, and how much of it is on the shelf."
                actions={
                    <Button onClick={() => setCreating(true)}>
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
                onRowClick={(row) => setEditing(row)}
                empty={
                    <EmptyState
                        icon={Package}
                        title={table.search ? 'No matches' : 'No products yet'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Add your first product to get started.'
                        }
                        action={
                            <Button onClick={() => setCreating(true)}>
                                <Plus data-icon="inline-start" />
                                New product
                            </Button>
                        }
                    />
                }
            />

            <ProductCreateDrawer open={creating} onOpenChange={setCreating} />

            <ProductDrawer
                product={editing}
                onOpenChange={(open) => !open && setEditing(null)}
            />

            <QuickPurchaseDialog
                product={buying}
                suppliers={suppliers}
                onOpenChange={(open) => !open && setBuying(null)}
            />

            <QuickSaleDialog
                product={selling}
                paymentMethods={paymentMethods}
                onOpenChange={(open) => !open && setSelling(null)}
            />
        </>
    );
}

ProductsIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
