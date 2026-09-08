import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { BankField, bankAfterMethodChange } from '@/components/bank-field';
import { FormField } from '@/components/form-field';
import { MoneyDisplay } from '@/components/money-display';
import { MoneyInput } from '@/components/money-input';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCurrency, useFormatMoney, useToBase } from '@/hooks/use-currency';
import { todayIso } from '@/lib/date';
import { toDecimalString } from '@/lib/money';
import type { MinorUnits } from '@/lib/money';
import { store } from '@/routes/customers/payments';
import type { BankOption } from '@/types/banks';
import { NO_BANK } from '@/types/banks';
import type {
    CustomerPaymentForm,
    OpenSale,
    PaymentAllocationForm,
} from '@/types/customers';
import type { PaymentMethodOption } from '@/types/sales';

export type RecordPaymentDialogProps = {
    customer: { id: number; name: string };
    openSales: OpenSale[];
    paymentMethods: PaymentMethodOption[];
    /** The accounts a card or transfer can come into. */
    banks: BankOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Money coming in against what a customer owes.
 *
 * A payment has to be applied to invoices in full — money against nothing in
 * particular would make the account balance and the invoice balances disagree —
 * so this fills the oldest invoices first the moment an amount is typed, which is
 * what someone paying off a tab actually means. Every row stays editable for the
 * case where they are paying one particular invoice.
 *
 * Allocations are stated in the base currency even when the payment was taken in
 * another: the outstanding figures are base-currency, so filling from them
 * converts once instead of twice and the parts add up to the whole exactly.
 */
export function RecordPaymentDialog({
    customer,
    openSales,
    paymentMethods,
    banks,
    open,
    onOpenChange,
}: RecordPaymentDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <PaymentForm
                    key={openSales.length}
                    customer={customer}
                    openSales={openSales}
                    paymentMethods={paymentMethods}
                    banks={banks}
                    onDone={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

/**
 * Spread an amount over invoices, oldest first, giving each what it still owes
 * until the money runs out.
 */
function oldestFirst(
    openSales: OpenSale[],
    amount: MinorUnits,
    currency: string,
): PaymentAllocationForm[] {
    let left = amount;

    return openSales.map((sale) => {
        const applied = Math.max(0, Math.min(left, sale.outstanding));

        left -= applied;

        return {
            sale_id: sale.id,
            amount: applied === 0 ? '' : toDecimalString(applied),
            amount_currency: currency,
        };
    });
}

function PaymentForm({
    customer,
    openSales,
    paymentMethods,
    banks,
    onDone,
}: {
    customer: { id: number; name: string };
    openSales: OpenSale[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    onDone: () => void;
}) {
    const { base } = useCurrency();
    const format = useFormatMoney();
    const toBase = useToBase();

    // Once a row has been typed into by hand, the amount box stops rewriting it.
    const [handEdited, setHandEdited] = useState(false);

    const owed = useMemo(
        () => openSales.reduce((total, sale) => total + sale.outstanding, 0),
        [openSales],
    );

    const form = useForm<CustomerPaymentForm>({
        amount: '',
        amount_currency: base,
        currency: base,
        received_on: todayIso(),
        payment_method: paymentMethods[0]?.value ?? 'cash',
        bank_id: NO_BANK,
        notes: '',
        allocations: openSales.map((sale) => ({
            sale_id: sale.id,
            amount: '',
            amount_currency: base,
        })),
    });

    const applied = form.data.allocations.reduce(
        (total, allocation) =>
            total + toBase(allocation.amount, allocation.amount_currency),
        0,
    );

    const amount = toBase(form.data.amount, form.data.amount_currency);
    const left = amount - applied;

    function changeAmount(value: string, currency = form.data.amount_currency) {
        const typed = toBase(value, currency);

        form.setData((data) => ({
            ...data,
            amount: value,
            amount_currency: currency,
            // The whole point: type what came in, and the oldest invoices are
            // settled with it. Stops as soon as a row is touched by hand.
            allocations: handEdited
                ? data.allocations
                : oldestFirst(openSales, typed, base),
        }));
    }

    function updateAllocation(
        saleId: number,
        patch: Partial<PaymentAllocationForm>,
    ) {
        setHandEdited(true);

        form.setData(
            'allocations',
            form.data.allocations.map((allocation) =>
                allocation.sale_id === saleId
                    ? { ...allocation, ...patch }
                    : allocation,
            ),
        );
    }

    function payEverything() {
        setHandEdited(false);
        changeAmount(toDecimalString(owed), base);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(store.url(customer.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    const balanced = amount > 0 && left === 0;

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">
                    Payment from {customer.name}
                </DialogTitle>
                <DialogDescription>
                    {format(owed)} owed across {openSales.length}{' '}
                    {openSales.length === 1 ? 'invoice' : 'invoices'}. Typing an
                    amount settles the oldest first.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Amount" error={form.errors.amount}>
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.amount}
                                currency={form.data.amount_currency}
                                autoFocus
                                onChange={(value) => changeAmount(value)}
                                onCurrencyChange={(currency) =>
                                    // One amount on this form, so what it is
                                    // typed in IS what the money came in as.
                                    form.setData((data) => ({
                                        ...data,
                                        amount_currency: currency,
                                        currency,
                                    }))
                                }
                            />
                        )}
                    </FormField>

                    <FormField label="Received" error={form.errors.received_on}>
                        {(control) => (
                            <Input
                                {...control}
                                type="date"
                                value={form.data.received_on}
                                onChange={(event) =>
                                    form.setData(
                                        'received_on',
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                    </FormField>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Paid by"
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

                    <BankField
                        banks={banks}
                        methods={paymentMethods}
                        method={form.data.payment_method}
                        value={form.data.bank_id}
                        error={form.errors.bank_id}
                        onChange={(value) => form.setData('bank_id', value)}
                    />

                    <FormField label="Note" error={form.errors.notes}>
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={1}
                                value={form.data.notes ?? ''}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                </div>
            </FieldGroup>

            <div className="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Invoice</TableHead>
                            <TableHead className="text-right">Owed</TableHead>
                            <TableHead className="w-40 text-right">
                                Applied
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {openSales.map((sale) => {
                            const allocation = form.data.allocations.find(
                                (row) => row.sale_id === sale.id,
                            );

                            return (
                                <TableRow key={sale.id}>
                                    <TableCell>
                                        <span className="font-mono font-medium">
                                            {sale.number}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {sale.sold_on}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyDisplay
                                            amount={sale.outstanding}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <MoneyInput
                                            aria-label={`Applied to ${sale.number}`}
                                            value={allocation?.amount ?? ''}
                                            currency={
                                                allocation?.amount_currency ??
                                                base
                                            }
                                            onChange={(value) =>
                                                updateAllocation(sale.id, {
                                                    amount: value,
                                                })
                                            }
                                            onCurrencyChange={(currency) =>
                                                updateAllocation(sale.id, {
                                                    amount_currency: currency,
                                                })
                                            }
                                        />
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {/* A payment must be applied in full, so the shortfall is on screen
                before the button is pressed rather than in an error afterwards. */}
            <div className="flex flex-wrap items-baseline justify-between gap-2 border-t pt-4 text-sm">
                <div className="flex items-center gap-3">
                    <span className="text-muted-foreground">
                        {left === 0
                            ? 'Applied in full'
                            : left > 0
                              ? 'Left to apply'
                              : 'Applied more than came in'}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={payEverything}
                    >
                        Paying it all off
                    </Button>
                </div>
                <MoneyDisplay
                    amount={left}
                    colored
                    className="text-lg font-semibold"
                />
            </div>

            {typeof form.errors.allocations === 'string' && (
                <p className="text-sm text-destructive">
                    {form.errors.allocations}
                </p>
            )}

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing || !balanced}>
                    Record payment
                </Button>
            </DialogFooter>
        </form>
    );
}
