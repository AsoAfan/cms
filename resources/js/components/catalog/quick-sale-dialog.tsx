import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/form-field';
import { MoneyDisplay } from '@/components/money-display';
import { MoneyInput } from '@/components/money-input';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCurrency, useToBase } from '@/hooks/use-currency';
import { todayIso } from '@/lib/date';
import { toDecimalString } from '@/lib/money';
import { sell } from '@/routes/products';
import type { ProductListRow, QuickSaleForm } from '@/types/catalog';
import type { PaymentMethodOption } from '@/types/sales';

export type QuickSaleDialogProps = {
    product: ProductListRow | null;
    paymentMethods: PaymentMethodOption[];
    onOpenChange: (open: boolean) => void;
};

/**
 * Selling one product, in one dialog.
 *
 * Writes a posted sale, so the stock leaves FIFO and what it cost is recorded
 * against the batches that actually paid for it — the same path a full sale
 * takes, which is what keeps profit reporting honest.
 */
export function QuickSaleDialog({
    product,
    paymentMethods,
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
    onDone,
}: {
    product: ProductListRow;
    paymentMethods: PaymentMethodOption[];
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
        sold_on: todayIso(),
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

    // The server refuses an oversell outright; saying so while the number is
    // still being typed saves the round trip.
    const short = wanted !== null && wanted > product.quantity;

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">Sell {product.name}</DialogTitle>
                <DialogDescription>
                    {product.quantity === 0
                        ? 'Nothing in stock — buy some first.'
                        : `${product.quantity} in stock.`}
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Quantity"
                        error={
                            form.errors.quantity ??
                            (short
                                ? `Only ${product.quantity} in stock.`
                                : undefined)
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
                            <Select
                                value={form.data.payment_method}
                                onValueChange={(value) =>
                                    form.setData(
                                        'payment_method',
                                        String(value),
                                    )
                                }
                            >
                                <SelectTrigger {...control} className="w-full">
                                    <SelectValue placeholder="How it was paid" />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethods.map((method) => (
                                        <SelectItem
                                            key={method.value}
                                            value={method.value}
                                        >
                                            {method.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                <Button type="submit" disabled={form.processing || short}>
                    Sell
                </Button>
            </DialogFooter>
        </form>
    );
}
