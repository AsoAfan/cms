import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

import { BankField, bankAfterMethodChange } from '@/components/bank-field';
import { StatusPicker } from '@/components/document-status';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { OptionSelect } from '@/components/option-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
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
import { store, update } from '@/routes/sales';
import type { BankOption } from '@/types/banks';
import { NO_BANK } from '@/types/banks';
import type { SaleCustomer } from '@/types/customers';
import type {
    PaymentMethodOption,
    SaleDetail,
    SaleFormData,
    SaleLineForm,
    SaleStatusOption,
    SellableProduct,
} from '@/types/sales';

export type SaleFormProps = {
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    /** The accounts a card or transfer can be taken into. */
    banks: BankOption[];
    statuses: SaleStatusOption[];
    /** Walk-in first, so counter trade is already selected. */
    customers: SaleCustomer[];
    /** The sale being corrected, or the reference the new one will be filed as. */
    sale?: SaleDetail;
    nextNumber?: string;
    onDone: () => void;
};

function blankLine(currency: string): SaleLineForm {
    return {
        product_id: null,
        quantity: '1',
        unit_price: '',
        unit_price_currency: currency,
        discount: '',
        discount_currency: currency,
    };
}

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

/**
 * Ringing up a sale, in a drawer over whatever screen it was opened from.
 *
 * The same form creates and edits: a sale being corrected is the same document
 * as one being rung up, and having two of these to keep in step was how the two
 * ended up differing.
 */
export function SaleForm({
    products,
    paymentMethods,
    banks,
    statuses,
    customers,
    sale,
    nextNumber,
    onDone,
}: SaleFormProps) {
    const { base, currencies } = useCurrency();
    const format = useFormatMoney();
    const toBase = useToBase();
    const restate = useRestate();

    const editing = sale !== undefined;
    // Stored amounts are base currency, so a sale reopens in it whatever it was
    // originally rung up in.
    const saleCurrency = sale?.base_currency ?? base;

    const form = useForm<SaleFormData>({
        customer_id: sale?.customer_id ?? customers[0]?.id ?? null,
        sold_on: sale?.sold_on ?? todayIso(),
        status: sale?.status ?? 'ordered',
        payment_method:
            sale?.payment_method ?? paymentMethods[0]?.value ?? 'cash',
        bank_id: sale?.bank_id ?? NO_BANK,
        // Off by default, because a new sale opens at `ordered`: goods still on
        // the shelf have not been paid for. Ticking it is one click for the
        // counter sale, and the server then takes the figure from the lines so
        // nothing is rounded on the way.
        paid_in_full: false,
        amount_paid: sale?.amount_paid_decimal ?? '',
        amount_paid_currency: saleCurrency,
        currency: saleCurrency,
        notes: sale?.notes ?? '',
        lines: sale
            ? sale.lines.map((line) => ({
                  product_id: line.product_id,
                  quantity: String(line.quantity),
                  unit_price: line.unit_price_decimal,
                  unit_price_currency: saleCurrency,
                  discount: line.discount_decimal,
                  discount_currency: saleCurrency,
              }))
            : [blankLine(saleCurrency)],
    });

    const total = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) => sum + lineNet(line, toBase),
                0,
            ),
        [form.data.lines, toBase],
    );

    const paid = form.data.paid_in_full
        ? total
        : toBase(form.data.amount_paid, form.data.amount_paid_currency);

    // What the customer is being lent. It only becomes a debt once the goods are
    // theirs, which is what the server counts too.
    const owing = total - paid;

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
     * What each line's dropdown offers, with what is on the shelf beside the
     * name — the number that decides whether this line can go out today.
     */
    const productOptions = useMemo(
        () =>
            products.map((product) => ({
                value: String(product.id),
                label: `${product.name} · ${product.on_hand} in stock`,
            })),
        [products],
    );

    function updateLine(index: number, patch: Partial<SaleLineForm>) {
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );
    }

    function chooseProduct(index: number, productId: number) {
        const product = byId.get(productId);

        // The catalogue price is held in the base currency, so pre-filling it
        // has to put the field back into the base currency to match.
        const prefill = form.data.lines[index].unit_price
            ? {}
            : {
                  unit_price: product?.selling_price ?? '',
                  unit_price_currency: base,
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

    /** Enter on the last line adds another, so a whole sale is typeable. */
    function onLineKeyDown(event: React.KeyboardEvent, index: number) {
        if (event.key === 'Enter') {
            event.preventDefault();

            if (index === form.data.lines.length - 1) {
                addLine();
            }
        }
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (sale) {
            form.put(update.url(sale.id), options);
        } else {
            form.post(store.url(), options);
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
                    {editing ? 'Edit sale' : 'New sale'}
                    <span className="font-mono text-sm font-normal text-muted-foreground">
                        {sale?.number ?? nextNumber}
                    </span>
                </SheetTitle>
                <SheetDescription>
                    {editing
                        ? 'Corrections put the stock back and take it out again to match.'
                        : 'Stock leaves when the sale goes out, not when it is asked for.'}
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
                        label="Customer"
                        error={form.errors.customer_id}
                        description="Counter trade goes to Walk-in."
                    >
                        {(control) => (
                            <OptionSelect
                                {...control}
                                className="w-full"
                                value={String(form.data.customer_id ?? '')}
                                options={customers.map((customer) => ({
                                    value: String(customer.id),
                                    label: customer.name,
                                }))}
                                onChange={(value) =>
                                    form.setData('customer_id', Number(value))
                                }
                                placeholder="Who bought it"
                            />
                        )}
                    </FormField>

                    <FormField
                        label="Date"
                        error={form.errors.sold_on}
                        description="Costed at that day's rate."
                    >
                        {(control) => (
                            <Input
                                {...control}
                                type="date"
                                value={form.data.sold_on}
                                onChange={(event) =>
                                    form.setData('sold_on', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Payment"
                        error={form.errors.payment_method}
                    >
                        {(control) => (
                            <OptionSelect
                                {...control}
                                className="w-full"
                                value={form.data.payment_method}
                                options={paymentMethods}
                                onChange={(value) => {
                                    const method = String(value);

                                    form.setData((data) => ({
                                        ...data,
                                        payment_method: method,
                                        bank_id: bankAfterMethodChange(
                                            paymentMethods,
                                            method,
                                            data.bank_id,
                                        ),
                                    }));
                                }}
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
                                        changeSaleCurrency(String(value))
                                    }
                                />
                            )}
                        </FormField>
                    )}
                </div>

                <BankField
                    banks={banks}
                    methods={paymentMethods}
                    method={form.data.payment_method}
                    value={form.data.bank_id}
                    error={form.errors.bank_id}
                    onChange={(value) => form.setData('bank_id', value)}
                />
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
                                    Price
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
                            {form.data.lines.map((line, index) => {
                                const product =
                                    line.product_id === null
                                        ? undefined
                                        : byId.get(line.product_id);
                                const wanted = Number(line.quantity);
                                // Selling more than is on the shelf is
                                // allowed — an order can be placed for goods
                                // still on their way in — but it is worth
                                // saying before it goes out.
                                const short =
                                    product !== undefined &&
                                    Number.isFinite(wanted) &&
                                    wanted > product.on_hand;

                                return (
                                    <TableRow key={index}>
                                        <TableCell>
                                            <OptionSelect
                                                className="w-full min-w-56"
                                                aria-label={`Product for line ${index + 1}`}
                                                aria-invalid={
                                                    lineError(
                                                        index,
                                                        'product_id',
                                                    )
                                                        ? true
                                                        : undefined
                                                }
                                                autoFocus={
                                                    index === 0 && !editing
                                                }
                                                value={
                                                    line.product_id === null
                                                        ? ''
                                                        : String(
                                                              line.product_id,
                                                          )
                                                }
                                                options={productOptions}
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
                                                    {lineError(
                                                        index,
                                                        'product_id',
                                                    )}
                                                </p>
                                            )}
                                            {short && (
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1 text-destructive"
                                                >
                                                    only {product?.on_hand} in
                                                    stock
                                                </Badge>
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
                                                        unit_price: value,
                                                    })
                                                }
                                                onCurrencyChange={(currency) =>
                                                    updateLine(index, {
                                                        unit_price_currency:
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
            </div>

            <FieldGroup>
                <Field orientation="horizontal">
                    <Checkbox
                        id="paid_in_full"
                        checked={form.data.paid_in_full}
                        onCheckedChange={(checked) =>
                            form.setData('paid_in_full', checked === true)
                        }
                    />
                    <FieldLabel htmlFor="paid_in_full" className="font-normal">
                        Paid in full
                    </FieldLabel>
                </Field>

                {/* Only asked for when it is not: the amount handed over is what
                    turns the rest of the invoice into the customer's loan. */}
                {!form.data.paid_in_full && (
                    <FormField
                        label="Paid now"
                        error={form.errors.amount_paid}
                        description="Leave at nothing to put the whole invoice on their account."
                    >
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.amount_paid}
                                currency={form.data.amount_paid_currency}
                                onChange={(value) =>
                                    form.setData('amount_paid', value)
                                }
                                onCurrencyChange={(currency) =>
                                    form.setData(
                                        'amount_paid_currency',
                                        currency,
                                    )
                                }
                            />
                        )}
                    </FormField>
                )}

                <FormField label="Notes" error={form.errors.notes}>
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={2}
                            value={form.data.notes ?? ''}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    )}
                </FormField>
            </FieldGroup>

            <Separator />

            <div className="flex flex-wrap items-center justify-between gap-3 pb-2">
                <div className="flex flex-col text-sm">
                    {/* No currency in the label: `format` puts it on the figure,
                        and the figure follows whichever currency the user is
                        reading in. */}
                    <span className="text-base font-semibold">
                        Total {format(total)}
                    </span>
                    {/* What is being lent, said plainly before the sale is
                        saved rather than discovered on a statement later. */}
                    {owing !== 0 && (
                        <span className="text-muted-foreground">
                            {owing > 0
                                ? `${format(owing)} on account, owed once the customer has the goods.`
                                : `${format(-owing)} more than the invoice comes to.`}
                        </span>
                    )}
                </div>

                <div className="flex gap-2">
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Record sale'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
