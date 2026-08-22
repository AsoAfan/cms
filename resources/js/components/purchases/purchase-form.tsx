import { useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { StatusPicker } from '@/components/document-status';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
import { store, update } from '@/routes/purchases';
import type {
    AdditionalCostForm,
    AllocationMethodOption,
    ProductOption,
    PurchaseDetail,
    PurchaseFormData,
    PurchaseLineForm,
    PurchaseStatusOption,
} from '@/types/purchasing';

export type PurchaseFormProps = {
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    statuses: PurchaseStatusOption[];
    /** The invoice being edited, or the reference the new one will be filed as. */
    purchase?: PurchaseDetail;
    nextNumber?: string;
    onDone: () => void;
};

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

/**
 * Writing an invoice, in a drawer over whatever screen it was opened from.
 *
 * The same form creates and edits: an invoice being corrected is the same
 * document as one being written, and having two of these to keep in step was
 * how the two ended up differing.
 */
export function PurchaseForm({
    products,
    allocationMethods,
    statuses,
    purchase,
    nextNumber,
    onDone,
}: PurchaseFormProps) {
    const { base, currencies } = useCurrency();
    const format = useFormatMoney();
    const toBase = useToBase();
    const restate = useRestate();

    const editing = purchase !== undefined;
    const invoiceCurrency = purchase?.base_currency ?? base;

    const form = useForm<PurchaseFormData>({
        invoiced_on: purchase?.invoiced_on ?? todayIso(),
        status: purchase?.status ?? 'ordered',
        currency: invoiceCurrency,
        notes: purchase?.notes ?? '',
        lines: purchase
            ? purchase.lines.map((line) => ({
                  product_id: line.product_id,
                  quantity: String(line.quantity),
                  unit_cost: line.unit_cost_decimal,
                  unit_cost_currency: invoiceCurrency,
                  discount: line.discount_decimal,
                  discount_currency: invoiceCurrency,
              }))
            : [blankLine(invoiceCurrency)],
        additional_costs:
            purchase?.additional_costs.map((cost) => ({
                label: cost.label,
                amount: cost.amount_decimal,
                amount_currency: invoiceCurrency,
                allocation_method: cost.allocation_method,
            })) ?? [],
    });

    // Freight is the exception, not the rule, so it stays folded away unless
    // this invoice actually has some.
    const [showCosts, setShowCosts] = useState(
        (purchase?.additional_costs.length ?? 0) > 0,
    );

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
        setShowCosts(true);
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

        const options = { preserveScroll: true, onSuccess: onDone };

        if (purchase) {
            form.put(update.url(purchase.id), options);
        } else {
            form.post(store.url(), options);
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
        <form
            onSubmit={submit}
            className="mx-auto flex w-full max-w-4xl flex-col gap-5"
        >
            <SheetHeader className="px-0">
                <SheetTitle className="flex items-center gap-2">
                    {editing ? 'Edit invoice' : 'New purchase'}
                    <span className="font-mono text-sm font-normal text-muted-foreground">
                        {purchase?.number ?? nextNumber}
                    </span>
                </SheetTitle>
                <SheetDescription>
                    {editing
                        ? 'Corrections re-cost the stock this invoice brought in.'
                        : 'Stock arrives when the invoice does — mark it Proceed once the goods are here.'}
                </SheetDescription>
            </SheetHeader>

            <FieldGroup>
                <FormField label="Status" error={form.errors.status}>
                    {() => (
                        <StatusPicker
                            value={form.data.status}
                            statuses={statuses}
                            onChange={(status) =>
                                form.setData('status', status)
                            }
                        />
                    )}
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Invoice date"
                        error={form.errors.invoiced_on}
                        description="Stock is taken in on this date, at that day's rate."
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

                    {currencies.length > 1 && (
                        <FormField
                            label="Paid in"
                            error={form.errors.currency}
                            description={`Recorded in ${base} either way.`}
                        >
                            {(control) => (
                                <OptionSelect
                                    {...control}
                                    className="w-full"
                                    value={form.data.currency}
                                    options={currencies.map((currency) => ({
                                        value: currency.code,
                                        label: `${currency.code} — ${currency.name}`,
                                    }))}
                                    onChange={(value) =>
                                        changeInvoiceCurrency(value)
                                    }
                                />
                            )}
                        </FormField>
                    )}
                </div>
            </FieldGroup>

            <div className="flex flex-col gap-3">
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
                                <TableHead className="w-20 text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="w-32 text-right">
                                    Unit cost
                                </TableHead>
                                <TableHead className="w-32 text-right">
                                    Discount
                                </TableHead>
                                <TableHead className="w-28 text-right">
                                    Net
                                </TableHead>
                                <TableHead className="w-10" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {form.data.lines.map((line, index) => (
                                <TableRow key={index}>
                                    <TableCell>
                                        <OptionSelect
                                            className="w-full min-w-40"
                                            aria-label={`Product for line ${index + 1}`}
                                            aria-invalid={
                                                lineError(index, 'product_id')
                                                    ? true
                                                    : undefined
                                            }
                                            value={
                                                line.product_id === null
                                                    ? ''
                                                    : String(line.product_id)
                                            }
                                            options={products.map(
                                                (product) => ({
                                                    value: String(product.id),
                                                    label: product.name,
                                                }),
                                            )}
                                            onChange={(value) =>
                                                chooseProduct(
                                                    index,
                                                    Number(value),
                                                )
                                            }
                                            placeholder="Choose a product"
                                        />
                                        {lineError(index, 'product_id') && (
                                            <p className="mt-1 text-xs text-destructive">
                                                {lineError(index, 'product_id')}
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
                                                lineError(index, 'unit_cost')
                                                    ? true
                                                    : undefined
                                            }
                                            value={line.unit_cost}
                                            currency={line.unit_cost_currency}
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
                                            currency={line.discount_currency}
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
                                                    discount_currency: currency,
                                                })
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-sm tabular-nums">
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
                                            onClick={() => removeLine(index)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addLine}
                    >
                        <Plus data-icon="inline-start" />
                        Add line
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-expanded={showCosts}
                        onClick={() => setShowCosts((open) => !open)}
                    >
                        {showCosts ? (
                            <ChevronDown data-icon="inline-start" />
                        ) : (
                            <ChevronRight data-icon="inline-start" />
                        )}
                        Freight and other costs
                        {form.data.additional_costs.length > 0 &&
                            ` (${form.data.additional_costs.length})`}
                    </Button>
                </div>
            </div>

            {showCosts && (
                <div className="flex flex-col gap-3 rounded-lg border border-dashed p-3">
                    <p className="text-xs text-muted-foreground">
                        Spread across the lines, so they end up inside the cost
                        of the goods rather than beside it.
                    </p>

                    {form.data.additional_costs.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Label</TableHead>
                                        <TableHead className="w-32 text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="w-44">
                                            Spread
                                        </TableHead>
                                        <TableHead className="w-10" />
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
                                                    <OptionSelect
                                                        className="w-full"
                                                        aria-label={`How cost ${index + 1} is spread`}
                                                        value={
                                                            cost.allocation_method
                                                        }
                                                        options={
                                                            allocationMethods
                                                        }
                                                        onChange={(value) =>
                                                            updateCost(index, {
                                                                allocation_method:
                                                                    value,
                                                            })
                                                        }
                                                    />
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
                </div>
            )}

            <FormField label="Notes" error={form.errors.notes}>
                {(control) => (
                    <Textarea
                        {...control}
                        rows={2}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                )}
            </FormField>

            <Separator />

            <div className="flex flex-wrap items-center justify-between gap-3 pb-2">
                <div className="flex flex-col text-sm">
                    {costsTotal > 0 && (
                        <span className="text-muted-foreground">
                            Goods {format(goodsTotal)} + costs{' '}
                            {format(costsTotal)}
                        </span>
                    )}
                    {/* No currency in the label: `format` puts it on the figure,
                        and the figure follows whichever currency the user is
                        reading in. */}
                    <span className="text-base font-semibold">
                        Total {format(goodsTotal + costsTotal)}
                    </span>
                </div>

                <div className="flex gap-2">
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Record purchase'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
