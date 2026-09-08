import { Head, Link } from '@inertiajs/react';
import { HandCoins, PackageX, ShoppingCart, Users } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { PurchaseDrawer } from '@/components/purchases/purchase-drawer';
import { StatTile } from '@/components/reports/stat-tile';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import purchases from '@/routes/purchases';
import { show as showSale } from '@/routes/sales';
import type { BreadcrumbItem } from '@/types';
import type { LoansPage } from '@/types/loans';
import type { PurchaseFormOptions, PurchaseLineSeed } from '@/types/purchasing';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Loans' }];

/**
 * Both loans in one place: goods the shop owes out, and money it is owed in.
 *
 * They are made of different things and derived by different queries, but they
 * are the same question to whoever is running the shop — so they get one screen
 * with a clear line down the middle rather than a figure buried on each of two
 * others.
 *
 * Nothing here is period arithmetic. Every figure is what stands today, which
 * is why the screen has no date range.
 */
export default function Loans({
    goods,
    customers,
    products,
    allocationMethods,
    statuses,
    paymentMethods,
    banks,
    nextNumber,
}: LoansPage & PurchaseFormOptions) {
    // What the drawer opens filled with. Null while it is shut, so each opening
    // starts from the loan as it stands rather than from the last order that
    // was abandoned.
    const [buying, setBuying] = useState<PurchaseLineSeed[] | null>(null);

    /** Buy exactly what is short — the number the row is telling you to order. */
    const toBuy = (rows: LoansPage['goods']['rows']): PurchaseLineSeed[] =>
        rows.map((row) => ({
            product_id: row.product_id,
            quantity: row.short,
        }));

    return (
        <>
            <Head title="Loans" />

            <PageHeader
                title="Loans"
                description="What you owe, and what you are owed. Both as they stand today."
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <StatTile
                    label="Owed by you"
                    value={goods.value}
                    money
                    hint={
                        goods.items === 0
                            ? 'Every order can be covered'
                            : `${goods.items} ${
                                  goods.items === 1 ? 'item' : 'items'
                              } sold and not in stock, at cost`
                    }
                />
                <StatTile
                    label="Owed to you"
                    value={customers.total}
                    money
                    hint={
                        customers.rows.length === 0
                            ? 'Nobody owes anything'
                            : `Across ${customers.rows.length} ${
                                  customers.rows.length === 1
                                      ? 'customer'
                                      : 'customers'
                              }`
                    }
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <PackageX className="size-4" />
                        Owed by you
                    </CardTitle>
                    <CardDescription>
                        Goods sold that are not on the shelf. Each order is held
                        until its stock is purchased, and buying it in clears
                        these by itself.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    {goods.rows.length === 0 ? (
                        <EmptyState
                            icon={PackageX}
                            title="You owe nothing"
                            description="Every order on the books can be covered from stock."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="text-right">
                                            Ordered
                                        </TableHead>
                                        <TableHead className="text-right">
                                            In stock
                                        </TableHead>
                                        <TableHead className="text-right">
                                            To buy
                                        </TableHead>
                                        <TableHead className="text-right">
                                            At cost
                                        </TableHead>
                                        <TableHead>Waiting on</TableHead>
                                        <TableHead className="w-px" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {goods.rows.map((row) => (
                                        <TableRow key={row.product_id}>
                                            <TableCell className="font-medium">
                                                {row.product}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.quantity}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.on_hand}
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-destructive tabular-nums">
                                                {row.short}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyDisplay
                                                    amount={row.value}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {/* The invoices this line is
                                                    holding up, oldest first —
                                                    who has been waiting
                                                    longest. */}
                                                <span className="flex flex-wrap gap-x-2 gap-y-1">
                                                    {row.sales.map((sale) => (
                                                        <Link
                                                            key={sale.id}
                                                            href={showSale(
                                                                sale.id,
                                                            )}
                                                            className="font-mono text-xs underline underline-offset-2 hover:no-underline"
                                                        >
                                                            {sale.number}
                                                        </Link>
                                                    ))}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setBuying(toBuy([row]))
                                                    }
                                                >
                                                    Buy
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>

                {goods.rows.length > 0 && (
                    <CardFooter className="gap-2">
                        {/* The whole loan as one invoice — the common case, since
                            a shop short of five things orders them together. */}
                        <Button
                            size="sm"
                            onClick={() => setBuying(toBuy(goods.rows))}
                        >
                            <ShoppingCart data-icon="inline-start" />
                            Buy everything owed
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            render={<Link href={purchases.index()} />}
                        >
                            All purchases
                        </Button>
                    </CardFooter>
                )}
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <HandCoins className="size-4" />
                        Owed to you
                    </CardTitle>
                    <CardDescription>
                        Unpaid on delivered invoices. An order still on its way
                        owes nothing — money on one is a deposit until the
                        customer has the goods.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    {customers.rows.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Nobody owes you anything"
                            description="Every delivered invoice has been paid for."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Customer</TableHead>
                                        <TableHead className="text-right">
                                            Owed
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {customers.rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link
                                                    href={showCustomer(row.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {/* A negative balance is a
                                                    credit, not a debt. Coloured
                                                    so it never reads as one. */}
                                                <MoneyDisplay
                                                    amount={row.balance}
                                                    colored={row.balance < 0}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>
            {/* The order the list is telling you to place, opened for review in
                the same drawer the purchases screen writes one in. Nothing is
                recorded until it is saved, and receiving it is what clears the
                loan — see `GoodsOwedQuery`. */}
            <PurchaseDrawer
                open={buying !== null}
                onOpenChange={(open) => !open && setBuying(null)}
                products={products}
                allocationMethods={allocationMethods}
                statuses={statuses}
                paymentMethods={paymentMethods}
                banks={banks}
                nextNumber={nextNumber}
                prefill={buying ?? undefined}
            />
        </>
    );
}

Loans.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
