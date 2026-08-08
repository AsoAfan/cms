import { useForm } from '@inertiajs/react';
import { Search, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
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
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    useCurrency,
    useFormatMoney,
    useRestate,
    useToBase,
} from '@/hooks/use-currency';
import { todayIso } from '@/lib/date';
import type {
    PaymentMethodOption,
    SaleFormData,
    SaleLineForm,
    SellableProduct,
} from '@/types/sales';

export type SaleFormProps = {
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    sale?: SaleFormData;
    action: { url: string; method: 'post' | 'put' };
    title: string;
    headerActions?: React.ReactNode;
    submitLabel: string;
};

/**
 * Base-currency minor units for one line: quantity x price, less the discount.
 *
 * Each amount is converted from whatever it was typed in before anything is
 * multiplied or subtracted — a running total means nothing otherwise once one
 * line is in dollars and the next is in dinars.
 */
function lineNet(
    line: SaleLineForm,
    toBase: (value: string, currency: string) => number,
): number {
    const quantity = Number(line.quantity);

    if (!Number.isFinite(quantity) || quantity < 0) {
        return 0;
    }

    return (
        quantity * toBase(line.unit_price, line.unit_price_currency) -
        toBase(line.discount, line.discount_currency)
    );
}

export function SaleForm({
    products,
    paymentMethods,
    sale,
    action,
    title,
    headerActions,
    submitLabel,
}: SaleFormProps) {
    const { base, currencies } = useCurrency();
    const format = useFormatMoney();
    const toBase = useToBase();
    const restate = useRestate();
    const scanRef = useRef<HTMLInputElement>(null);
    const [scan, setScan] = useState('');
    const [scanError, setScanError] = useState<string | null>(null);

    const form = useForm<SaleFormData>({
        sold_on: sale?.sold_on ?? todayIso(),
        payment_method:
            sale?.payment_method ?? paymentMethods[0]?.value ?? 'cash',
        currency: sale?.currency ?? base,
        notes: sale?.notes ?? '',
        lines: sale?.lines ?? [],
    });

    const total = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) => sum + lineNet(line, toBase),
                0,
            ),
        [form.data.lines, toBase],
    );

    /**
     * Changing what the customer paid in carries every amount still on the old
     * currency across with it — converting each one, exactly as a field's own
     * dropdown does — and leaves alone any field switched by hand.
     */
    function changeSaleCurrency(next: string) {
        const previous = form.data.currency;
        const moved = (currency: string) => currency === previous;
        const follow = (value: string, currency: string) =>
            moved(currency) ? restate(value, currency, next) : value;

        form.setData((data) => ({
            ...data,
            currency: next,
            lines: data.lines.map((line) => ({
                ...line,
                unit_price: follow(line.unit_price, line.unit_price_currency),
                unit_price_currency: moved(line.unit_price_currency)
                    ? next
                    : line.unit_price_currency,
                discount: follow(line.discount, line.discount_currency),
                discount_currency: moved(line.discount_currency)
                    ? next
                    : line.discount_currency,
            })),
        }));
    }

    const byId = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );

    /**
     * Type a name and press Enter. An exact name wins over a partial one, so
     * "Voile Panel 117" does not get beaten by a longer name containing it. A
     * product already on the sale gets one more rather than a second line,
     * which is what a till should do.
     */
    function addByName(term: string) {
        const needle = term.trim().toLowerCase();

        if (needle === '') {
            return;
        }

        const product =
            products.find(
                (candidate) => candidate.name.toLowerCase() === needle,
            ) ??
            products.find((candidate) =>
                candidate.name.toLowerCase().includes(needle),
            );

        if (!product) {
            setScanError(`Nothing matches "${term}".`);

            return;
        }

        setScanError(null);
        setScan('');

        const existing = form.data.lines.findIndex(
            (line) => line.product_id === product.id,
        );

        if (existing >= 0) {
            const line = form.data.lines[existing];

            updateLine(existing, {
                quantity: String((Number(line.quantity) || 0) + 1),
            });

            return;
        }

        form.setData('lines', [
            ...form.data.lines,
            {
                product_id: product.id,
                quantity: '1',
                // The catalogue price is held in the base currency, so the field
                // opens in the base currency to match it.
                unit_price: product.selling_price,
                unit_price_currency: base,
                discount: '',
                discount_currency: form.data.currency,
            },
        ]);
    }

    function updateLine(index: number, patch: Partial<SaleLineForm>) {
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );
    }

    function removeLine(index: number) {
        form.setData(
            'lines',
            form.data.lines.filter((_, position) => position !== index),
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (action.method === 'put') {
            form.put(action.url, { preserveScroll: true });
        } else {
            form.post(action.url);
        }
    }

    function lineError(index: number, field: string): string | undefined {
        return form.errors[
            `lines.${index}.${field}` as keyof typeof form.errors
        ] as string | undefined;
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <PageHeader
                title={title}
                actions={
                    <>
                        {headerActions}
                        <Button type="submit" disabled={form.processing}>
                            {submitLabel}
                        </Button>
                    </>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                    <CardDescription>
                        Type a product name and press Enter. Entering the same
                        one again adds another.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="max-w-md">
                        <InputGroup>
                            <InputGroupInput
                                ref={scanRef}
                                value={scan}
                                autoFocus
                                placeholder="Type a product name"
                                aria-label="Add a product by name"
                                aria-invalid={scanError ? true : undefined}
                                onChange={(event) => {
                                    setScan(event.target.value);
                                    setScanError(null);
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        addByName(scan);
                                    }
                                }}
                            />
                            <InputGroupAddon>
                                <Search />
                            </InputGroupAddon>
                        </InputGroup>
                        {scanError && (
                            <p className="mt-1 text-xs text-destructive">
                                {scanError}
                            </p>
                        )}
                    </div>

                    {typeof form.errors.lines === 'string' && (
                        <p className="text-sm text-destructive">
                            {form.errors.lines}
                        </p>
                    )}

                    {form.data.lines.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing on this sale yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="w-24 text-right">
                                            Qty
                                        </TableHead>
                                        <TableHead className="w-32 text-right">
                                            Price
                                        </TableHead>
                                        <TableHead className="w-32 text-right">
                                            Discount
                                        </TableHead>
                                        <TableHead className="w-32 text-right">
                                            Total
                                        </TableHead>
                                        <TableHead className="w-12" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {form.data.lines.map((line, index) => {
                                        const product =
                                            line.product_id === null
                                                ? undefined
                                                : byId.get(line.product_id);
                                        const wanted = Number(line.quantity);
                                        const short =
                                            product !== undefined &&
                                            Number.isFinite(wanted) &&
                                            wanted > product.on_hand;

                                        return (
                                            <TableRow key={index}>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        {product?.name ??
                                                            'Unknown'}
                                                    </span>
                                                    {short && (
                                                        <Badge
                                                            variant="outline"
                                                            className="mt-1 text-destructive"
                                                        >
                                                            only{' '}
                                                            {product?.on_hand}{' '}
                                                            in stock
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        inputMode="numeric"
                                                        className="text-right tabular-nums"
                                                        aria-label={`Quantity for ${product?.name ?? 'line'}`}
                                                        aria-invalid={
                                                            lineError(
                                                                index,
                                                                'quantity',
                                                            )
                                                                ? true
                                                                : undefined
                                                        }
                                                        value={line.quantity}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                quantity:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <MoneyInput
                                                        aria-label={`Price for ${product?.name ?? 'line'}`}
                                                        aria-invalid={
                                                            lineError(
                                                                index,
                                                                'unit_price',
                                                            )
                                                                ? true
                                                                : undefined
                                                        }
                                                        value={line.unit_price}
                                                        currency={
                                                            line.unit_price_currency
                                                        }
                                                        onChange={(value) =>
                                                            updateLine(index, {
                                                                unit_price:
                                                                    value,
                                                            })
                                                        }
                                                        onCurrencyChange={(
                                                            currency,
                                                        ) =>
                                                            updateLine(index, {
                                                                unit_price_currency:
                                                                    currency,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <MoneyInput
                                                        aria-label={`Discount for ${product?.name ?? 'line'}`}
                                                        value={line.discount}
                                                        currency={
                                                            line.discount_currency
                                                        }
                                                        onChange={(value) =>
                                                            updateLine(index, {
                                                                discount: value,
                                                            })
                                                        }
                                                        onCurrencyChange={(
                                                            currency,
                                                        ) =>
                                                            updateLine(index, {
                                                                discount_currency:
                                                                    currency,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right font-mono tabular-nums">
                                                    {format(
                                                        lineNet(line, toBase),
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={`Remove ${product?.name ?? 'line'}`}
                                                        onClick={() =>
                                                            removeLine(index)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Sale</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    label="Date"
                                    error={form.errors.sold_on}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            type="date"
                                            value={form.data.sold_on}
                                            onChange={(event) =>
                                                form.setData(
                                                    'sold_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>

                                <FormField
                                    label="Payment"
                                    error={form.errors.payment_method}
                                >
                                    {(control) => (
                                        <Select
                                            value={form.data.payment_method}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'payment_method',
                                                    String(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                {...control}
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {paymentMethods.map(
                                                    (method) => (
                                                        <SelectItem
                                                            key={method.value}
                                                            value={method.value}
                                                        >
                                                            {method.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </FormField>
                            </div>

                            {currencies.length > 1 && (
                                <div className="grid gap-6 sm:grid-cols-2">
                                    <FormField
                                        label="Paid in"
                                        error={form.errors.currency}
                                        description={`Sets the currency for every price above. Recorded in ${base} either way.`}
                                    >
                                        {(control) => (
                                            <Select
                                                value={form.data.currency}
                                                onValueChange={(value) =>
                                                    changeSaleCurrency(
                                                        String(value),
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    {...control}
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {currencies.map(
                                                        (currency) => (
                                                            <SelectItem
                                                                key={
                                                                    currency.code
                                                                }
                                                                value={
                                                                    currency.code
                                                                }
                                                            >
                                                                {currency.code}{' '}
                                                                —{' '}
                                                                {currency.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </FormField>
                                </div>
                            )}

                            <FormField label="Notes" error={form.errors.notes}>
                                {(control) => (
                                    <Textarea
                                        {...control}
                                        rows={2}
                                        value={form.data.notes ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Items</span>
                            <span className="tabular-nums">
                                {form.data.lines.length}
                            </span>
                        </div>
                        <Separator />
                        {/* No currency in the label: `format` puts it on the
                            figure, which follows whichever currency the user is
                            reading in. */}
                        <div className="flex justify-between text-xl font-semibold">
                            <span>Total</span>
                            <span className="font-mono tabular-nums">
                                {format(total)}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    );
}
