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
import { index } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type { SaleDetail } from '@/types/sales';

export default function SalesShow({ sale }: { sale: SaleDetail }) {
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
        breadcrumbs: [
            { title: 'Sales', href: index.url() },
            { title: sale.number },
        ],
    });

    const posted = sale.status === 'posted';

    return (
        <>
            <Head title={sale.number} />

            <PageHeader
                title={sale.number}
                description={`${sale.sold_on} · ${sale.payment_method}`}
                actions={
                    <>
                        {posted ? (
                            <Badge variant="secondary">Posted</Badge>
                        ) : (
                            <Badge variant="outline">Draft</Badge>
                        )}
                        <Button
                            variant="outline"
                            render={<Link href={index()} />}
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Sales
                        </Button>
                    </>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                    <CardDescription>
                        {posted
                            ? 'Cost is what these goods actually cost, from the batches the sale drew on.'
                            : 'Cost and profit appear once the sale is posted and stock has left the shelf.'}
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
                                        Price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Sold for
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Cost
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Profit
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sale.lines.map((line) => (
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
                                                amount={line.unit_price}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <MoneyDisplay
                                                amount={line.net_total}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {posted ? (
                                                <MoneyDisplay
                                                    amount={
                                                        line.cost_of_goods_sold
                                                    }
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {posted ? (
                                                <MoneyDisplay
                                                    amount={line.gross_profit}
                                                    colored
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card className="ml-auto w-full max-w-sm">
                <CardContent className="flex flex-col gap-2 text-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">Takings</span>
                        <MoneyDisplay amount={sale.total} />
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">
                            Cost of goods
                        </span>
                        {posted ? (
                            <MoneyDisplay amount={sale.cost_of_goods_sold} />
                        ) : (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                    <Separator />
                    <div className="flex items-center justify-between text-base font-semibold">
                        <span>Gross profit</span>
                        {posted ? (
                            <MoneyDisplay amount={sale.gross_profit} colored />
                        ) : (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                </CardContent>
            </Card>

            {sale.notes && (
                <Card>
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm whitespace-pre-line">
                            {sale.notes}
                        </p>
                    </CardContent>
                </Card>
            )}
        </>
    );
}

SalesShow.layout = [AppLayout];
