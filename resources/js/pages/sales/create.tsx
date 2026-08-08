import { Head } from '@inertiajs/react';

import { SaleForm } from '@/components/sales/sale-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type { PaymentMethodOption, SellableProduct } from '@/types/sales';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: index.url() },
    { title: 'New' },
];

export default function SalesCreate({
    products,
    paymentMethods,
}: {
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
}) {
    return (
        <>
            <Head title="New sale" />
            <SaleForm
                products={products}
                paymentMethods={paymentMethods}
                action={{ url: store.url(), method: 'post' }}
                title="New sale"
                submitLabel="Save draft"
            />
        </>
    );
}

SalesCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
