import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { StatusStepper } from '@/components/document-status';
import { MoneyDisplay, MoneyReview } from '@/components/money-display';
import { PurchaseDrawer } from '@/components/purchases/purchase-drawer';
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
import { destroy, index, status as statusRoute } from '@/routes/purchases';
import type { BreadcrumbItem } from '@/types';
import type {
    AllocationMethodOption,
    ProductOption,
    PurchaseDetail,
    PurchaseStatusOption,
} from '@/types/purchasing';

/**
 * The invoice, as a piece of paper would show it: who it is from, what is on
 * it, what it comes to.
 *
 * Everything about how the goods were costed into stock — landed cost per
 * line, which batches they became — was on this page and is not any more. It
 * is a document, and the FIFO ledger behind it is not what somebody opens an
 * invoice to read.
 */
export default function PurchasesShow({
    purchase,
    products,
    allocationMethods,
    statuses,
}: {
    purchase: PurchaseDetail;
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    statuses: PurchaseStatusOption[];
}) {
    const [editing, setEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [moving, setMoving] = useState(false);

    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Purchases', href: index.url() },
            { title: purchase.number },
        ],
    });

    function moveTo(next: string) {
        setMoving(true);

        router.post(
            statusRoute.url(purchase.id),
            { status: next },
            {
                preserveScroll: true,
                onFinish: () => setMoving(false),
            },
        );
    }

    return (
        <>
            <Head title={purchase.number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <StatusStepper
                    status={purchase.status}
                    statuses={statuses}
                    busy={moving}
                    onChange={moveTo}
                />

                <div className="flex items-center gap-2">
                    <Button variant="ghost" render={<Link href={index()} />}>
                        <ArrowLeft data-icon="inline-start" />
                        Purchases
                    </Button>
                    <Button variant="outline" onClick={() => setEditing(true)}>
                        <Pencil data-icon="inline-start" />
                        Edit
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Delete ${purchase.number}`}
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
                            Delete {purchase.number}?
                            {purchase.committed_at !== null &&
                                ' The stock it brought in comes back off the shelf.'}
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
                                    router.delete(destroy.url(purchase.id))
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
                                {purchase.number}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Invoiced {purchase.invoiced_on}
                            </p>
                        </div>

                        <dl className="text-right text-sm">
                            <div className="flex justify-end gap-2">
                                <dt className="text-muted-foreground">Items</dt>
                                <dd className="tabular-nums">
                                    {purchase.total_quantity}
                                </dd>
                            </div>
                            {purchase.currency !== purchase.base_currency && (
                                <div className="flex justify-end gap-2">
                                    <dt className="text-muted-foreground">
                                        Paid in
                                    </dt>
                                    <dd>
                                        {purchase.currency} @{' '}
                                        {purchase.exchange_rate}
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-end gap-2">
                                <dt className="text-muted-foreground">Stock</dt>
                                <dd>
                                    {purchase.committed_at === null
                                        ? 'Not in yet'
                                        : `Taken in ${purchase.committed_at}`}
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
                                        Unit cost
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
                                {purchase.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell className="font-medium">
                                            {line.product}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.quantity}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <MoneyDisplay
                                                amount={line.unit_cost}
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

                    <div className="flex justify-end">
                        <dl className="flex w-full max-w-xs flex-col gap-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Goods</dt>
                                <dd>
                                    <MoneyDisplay
                                        amount={purchase.goods_total}
                                    />
                                </dd>
                            </div>

                            {purchase.additional_costs.map((cost, index) => (
                                <div
                                    key={index}
                                    className="flex justify-between"
                                >
                                    <dt className="text-muted-foreground">
                                        {cost.label}
                                        <span className="ml-1 text-xs">
                                            ({cost.allocation_label})
                                        </span>
                                    </dt>
                                    <dd>
                                        <MoneyDisplay amount={cost.amount} />
                                    </dd>
                                </div>
                            ))}

                            <Separator />

                            <div className="flex justify-between text-base font-semibold">
                                <dt>Total</dt>
                                <dd>
                                    <MoneyReview amount={purchase.total} />
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {purchase.notes && (
                        <p className="border-t pt-4 text-sm whitespace-pre-line text-muted-foreground">
                            {purchase.notes}
                        </p>
                    )}
                </CardContent>
            </Card>

            <PurchaseDrawer
                open={editing}
                onOpenChange={setEditing}
                products={products}
                allocationMethods={allocationMethods}
                statuses={statuses}
                purchase={purchase}
            />
        </>
    );
}

PurchasesShow.layout = [AppLayout];
