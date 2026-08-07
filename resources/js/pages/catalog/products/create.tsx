import { Head } from '@inertiajs/react';

import { ProductForm } from '@/components/catalog/product-form';
import AppLayout from '@/layouts/app-layout';
import { index, store } from '@/routes/products';
import type { BreadcrumbItem } from '@/types';
import type { Attribute } from '@/types/catalog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: index.url() },
    { title: 'New product' },
];

export default function ProductsCreate({
    attributes,
}: {
    attributes: Attribute[];
}) {
    return (
        <>
            <Head title="New product" />
            <ProductForm
                attributes={attributes}
                action={{ url: store.url(), method: 'post' }}
                title="New product"
                description="Add a product and the items it is stocked and sold as."
                submitLabel="Create product"
            />
        </>
    );
}

ProductsCreate.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
