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
import { useCurrency, useToBase } from '@/hooks/use-currency';
import { todayIso } from '@/lib/date';
import { toDecimalString } from '@/lib/money';
import { purchase } from '@/routes/products';
import type { ProductListRow, QuickPurchaseForm } from '@/types/catalog';

export type QuickPurchaseDialogProps = {
    product: ProductListRow | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * Ordering one product, in one dialog.
 *
 * It writes a real invoice rather than a shortcut — at `ordered`, so it moves
 * no stock. The goods arrive when the invoice is marked Proceed, which is on
 * the invoice's own screen. The dialog says so, because somebody who expected
 * the shelf to go up needs to know where that now happens.
 */
export function QuickPurchaseDialog({
    product,
    onOpenChange,
}: QuickPurchaseDialogProps) {
    return (
        <Dialog open={product !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                {product !== null && (
                    <PurchaseForm
                        key={product.id}
                        product={product}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PurchaseForm({
    product,
    onDone,
}: {
    product: ProductListRow;
    onDone: () => void;
}) {
    const { base } = useCurrency();
    const toBase = useToBase();

    const form = useForm<QuickPurchaseForm>({
        quantity: '1',
        unit_cost: toDecimalString(product.cost_price),
        unit_cost_currency: base,
        currency: base,
        invoiced_on: todayIso(),
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(purchase.url(product.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    const quantity = Number(form.data.quantity);
    // Totalled in the base currency, which is the only currency a total means
    // anything in once a field can be typed in another.
    const unitCost = toBase(form.data.unit_cost, form.data.unit_cost_currency);
    const total =
        Number.isInteger(quantity) && quantity > 0 ? quantity * unitCost : null;

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">Order {product.name}</DialogTitle>
                <DialogDescription>
                    Writes a purchase at Ordered. The stock arrives when you
                    mark the invoice Proceed.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Quantity" error={form.errors.quantity}>
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

                    <FormField label="Unit cost" error={form.errors.unit_cost}>
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.unit_cost}
                                currency={form.data.unit_cost_currency}
                                onChange={(value) =>
                                    form.setData('unit_cost', value)
                                }
                                onCurrencyChange={(next) =>
                                    // The cost is the only amount here, so what
                                    // it is typed in IS what the invoice was
                                    // written in.
                                    form.setData((data) => ({
                                        ...data,
                                        unit_cost_currency: next,
                                        currency: next,
                                    }))
                                }
                            />
                        )}
                    </FormField>
                </div>

                <FormField label="Invoice date" error={form.errors.invoiced_on}>
                    {(control) => (
                        <Input
                            {...control}
                            type="date"
                            value={form.data.invoiced_on}
                            onChange={(event) =>
                                form.setData('invoiced_on', event.target.value)
                            }
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
