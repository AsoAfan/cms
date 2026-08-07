import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ProductForm } from '@/components/catalog/product-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, update } from '@/routes/products';
import type { BreadcrumbItem } from '@/types';
import type { ProductFormData } from '@/types/catalog';

export default function ProductsEdit({
    product,
}: {
    product: ProductFormData;
}) {
    // The breadcrumb carries the product's name, which the layout cannot know
    // on its own. Reading it off the page element inside `Page.layout` looks
    // equivalent but silently breaks SSR; layout props are the supported way.
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Products', href: index.url() },
            { title: product.name },
        ],
    });

    return (
        <>
            <Head title={product.name} />

            <ProductForm
                product={product}
                action={{ url: update(product.id!).url, method: 'put' }}
                title={product.name}
                submitLabel="Save"
                headerActions={
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => router.delete(destroy(product.id!).url)}
                    >
                        <Trash2 data-icon="inline-start" />
                        Delete
                    </Button>
                }
            />
        </>
    );
}

ProductsEdit.layout = [AppLayout];
