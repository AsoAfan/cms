import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

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
import { index } from '@/routes/purchases';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseDetail } from '@/types/purchasing';

export default function PurchasesShow({
    purchase,
}: {
    purchase: PurchaseDetail;
}) {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Purchases', href: index.url() },
            { title: purchase.number },
        ],
    });

    return (
        <>
            <Head title={purchase.number} />

            <PageHeader
                title={purchase.number}
                description={`${purchase.supplier} · ${purchase.invoiced_on}`}
                actions={
                    <>
                        {purchase.status === 'posted' ? (
                            <Badge variant="secondary">Posted</Badge>
                        ) : (
                            <Badge variant="outline">Draft</Badge>
                        )}
                        <Button
                            variant="outline"
                            render={<Link href={index()} />}
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Purchases
                        </Button>
                    </>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Lines</CardTitle>
                    <CardDescription>
                        Landed cost is what the line came to once freight and
                        other costs were spread over it — that is what stock is
                        valued at.
                    </CardDescription>
                </CardHeader>
                <CardContent>
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
                                        Net
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Landed
                                    </TableHead>
                                    <TableHead>On the shelf at</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {purchase.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="flex flex-col">
                                                <span className="font-medium">
                                                    {line.product}
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {line.code}
                                                </span>
                                            </div>
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
                                            <MoneyDisplay
                                                amount={line.net_total}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            <MoneyDisplay
                                                amount={line.landed_total}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {line.batches.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {line.batches.map(
                                                        (batch, index) => (
                                                            <Badge
                                                                key={index}
                                                                variant="secondary"
                                                                className="font-mono"
                                                            >
                                                                {batch.quantity}
                                                                {' @ '}
                                                                <MoneyDisplay
                                                                    amount={
                                                                        batch.unit_cost
                                                                    }
                                                                    className="text-inherit"
                                                                />
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            {purchase.additional_costs.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Freight and other costs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Label</TableHead>
                                        <TableHead>Spread</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {purchase.additional_costs.map(
                                        (cost, index) => (
                                            <TableRow key={index}>
                                                <TableCell className="font-medium">
                                                    {cost.label}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {cost.allocation_label}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <MoneyDisplay
                                                        amount={cost.amount}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card className="ml-auto w-full max-w-sm">
                <CardContent className="flex flex-col gap-2 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Goods</span>
                        <MoneyDisplay amount={purchase.goods_total} />
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            Freight and other
                        </span>
                        <MoneyDisplay
                            amount={purchase.additional_costs_total}
                        />
                    </div>
                    <Separator />
                    <div className="flex items-center justify-between text-base font-semibold">
                        <span>Total</span>
                        <MoneyDisplay amount={purchase.total} />
                    </div>
                </CardContent>
            </Card>

            {purchase.notes && (
                <Card>
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm whitespace-pre-line">
                            {purchase.notes}
                        </p>
                    </CardContent>
                </Card>
            )}
        </>
    );
}

PurchasesShow.layout = [AppLayout];
