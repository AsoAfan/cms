import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { SupplierForm } from '@/components/suppliers/supplier-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, update } from '@/routes/suppliers';
import type { BreadcrumbItem } from '@/types';
import type { SupplierFormData } from '@/types/suppliers';

export default function SuppliersEdit({
    supplier,
}: {
    supplier: SupplierFormData;
}) {
    // Reading the name off the page element inside `Page.layout` looks
    // equivalent but silently breaks SSR; layout props are the supported way.
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Suppliers', href: index.url() },
            { title: supplier.name },
        ],
    });

    return (
        <>
            <Head title={supplier.name} />

            <SupplierForm
                supplier={supplier}
                action={{ url: update(supplier.id!).url, method: 'put' }}
                title={supplier.name}
                submitLabel="Save"
                headerActions={
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => router.delete(destroy(supplier.id!).url)}
                    >
                        <Trash2 data-icon="inline-start" />
                        Delete
                    </Button>
                }
            />
        </>
    );
}

SuppliersEdit.layout = [AppLayout];
