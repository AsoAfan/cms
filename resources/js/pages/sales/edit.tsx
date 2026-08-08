import { Head, router, setLayoutProps } from '@inertiajs/react';
import { CheckCircle2, Trash2 } from 'lucide-react';

import { SaleForm } from '@/components/sales/sale-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, post, update } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type {
    PaymentMethodOption,
    SaleFormData,
    SellableProduct,
} from '@/types/sales';

export default function SalesEdit({
    products,
    paymentMethods,
    sale,
}: {
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    sale: SaleFormData;
}) {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Sales', href: index.url() },
            { title: sale.number ?? 'Draft' },
        ],
    });

    return (
        <>
            <Head title={sale.number ?? 'Draft sale'} />

            <SaleForm
                products={products}
                paymentMethods={paymentMethods}
                sale={sale}
                action={{ url: update(sale.id!).url, method: 'put' }}
                title={sale.number ?? 'Draft sale'}
                submitLabel="Save draft"
                headerActions={
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => router.delete(destroy(sale.id!).url)}
                        >
                            <Trash2 data-icon="inline-start" />
                            Delete
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.post(post(sale.id!).url)}
                        >
                            <CheckCircle2 data-icon="inline-start" />
                            Post
                        </Button>
                    </>
                }
            />
        </>
    );
}

SalesEdit.layout = [AppLayout];
