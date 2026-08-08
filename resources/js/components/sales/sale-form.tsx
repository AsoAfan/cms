import { useForm } from '@inertiajs/react';
import { Search, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

import { FormField } from '@/components/form-field';
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
import { useCurrency, useFormatMoney } from '@/hooks/use-currency';
import { parseMoney } from '@/lib/money';
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

/** Minor units for one line: quantity x price, less the discount. */
function lineNet(line: SaleLineForm): number {
    const quantity = Number(line.quantity);
    const price = parseMoney(line.unit_price) ?? 0;
    const discount = parseMoney(line.discount) ?? 0;

    if (!Number.isFinite(quantity) || quantity < 0) {
        return 0;
    }

    return quantity * price - discount;
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
    const currency = useCurrency();
    const format = useFormatMoney();
    const scanRef = useRef<HTMLInputElement>(null);
    const [scan, setScan] = useState('');
    const [scanError, setScanError] = useState<string | null>(null);

    const form = useForm<SaleFormData>({
        sold_on: sale?.sold_on ?? new Date().toISOString().slice(0, 10),
        payment_method:
            sale?.payment_method ?? paymentMethods[0]?.value ?? 'cash',
        notes: sale?.notes ?? '',
        lines: sale?.lines ?? [],
    });

    const total = useMemo(
        () => form.data.lines.reduce((sum, line) => sum + lineNet(line), 0),
        [form.data.lines],
    );

    const byId = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );

    /**
     * Type or scan a code and press Enter. A product already on the sale gets
     * one more rather than a second line, which is what a till should do.
     */
    function addByCode(term: string) {
        const needle = term.trim().toLowerCase();

        if (needle === '') {
            return;
        }

        const product =
            products.find(
                (candidate) => candidate.code.toLowerCase() === needle,
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
                unit_price: product.default_selling_price ?? '',
                discount: '',
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
                        Type or scan a code and press Enter. Scanning the same
                        code again adds one more.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="max-w-md">
                        <InputGroup>
                            <InputGroupInput
                                ref={scanRef}
                                value={scan}
                                autoFocus
                                placeholder="Scan or type a code"
                                aria-label="Scan or type a product code"
                                aria-invalid={scanError ? true : undefined}
                                onChange={(event) => {
                                    setScan(event.target.value);
                                    setScanError(null);
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        addByCode(scan);
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
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">
                                                            {product?.name ??
                                                                'Unknown'}
                                                        </span>
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {product?.code}
                                                        </span>
                                                    </div>
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
                                                    <Input
                                                        inputMode="decimal"
                                                        placeholder="0.00"
                                                        className="text-right tabular-nums"
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
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                unit_price:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        inputMode="decimal"
                                                        placeholder="0.00"
                                                        className="text-right tabular-nums"
                                                        aria-label={`Discount for ${product?.name ?? 'line'}`}
                                                        value={line.discount}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                discount:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right font-mono tabular-nums">
                                                    {format(lineNet(line))}
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
                        <div className="flex justify-between text-xl font-semibold">
                            <span>Total ({currency.code})</span>
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
