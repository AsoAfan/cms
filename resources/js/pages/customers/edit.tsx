import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ReceiptText, Trash2 } from 'lucide-react';

import { CustomerForm } from '@/components/customers/customer-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { destroy, index, show, update } from '@/routes/customers';
import type { BreadcrumbItem } from '@/types';
import type { CustomerFormData } from '@/types/customers';

export default function CustomersEdit({
    customer,
}: {
    customer: CustomerFormData;
}) {
    // Reading the name off the page element inside `Page.layout` looks
    // equivalent but silently breaks SSR; layout props are the supported way.
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Customers', href: index.url() },
            { title: customer.name, href: show(customer.id!).url },
            { title: 'Edit' },
        ],
    });

    return (
        <>
            <Head title={customer.name} />

            <CustomerForm
                customer={customer}
                action={{ url: update(customer.id!).url, method: 'put' }}
                title={customer.name}
                submitLabel="Save"
                headerActions={
                    <>
                        <Button
                            variant="outline"
                            render={<Link href={show(customer.id!)} />}
                        >
                            <ReceiptText data-icon="inline-start" />
                            Account
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                router.delete(destroy(customer.id!).url)
                            }
                        >
                            <Trash2 data-icon="inline-start" />
                            Delete
                        </Button>
                    </>
                }
            />
        </>
    );
}

CustomersEdit.layout = [AppLayout];
