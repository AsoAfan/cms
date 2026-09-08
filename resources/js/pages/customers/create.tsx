import { Head } from '@inertiajs/react';

import { CustomerForm } from '@/components/customers/customer-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/customers';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: index.url() },
    { title: 'New' },
];

export default function CustomersCreate() {
    return (
        <>
            <Head title="New customer" />
            <CustomerForm
                action={{ url: store.url(), method: 'post' }}
                title="New customer"
                submitLabel="Create"
            />
        </>
    );
}

CustomersCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
