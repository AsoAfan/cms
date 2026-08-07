import { Head } from '@inertiajs/react';

import { ProductForm } from '@/components/catalog/product-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/products';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: index.url() },
    { title: 'New' },
];

export default function ProductsCreate() {
    return (
        <>
            <Head title="New product" />
            <ProductForm
                action={{ url: store.url(), method: 'post' }}
                title="New product"
                submitLabel="Create"
            />
        </>
    );
}

ProductsCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
