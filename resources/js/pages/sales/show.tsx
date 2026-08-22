import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { StatusStepper } from '@/components/document-status';
import {
    MoneyDisplay,
    MoneyReview,
    MoneyReviewGroup,
    MoneyReviewSwitch,
} from '@/components/money-display';
import { SaleDrawer } from '@/components/sales/sale-drawer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { show as showCustomer } from '@/routes/customers';
import { destroy, index, status as statusRoute } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type { BankOption } from '@/types/banks';
import type { SaleCustomer } from '@/types/customers';
import type {
    PaymentMethodOption,
    SaleDetail,
    SaleStatusOption,
    SellableProduct,
} from '@/types/sales';

/**
 * The invoice, as a piece of paper would show it: who bought it, what is on
 * it, what it comes to.
 *
 * What the goods cost and what was made on them are not on the lines — that is
 * the ledger's side of the transaction, not the customer's — but they are in
 * the summary, where the shop's own figures belong.
 */
export default function SalesShow({
    sale,
    products,
    paymentMethods,
    banks,
    statuses,
    customers,
}: {
    sale: SaleDetail;
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    statuses: SaleStatusOption[];
    customers: SaleCustomer[];
}) {
    const [editing, setEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [moving, setMoving] = useState(false);

    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Sales', href: index.url() },
            { title: sale.number },
        ],
    });

    // Cost and profit exist once the goods have left, which is what
    // `committed_at` records — a sale still sitting at ordered has taken
    // nothing off the shelf, so it has no cost yet.
    const gone = sale.committed_at !== null;

    function moveTo(next: string) {
        setMoving(true);

        router.post(
            statusRoute.url(sale.id),
            { status: next },
            { preserveScroll: true, onFinish: () => setMoving(false) },
        );
    }

    return (
        <>
            <Head title={sale.number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <StatusStepper
                    status={sale.status}
                    statuses={statuses}
                    busy={moving}
                    onChange={moveTo}
                />

                <div className="flex items-center gap-2">
                    <Button variant="ghost" render={<Link href={index()} />}>
                        <ArrowLeft data-icon="inline-start" />
                        Sales
                    </Button>
                    <Button variant="outline" onClick={() => setEditing(true)}>
                        <Pencil data-icon="inline-start" />
                        Edit
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Delete ${sale.number}`}
                        onClick={() => setConfirmingDelete(true)}
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>

            {confirmingDelete && (
                <Card className="border-destructive/40 bg-destructive/5">
                    <CardContent className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm">
                            Delete {sale.number}?
                            {gone &&
                                ' The stock it took out goes back on the shelf.'}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setConfirmingDelete(false)}
                            >
                                Keep
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    router.delete(destroy.url(sale.id))
                                }
                            >
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent className="flex flex-col gap-6 py-2">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="font-mono text-2xl font-semibold tracking-tight">
                                {sale.number}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Sold {sale.sold_on} to{' '}
                                <Link
                                    href={showCustomer(sale.customer_id)}
                                    className="underline underline-offset-2 hover:no-underline"
                                >
                                    {sale.customer}
                                </Link>
                            </p>
                        </div>

                        <dl className="text-right text-sm">
                            <div className="flex justify-end gap-2">
                                <dt className="text-muted-foreground">Items</dt>
                                <dd className="tabular-nums">
                                    {sale.total_quantity}
                                </dd>
                            </div>
                            <div className="flex justify-end gap-2">
                                <dt className="text-muted-foreground">
                                    Paid by
                                </dt>
                                <dd>
                                    {sale.payment_method_label}
                                    {sale.bank && ` · ${sale.bank}`}
                                </dd>
                            </div>
                            {sale.currency !== sale.base_currency && (
                                <div className="flex justify-end gap-2">
                                    <dt className="text-muted-foreground">
                                        Taken in
                                    </dt>
                                    <dd>
                                        {sale.currency} @ {sale.exchange_rate}
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-end gap-2">
                                <dt className="text-muted-foreground">Stock</dt>
                                <dd>
                                    {gone
                                        ? `Left ${sale.committed_at}`
                                        : 'Still on the shelf'}
                                </dd>
                            </div>
                        </dl>
                    </header>

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Discount
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sale.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell className="font-medium">
                                            {line.product}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.quantity}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <MoneyDisplay
                                                amount={line.unit_price}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {line.discount === 0 ? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            ) : (
                                                <MoneyDisplay
                                                    amount={line.discount}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            <MoneyDisplay
                                                amount={line.net_total}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* One dropdown for the whole summary: these figures are one
                        statement about one sale, and reading the total in
                        dollars against a profit in dinars says nothing. */}
                    <MoneyReviewGroup>
                        <div className="flex justify-end">
                            <dl className="flex w-full max-w-xs flex-col gap-2 text-sm">
                                <div className="flex items-center justify-between text-base font-semibold">
                                    <dt>Total</dt>
                                    <dd>
                                        <MoneyReview amount={sale.total} />
                                    </dd>
                                </div>

                                <div className="flex items-center justify-between">
                                    <dt className="text-muted-foreground">
                                        Cost of goods
                                    </dt>
                                    <dd>
                                        {gone ? (
                                            <MoneyReview
                                                amount={sale.cost_of_goods_sold}
                                            />
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </dd>
                                </div>

                                <div className="flex items-center justify-between font-medium">
                                    <dt>Gross profit</dt>
                                    <dd>
                                        {gone ? (
                                            <MoneyReview
                                                amount={sale.gross_profit}
                                                colored
                                            />
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </dd>
                                </div>

                                <Separator />

                                <div className="flex items-center justify-between">
                                    <dt className="text-muted-foreground">
                                        Paid
                                    </dt>
                                    <dd>
                                        <MoneyReview
                                            amount={sale.paid_to_date}
                                        />
                                    </dd>
                                </div>

                                <div className="flex items-center justify-between font-medium">
                                    <dt>Owed</dt>
                                    <dd>
                                        {sale.outstanding === 0 ? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ) : (
                                            <MoneyReview
                                                amount={sale.outstanding}
                                            />
                                        )}
                                    </dd>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    {/* Nothing is owed before the goods are the
                                        customer's, so say why rather than
                                        showing a bare zero. */}
                                    <p className="text-xs text-muted-foreground">
                                        {!sale.delivered &&
                                            sale.paid_to_date < sale.total &&
                                            'Owed once the customer has it.'}
                                        {sale.delivered &&
                                            sale.outstanding > 0 && (
                                                <Link
                                                    href={showCustomer(
                                                        sale.customer_id,
                                                    )}
                                                    className="underline underline-offset-2 hover:no-underline"
                                                >
                                                    Record a payment on{' '}
                                                    {sale.customer}'s account
                                                </Link>
                                            )}
                                    </p>
                                    <MoneyReviewSwitch label="Read in" />
                                </div>
                            </dl>
                        </div>
                    </MoneyReviewGroup>

                    {sale.notes && (
                        <p className="border-t pt-4 text-sm whitespace-pre-line text-muted-foreground">
                            {sale.notes}
                        </p>
                    )}
                </CardContent>
            </Card>

            <SaleDrawer
                open={editing}
                onOpenChange={setEditing}
                products={products}
                paymentMethods={paymentMethods}
                banks={banks}
                statuses={statuses}
                customers={customers}
                sale={sale}
            />
        </>
    );
}

SalesShow.layout = [AppLayout];
