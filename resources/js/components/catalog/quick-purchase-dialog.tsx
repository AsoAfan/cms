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
import { purchase } from '@/routes/products';
import type {
    ProductListRow,
    QuickPurchaseForm,
    SupplierOption,
} from '@/types/catalog';

/** Stands in for "nobody" — a Select cannot hold an empty value. */
const NO_SUPPLIER = 'none';

export type QuickPurchaseDialogProps = {
    product: ProductListRow | null;
    suppliers: SupplierOption[];
    onOpenChange: (open: boolean) => void;
};

/**
 * Buying one product, in one dialog.
 *
 * It writes a real posted invoice rather than a shortcut, so the stock arrives
 * costed the same way a typed-up supplier invoice would. That is why the date
 * is here: goods get entered days after they land, and the ledger should say
 * when they actually arrived.
 */
export function QuickPurchaseDialog({
    product,
    suppliers,
    onOpenChange,
}: QuickPurchaseDialogProps) {
    return (
        <Dialog open={product !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                {product !== null && (
                    <PurchaseForm
                        key={product.id}
                        product={product}
                        suppliers={suppliers}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PurchaseForm({
    product,
    suppliers,
    onDone,
}: {
    product: ProductListRow;
    suppliers: SupplierOption[];
    onDone: () => void;
}) {
    const { base } = useCurrency();
    const toBase = useToBase();

    const form = useForm<QuickPurchaseForm>({
        // Optional, and empty unless there is one obvious answer: with a single
        // supplier on file, pre-selecting it records more than it costs.
        supplier_id: suppliers.length === 1 ? String(suppliers[0].id) : '',
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
                <DialogTitle className="pr-8">Buy {product.name}</DialogTitle>
                <DialogDescription>
                    Records a posted purchase and puts the stock on the shelf.
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

                {/* Last, and optional: what arrived and what it cost is the
                    purchase. Who it came from is filing. */}
                <FormField label="Supplier" error={form.errors.supplier_id}>
                    {(control) => (
                        <Select
                            value={
                                form.data.supplier_id === ''
                                    ? NO_SUPPLIER
                                    : form.data.supplier_id
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'supplier_id',
                                    value === NO_SUPPLIER ? '' : String(value),
                                )
                            }
                        >
                            <SelectTrigger {...control} className="w-full">
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
                    Buy
                </Button>
            </DialogFooter>
        </form>
    );
}
