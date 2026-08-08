import { Head, router, setLayoutProps } from '@inertiajs/react';
import { CheckCircle2, Trash2 } from 'lucide-react';

import { PurchaseForm } from '@/components/purchases/purchase-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, post, update } from '@/routes/purchases';
import type { BreadcrumbItem } from '@/types';
import type {
    AllocationMethodOption,
    ProductOption,
    PurchaseFormData,
    SupplierOption,
} from '@/types/purchasing';

export default function PurchasesEdit({
    suppliers,
    products,
    allocationMethods,
    purchase,
}: {
    suppliers: SupplierOption[];
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    purchase: PurchaseFormData;
}) {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Purchases', href: index.url() },
            { title: purchase.number ?? 'Draft' },
        ],
    });

    return (
        <>
            <Head title={purchase.number ?? 'Draft purchase'} />

            <PurchaseForm
                suppliers={suppliers}
                products={products}
                allocationMethods={allocationMethods}
                purchase={purchase}
                action={{ url: update(purchase.id!).url, method: 'put' }}
                title={purchase.number ?? 'Draft purchase'}
                submitLabel="Save draft"
                headerActions={
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                router.delete(destroy(purchase.id!).url)
                            }
                        >
                            <Trash2 data-icon="inline-start" />
                            Delete
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.post(post(purchase.id!).url)}
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

PurchasesEdit.layout = [AppLayout];
