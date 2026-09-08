import { useForm } from '@inertiajs/react';

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
import { useCurrency, useToBase } from '@/hooks/use-currency';
import { todayIso } from '@/lib/date';
import { toDecimalString } from '@/lib/money';
import { sell } from '@/routes/products';
import type { BankOption } from '@/types/banks';
import { NO_BANK } from '@/types/banks';
import type { ProductListRow, QuickSaleForm } from '@/types/catalog';
import type { SaleCustomer } from '@/types/customers';
import type { PaymentMethodOption } from '@/types/sales';

export type QuickSaleDialogProps = {
    product: ProductListRow | null;
    paymentMethods: PaymentMethodOption[];
    /** The accounts a card or transfer can be taken into. */
    banks: BankOption[];
    /** Walk-in first, so counter trade is already selected. */
    customers: SaleCustomer[];
    onOpenChange: (open: boolean) => void;
};

/**
 * Selling one product, in one dialog.
 *
 * Writes a real sale rather than a shortcut — at `ordered`, so it moves no
 * stock and records nothing as paid. The goods leave when the sale is marked
 * On the way, and what the customer handed over is recorded on the sale, both
 * on the sale's own screen. The dialog says so.
 */
export function QuickSaleDialog({
    product,
    paymentMethods,
    banks,
    customers,
    onOpenChange,
}: QuickSaleDialogProps) {
    return (
        <Dialog open={product !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                {product !== null && (
                    <SaleForm
                        key={product.id}
                        product={product}
                        paymentMethods={paymentMethods}
                        banks={banks}
                        customers={customers}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function SaleForm({
    product,
    paymentMethods,
    banks,
    customers,
    onDone,
}: {
    product: ProductListRow;
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    customers: SaleCustomer[];
    onDone: () => void;
}) {
    const { base } = useCurrency();
    const toBase = useToBase();

    const form = useForm<QuickSaleForm>({
        quantity: '1',
        unit_price: toDecimalString(product.selling_price),
        unit_price_currency: base,
        currency: base,
        payment_method: paymentMethods[0]?.value ?? '',
        bank_id: NO_BANK,
        sold_on: todayIso(),
        customer_id: customers[0]?.id ?? null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(sell.url(product.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    const quantity = Number(form.data.quantity);
    const wanted = Number.isInteger(quantity) && quantity > 0 ? quantity : null;
    // Totalled in the base currency, which is the only currency a total means
    // anything in once a field can be typed in another.
    const unitPrice = toBase(
        form.data.unit_price,
        form.data.unit_price_currency,
    );
    const total = wanted !== null ? wanted * unitPrice : null;

    // An order for more than is on the shelf is allowed — the stock does not
    // move until the sale goes out, and by then it may be in. Worth saying,
    // not worth refusing.
    const short = wanted !== null && wanted > product.quantity;

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">Sell {product.name}</DialogTitle>
                <DialogDescription>
                    Writes a sale at Ordered. The stock leaves when you mark it
                    On the way. {product.quantity} in stock.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Quantity"
                        error={form.errors.quantity}
                        description={
                            short
                                ? `Only ${product.quantity} in stock — this orders more than you have.`
                                : undefined
                        }
                    >
                        {(control) => (
                            <Input
                                {...control}
                                inputMode="numeric"
                                className="text-right tabular-nums"
                                value={form.data.quantity}
                                autoFocus
                                onChange={(event) =>
                                    form.setData('quantity', event.target.value)
                                }
                            />
                        )}
                    </FormField>

                    <FormField
                        label="Unit price"
                        error={form.errors.unit_price}
                    >
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.unit_price}
                                currency={form.data.unit_price_currency}
                                onChange={(value) =>
                                    form.setData('unit_price', value)
                                }
                                onCurrencyChange={(next) =>
                                    // The price is the only amount here, so what
                                    // it is typed in IS what the customer paid
                                    // in.
                                    form.setData((data) => ({
                                        ...data,
                                        unit_price_currency: next,
                                        currency: next,
                                    }))
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
                                placeholder="How it was paid"
                            />
                        )}
                    </FormField>

                    <FormField label="Date" error={form.errors.sold_on}>
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

                <BankField
                    banks={banks}
                    methods={paymentMethods}
                    method={form.data.payment_method}
                    value={form.data.bank_id}
                    error={form.errors.bank_id}
                    onChange={(value) => form.setData('bank_id', value)}
                />

                {/* Nothing is recorded as paid: the sale is only an order here.
                    What was handed over is the sale screen's to ask, once the
                    goods are actually going out. */}
                <FormField label="Customer" error={form.errors.customer_id}>
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
            </FieldGroup>

            <div className="flex items-baseline justify-between border-t pt-4">
                <span className="text-sm text-muted-foreground">Total</span>
                {total === null ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <MoneyDisplay
                        amount={total}
                        className="text-lg font-semibold"
                    />
                )}
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Order
                </Button>
            </DialogFooter>
        </form>
    );
}
