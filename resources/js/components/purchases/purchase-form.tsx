import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { PageHeader } from '@/components/page-header';
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
    AdditionalCostForm,
    AllocationMethodOption,
    ProductOption,
    PurchaseFormData,
    PurchaseLineForm,
    SupplierOption,
} from '@/types/purchasing';

export type PurchaseFormProps = {
    suppliers: SupplierOption[];
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    purchase?: PurchaseFormData;
    action: { url: string; method: 'post' | 'put' };
    title: string;
    headerActions?: React.ReactNode;
    submitLabel: string;
};

/**
 * Stands in for "nobody" in the supplier select, which cannot hold null: the
 * supplier is optional, so leaving it off has to be something you can pick and
 * pick back off, not just an untouched box.
 */
const NO_SUPPLIER = 'none';

function blankLine(currency: string): PurchaseLineForm {
    return {
        product_id: null,
        quantity: '1',
        unit_cost: '',
        unit_cost_currency: currency,
        discount: '',
        discount_currency: currency,
    };
}

/**
 * Base-currency minor units for one line: quantity x unit cost, less the
 * discount.
 *
 * Each amount is converted from whatever it was typed in before anything is
 * multiplied or subtracted. An invoice whose goods are priced in dollars and
 * whose discount was given in dinars has no total in either currency alone.
 */
function lineNet(
    line: PurchaseLineForm,
    toBase: (value: string, currency: string) => number,
): number {
    const quantity = Number(line.quantity);

    if (!Number.isFinite(quantity) || quantity < 0) {
        return 0;
    }

    return (
        quantity * toBase(line.unit_cost, line.unit_cost_currency) -
        toBase(line.discount, line.discount_currency)
    );
}

export function PurchaseForm({
    suppliers,
    products,
    allocationMethods,
    purchase,
    action,
    title,
    headerActions,
    submitLabel,
}: PurchaseFormProps) {
    const { base, currencies } = useCurrency();
    const format = useFormatMoney();
    const toBase = useToBase();
    const restate = useRestate();

    const invoiceCurrency = purchase?.currency ?? base;

    const form = useForm<PurchaseFormData>({
        supplier_id: purchase?.supplier_id ?? null,
        invoiced_on: purchase?.invoiced_on ?? todayIso(),
        currency: invoiceCurrency,
        notes: purchase?.notes ?? '',
        lines: purchase?.lines?.length
            ? purchase.lines
            : [blankLine(invoiceCurrency)],
        additional_costs: purchase?.additional_costs ?? [],
    });

    const goodsTotal = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) => sum + lineNet(line, toBase),
                0,
            ),
        [form.data.lines, toBase],
    );

    const costsTotal = useMemo(
        () =>
            form.data.additional_costs.reduce(
                (sum, cost) => sum + toBase(cost.amount, cost.amount_currency),
                0,
            ),
        [form.data.additional_costs, toBase],
    );

    /**
     * Changing what the invoice was written in carries every amount still on the
     * old currency across with it — converting each one, exactly as a field's own
     * dropdown does — and leaves alone any field switched by hand. Setting the
     * header to dollars should not undo the one line somebody deliberately put in
     * dinars.
     */
    function changeInvoiceCurrency(next: string) {
        const previous = form.data.currency;
        const moved = (currency: string) => currency === previous;
        const follow = (value: string, currency: string) =>
            moved(currency) ? restate(value, currency, next) : value;

        form.setData((data) => ({
            ...data,
            currency: next,
            lines: data.lines.map((line) => ({
                ...line,
                unit_cost: follow(line.unit_cost, line.unit_cost_currency),
                unit_cost_currency: moved(line.unit_cost_currency)
                    ? next
                    : line.unit_cost_currency,
                discount: follow(line.discount, line.discount_currency),
                discount_currency: moved(line.discount_currency)
                    ? next
                    : line.discount_currency,
            })),
            additional_costs: data.additional_costs.map((cost) => ({
                ...cost,
                amount: follow(cost.amount, cost.amount_currency),
                amount_currency: moved(cost.amount_currency)
                    ? next
                    : cost.amount_currency,
            })),
        }));
    }

    function updateLine(index: number, patch: Partial<PurchaseLineForm>) {
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );
    }

    function chooseProduct(index: number, productId: number) {
        const product = products.find(
            (candidate) => candidate.id === productId,
        );

        // The catalogue price is held in the base currency, so pre-filling it
        // has to put the field back into the base currency to match.
        const prefill = form.data.lines[index].unit_cost
            ? {}
            : {
                  unit_cost: product?.cost_price ?? '',
                  unit_cost_currency: base,
              };

        updateLine(index, { product_id: productId, ...prefill });
    }

    function addLine() {
        form.setData('lines', [
            ...form.data.lines,
            blankLine(form.data.currency),
        ]);
    }

    function removeLine(index: number) {
        form.setData(
            'lines',
            form.data.lines.filter((_, position) => position !== index),
        );
    }

    function addCost() {
        form.setData('additional_costs', [
            ...form.data.additional_costs,
            {
                label: '',
                amount: '',
                amount_currency: form.data.currency,
                allocation_method: allocationMethods[0]?.value ?? 'by_quantity',
            },
        ]);
    }

    function updateCost(index: number, patch: Partial<AdditionalCostForm>) {
        form.setData(
            'additional_costs',
            form.data.additional_costs.map((cost, position) =>
                position === index ? { ...cost, ...patch } : cost,
            ),
        );
    }

    function removeCost(index: number) {
        form.setData(
            'additional_costs',
            form.data.additional_costs.filter(
                (_, position) => position !== index,
            ),
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

    /** Enter on the last line adds another, so a whole invoice is typeable. */
    function onLineKeyDown(event: React.KeyboardEvent, index: number) {
        if (event.key === 'Enter') {
            event.preventDefault();

            if (index === form.data.lines.length - 1) {
                addLine();
            }
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
                    <CardTitle>Invoice</CardTitle>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <FormField
                                label="Supplier"
                                error={form.errors.supplier_id}
                                description="Optional — an invoice records itself without one."
                            >
                                {(control) => (
                                    <Select
                                        value={
                                            form.data.supplier_id === null
                                                ? NO_SUPPLIER
                                                : String(form.data.supplier_id)
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'supplier_id',
                                                value === NO_SUPPLIER
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            {...control}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="No supplier" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NO_SUPPLIER}>
                                                No supplier
                                            </SelectItem>
                                            {suppliers.map((supplier) => (
                                                <SelectItem
                                                    key={supplier.id}
                                                    value={String(supplier.id)}
                                                >
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>

                            <FormField
                                label="Invoice date"
                                error={form.errors.invoiced_on}
                                description="Stock is taken in on this date, at that day's exchange rate."
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="date"
                                        value={form.data.invoiced_on}
                                        onChange={(event) =>
                                            form.setData(
                                                'invoiced_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>

                        {currencies.length > 1 && (
                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    label="Paid in"
                                    error={form.errors.currency}
                                    description={`Sets the currency for every amount below. Recorded in ${base} either way.`}
                                >
                                    {(control) => (
                                        <Select
                                            value={form.data.currency}
                                            onValueChange={(value) =>
                                                changeInvoiceCurrency(
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
                                                {currencies.map((currency) => (
                                                    <SelectItem
                                                        key={currency.code}
                                                        value={currency.code}
                                                    >
                                                        {currency.code} —{' '}
                                                        {currency.name}
                                                    </SelectItem>
                                                ))}
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
                <CardHeader>
                    <CardTitle>Lines</CardTitle>
                    <CardDescription>
                        Press Enter on the last line to add another.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {typeof form.errors.lines === 'string' && (
                        <p className="text-sm text-destructive">
                            {form.errors.lines}
                        </p>
                    )}

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="w-32 text-right">
                                        Unit cost
                                    </TableHead>
                                    <TableHead className="w-32 text-right">
                                        Discount
                                    </TableHead>
                                    <TableHead className="w-32 text-right">
                                        Net
                                    </TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {form.data.lines.map((line, index) => (
                                    <TableRow key={index}>
                                        <TableCell>
                                            <Select
                                                value={
                                                    line.product_id === null
                                                        ? ''
                                                        : String(
                                                              line.product_id,
                                                          )
                                                }
                                                onValueChange={(value) =>
                                                    chooseProduct(
                                                        index,
                                                        Number(value),
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    className="w-full"
                                                    aria-label={`Product for line ${index + 1}`}
                                                    aria-invalid={
                                                        lineError(
                                                            index,
                                                            'product_id',
                                                        )
                                                            ? true
                                                            : undefined
                                                    }
                                                >
                                                    <SelectValue placeholder="Choose a product" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {products.map((product) => (
                                                        <SelectItem
                                                            key={product.id}
                                                            value={String(
                                                                product.id,
                                                            )}
                                                        >
                                                            {product.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {lineError(index, 'product_id') && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {lineError(
                                                        index,
                                                        'product_id',
                                                    )}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                inputMode="numeric"
                                                className="text-right tabular-nums"
                                                aria-label={`Quantity for line ${index + 1}`}
                                                aria-invalid={
                                                    lineError(index, 'quantity')
                                                        ? true
                                                        : undefined
                                                }
                                                value={line.quantity}
                                                onKeyDown={(event) =>
                                                    onLineKeyDown(event, index)
                                                }
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        quantity:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <MoneyInput
                                                aria-label={`Unit cost for line ${index + 1}`}
                                                aria-invalid={
                                                    lineError(
                                                        index,
                                                        'unit_cost',
                                                    )
                                                        ? true
                                                        : undefined
                                                }
                                                value={line.unit_cost}
                                                currency={
                                                    line.unit_cost_currency
                                                }
                                                onKeyDown={(event) =>
                                                    onLineKeyDown(event, index)
                                                }
                                                onChange={(value) =>
                                                    updateLine(index, {
                                                        unit_cost: value,
                                                    })
                                                }
                                                onCurrencyChange={(currency) =>
                                                    updateLine(index, {
                                                        unit_cost_currency:
                                                            currency,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <MoneyInput
                                                aria-label={`Discount for line ${index + 1}`}
                                                value={line.discount}
                                                currency={
                                                    line.discount_currency
                                                }
                                                onKeyDown={(event) =>
                                                    onLineKeyDown(event, index)
                                                }
                                                onChange={(value) =>
                                                    updateLine(index, {
                                                        discount: value,
                                                    })
                                                }
                                                onCurrencyChange={(currency) =>
                                                    updateLine(index, {
                                                        discount_currency:
                                                            currency,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-mono tabular-nums">
                                            {format(lineNet(line, toBase))}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={`Remove line ${index + 1}`}
                                                disabled={
                                                    form.data.lines.length === 1
                                                }
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addLine}
                        >
                            <Plus data-icon="inline-start" />
                            Add line
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Freight and other costs</CardTitle>
                    <CardDescription>
                        Spread across the lines, so they end up inside the cost
                        of the goods rather than beside it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {form.data.additional_costs.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Label</TableHead>
                                        <TableHead className="w-32 text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="w-48">
                                            Spread
                                        </TableHead>
                                        <TableHead className="w-12" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {form.data.additional_costs.map(
                                        (cost, index) => (
                                            <TableRow key={index}>
                                                <TableCell>
                                                    <Input
                                                        placeholder="Freight"
                                                        aria-label={`Cost label ${index + 1}`}
                                                        value={cost.label}
                                                        onChange={(event) =>
                                                            updateCost(index, {
                                                                label: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <MoneyInput
                                                        aria-label={`Cost amount ${index + 1}`}
                                                        value={cost.amount}
                                                        currency={
                                                            cost.amount_currency
                                                        }
                                                        onChange={(value) =>
                                                            updateCost(index, {
                                                                amount: value,
                                                            })
                                                        }
                                                        onCurrencyChange={(
                                                            currency,
                                                        ) =>
                                                            updateCost(index, {
                                                                amount_currency:
                                                                    currency,
                                                            })
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Select
                                                        value={
                                                            cost.allocation_method
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateCost(index, {
                                                                allocation_method:
                                                                    String(
                                                                        value,
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="w-full"
                                                            aria-label={`How cost ${index + 1} is spread`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {allocationMethods.map(
                                                                (method) => (
                                                                    <SelectItem
                                                                        key={
                                                                            method.value
                                                                        }
                                                                        value={
                                                                            method.value
                                                                        }
                                                                    >
                                                                        {
                                                                            method.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={`Remove cost ${index + 1}`}
                                                        onClick={() =>
                                                            removeCost(index)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addCost}
                        >
                            <Plus data-icon="inline-start" />
                            Add cost
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card className="ml-auto w-full max-w-sm">
                <CardContent className="flex flex-col gap-2 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Goods</span>
                        <span className="font-mono tabular-nums">
                            {format(goodsTotal)}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            Freight and other
                        </span>
                        <span className="font-mono tabular-nums">
                            {format(costsTotal)}
                        </span>
                    </div>
                    <Separator />
                    {/* No currency in the label: `format` puts it on the figure,
                        and the figure follows whichever currency the user is
                        reading in. */}
                    <div className="flex justify-between text-base font-semibold">
                        <span>Total</span>
                        <span className="font-mono tabular-nums">
                            {format(goodsTotal + costsTotal)}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}
