import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { ProductForm } from '@/components/catalog/product-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, update } from '@/routes/products';
import type { BreadcrumbItem } from '@/types';
import type { Attribute, ProductFormData } from '@/types/catalog';

type EditProps = {
    attributes: Attribute[];
    product: ProductFormData;
};

export default function ProductsEdit({ attributes, product }: EditProps) {
    // The breadcrumb carries the product's name, which the layout cannot know
    // on its own. Reading it off the page element inside `Page.layout` looks
    // equivalent but silently breaks SSR; layout props are the supported way.
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Products', href: index.url() },
            { title: product.name },
        ],
    });

    function remove() {
        router.delete(destroy(product.id!).url);
    }

    return (
        <>
            <Head title={product.name} />

            <ProductForm
                attributes={attributes}
                product={product}
                action={{ url: update(product.id!).url, method: 'put' }}
                title={product.name}
                description="Edit this product and the items it is stocked and sold as."
                submitLabel="Save changes"
            />

            <div className="flex items-center justify-between border-t pt-6">
                <Button variant="outline" render={<Link href={index()} />}>
                    Back to products
                </Button>
                <Button variant="outline" onClick={remove}>
                    <Trash2 data-icon="inline-start" />
                    Delete product
                </Button>
            </div>
        </>
    );
}

ProductsEdit.layout = [AppLayout];
