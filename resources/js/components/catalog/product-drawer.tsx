import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

import { ProductFields } from '@/components/catalog/product-fields';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrency } from '@/hooks/use-currency';
import { toDecimalString } from '@/lib/money';
import { destroy, update } from '@/routes/products';
import type { ProductFormData, ProductListRow } from '@/types/catalog';

export type ProductDrawerProps = {
    /** The row being edited, or null when the drawer is closed. */
    product: ProductListRow | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * A product opens beside the catalogue, not instead of it — the list keeps its
 * scroll position and its place in the day's work.
 */
export function ProductDrawer({ product, onOpenChange }: ProductDrawerProps) {
    return (
        <Sheet open={product !== null} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto sm:max-w-md"
            >
                {/* Keyed on the product so opening a different row starts from
                    that row's values instead of the last one's. */}
                {product !== null && (
                    <EditForm
                        key={product.id}
                        product={product}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}

function EditForm({
    product,
    onDone,
}: {
    product: ProductListRow;
    onDone: () => void;
}) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { base } = useCurrency();

    // Stored figures are base currency, so the fields open in it whatever the
    // price was originally typed in.
    const form = useForm<ProductFormData>({
        name: product.name,
        description: product.description ?? '',
        cost_price: toDecimalString(product.cost_price),
        cost_price_currency: base,
        selling_price: toDecimalString(product.selling_price),
        selling_price_currency: base,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.put(update.url(product.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form onSubmit={submit} className="flex min-h-full flex-col">
            <SheetHeader>
                <SheetTitle className="pr-8">{product.name}</SheetTitle>
                <SheetDescription>
                    {product.quantity === 0
                        ? 'None in stock.'
                        : `${product.quantity} in stock.`}{' '}
                    Stock moves by buying and selling.
                </SheetDescription>
            </SheetHeader>

            <div className="flex-1 px-4 py-2">
                <ProductFields
                    data={form.data}
                    errors={form.errors}
                    onChange={(key, value) => form.setData(key, value)}
                />
            </div>

            <div className="flex items-center justify-between gap-2 border-t p-4">
                {confirmingDelete ? (
                    <>
                        <span className="text-sm text-muted-foreground">
                            Delete this product?
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setConfirmingDelete(false)}
                            >
                                Keep
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    form.delete(destroy.url(product.id), {
                                        preserveScroll: true,
                                        onSuccess: onDone,
                                    })
                                }
                            >
                                Delete
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Delete ${product.name}`}
                            onClick={() => setConfirmingDelete(true)}
                        >
                            <Trash2 />
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
