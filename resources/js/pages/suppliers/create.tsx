import { Head } from '@inertiajs/react';

import { SupplierForm } from '@/components/suppliers/supplier-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/suppliers';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suppliers', href: index.url() },
    { title: 'New' },
];

export default function SuppliersCreate() {
    return (
        <>
            <Head title="New supplier" />
            <SupplierForm
                action={{ url: store.url(), method: 'post' }}
                title="New supplier"
                submitLabel="Create"
            />
        </>
    );
}

SuppliersCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
