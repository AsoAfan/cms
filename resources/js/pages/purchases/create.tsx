import { Head } from '@inertiajs/react';

import { PurchaseForm } from '@/components/purchases/purchase-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/purchases';
import type { BreadcrumbItem } from '@/types';
import type {
    AllocationMethodOption,
    ProductOption,
    SupplierOption,
} from '@/types/purchasing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchases', href: index.url() },
    { title: 'New' },
];

export default function PurchasesCreate({
    suppliers,
    products,
    allocationMethods,
}: {
    suppliers: SupplierOption[];
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
}) {
    return (
        <>
            <Head title="New purchase" />
            <PurchaseForm
                suppliers={suppliers}
                products={products}
                allocationMethods={allocationMethods}
                action={{ url: store.url(), method: 'post' }}
                title="New purchase"
                submitLabel="Save draft"
            />
        </>
    );
}

PurchasesCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
