import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { HandCoins, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { RecordPaymentDialog } from '@/components/customers/record-payment-dialog';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { edit, index } from '@/routes/customers';
import { destroy } from '@/routes/customers/payments';
import { show as showSale } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type { BankOption } from '@/types/banks';
import type {
    CustomerFormData,
    CustomerStatement,
    OpenSale,
} from '@/types/customers';
import type { PaymentMethodOption } from '@/types/sales';

export default function CustomersShow({
    customer,
    statement,
    openSales,
    paymentMethods,
    banks,
}: {
    customer: CustomerFormData;
    statement: CustomerStatement;
    openSales: OpenSale[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
}) {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Customers', href: index.url() },
            { title: customer.name },
        ],
    });

    const [paying, setPaying] = useState(false);

    return (
        <>
            <Head title={customer.name} />

            <PageHeader
                title={customer.name}
                description={
                    [customer.phone, customer.email]
                        .filter(Boolean)
                        .join(' · ') || 'No phone or email on record.'
                }
                actions={
                    <>
                        {!customer.is_active && (
                            <Badge variant="outline">Archived</Badge>
                        )}
                        <Button
                            variant="outline"
                            render={<Link href={edit(customer.id!)} />}
                        >
                            <Pencil data-icon="inline-start" />
                            Edit
                        </Button>
                        <Button
                            disabled={openSales.length === 0}
                            onClick={() => setPaying(true)}
                        >
                            <HandCoins data-icon="inline-start" />
                            Record payment
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <Card className="gap-0">
                    <CardContent className="flex flex-col gap-1">
                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Owes
                        </span>
                        <MoneyDisplay
                            amount={statement.balance}
                            className="text-2xl font-semibold"
                        />
                        <span className="text-xs text-muted-foreground">
                            {openSales.length === 0
                                ? 'Nothing outstanding'
                                : `Across ${openSales.length} ${openSales.length === 1 ? 'invoice' : 'invoices'}`}
                        </span>
                    </CardContent>
                </Card>

                <Card className="gap-0">
                    <CardContent className="flex flex-col gap-1">
                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Invoiced
                        </span>
                        <MoneyDisplay
                            amount={statement.invoiced}
                            className="text-2xl font-semibold"
                        />
                        <span className="text-xs text-muted-foreground">
                            Delivered sales only
                        </span>
                    </CardContent>
                </Card>

                <Card className="gap-0">
                    <CardContent className="flex flex-col gap-1">
                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Paid
                        </span>
                        <MoneyDisplay
                            amount={statement.paid}
                            className="text-2xl font-semibold"
                        />
                        <span className="text-xs text-muted-foreground">
                            At the till and since
                        </span>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Invoices</CardTitle>
                    <CardDescription>
                        Nothing is owed until the goods are the customer's, so
                        an order not yet delivered shows no balance.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {statement.sales.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing sold to {customer.name} yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Paid
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Owed
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {statement.sales.map((sale) => (
                                        <TableRow key={sale.id}>
                                            <TableCell>
                                                <Link
                                                    href={showSale(sale.id)}
                                                    className="font-mono font-medium hover:underline"
                                                >
                                                    {sale.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {sale.sold_on}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        sale.delivered
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {sale.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={sale.total}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={sale.paid}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {sale.outstanding === 0 ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    <MoneyDisplay
                                                        amount={
                                                            sale.outstanding
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Payments</CardTitle>
                    <CardDescription>
                        A payment is never edited. Deleting one puts back
                        everything it settled.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {statement.payments.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            No payments recorded.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {statement.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-medium">
                                            {payment.received_on} ·{' '}
                                            {payment.payment_method}
                                            {payment.bank &&
                                                ` · ${payment.bank}`}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {payment.allocations
                                                .map(
                                                    (allocation) =>
                                                        allocation.number,
                                                )
                                                .join(', ') ||
                                                'Nothing applied'}
                                            {payment.notes
                                                ? ` · ${payment.notes}`
                                                : ''}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <MoneyDisplay
                                            amount={payment.amount}
                                            className="font-medium"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Delete the payment of ${payment.received_on}`}
                                            onClick={() =>
                                                router.delete(
                                                    destroy({
                                                        customer: customer.id!,
                                                        payment: payment.id,
                                                    }).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {customer.notes && (
                <Card>
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm whitespace-pre-line">
                            {customer.notes}
                        </p>
                        {customer.address && (
                            <>
                                <Separator className="my-3" />
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {customer.address}
                                </p>
                            </>
                        )}
                    </CardContent>
                </Card>
            )}

            <RecordPaymentDialog
                customer={{ id: customer.id!, name: customer.name }}
                openSales={openSales}
                paymentMethods={paymentMethods}
                banks={banks}
                open={paying}
                onOpenChange={setPaying}
            />
        </>
    );
}

CustomersShow.layout = [AppLayout];
