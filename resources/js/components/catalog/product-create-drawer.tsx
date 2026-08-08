import { useForm } from '@inertiajs/react';

import {
    ProductFields,
    emptyProductForm,
} from '@/components/catalog/product-fields';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrency } from '@/hooks/use-currency';
import { store } from '@/routes/products';
import type { ProductFormData } from '@/types/catalog';

export type ProductCreateDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Adding a product rises from the bottom of the catalogue it is being added
 * to, rather than replacing it with a page. The list stays visible behind, so
 * it is obvious whether the thing being typed is already there.
 */
export function ProductCreateDrawer({
    open,
    onOpenChange,
}: ProductCreateDrawerProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[90svh] overflow-y-auto rounded-t-xl"
            >
                {/* Remounted per opening, so a cancelled entry is never
                    waiting there the next time. */}
                {open && <CreateForm onDone={() => onOpenChange(false)} />}
            </SheetContent>
        </Sheet>
    );
}

function CreateForm({ onDone }: { onDone: () => void }) {
    const { base } = useCurrency();
    const form = useForm<ProductFormData>(emptyProductForm(base));

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form onSubmit={submit} className="mx-auto w-full max-w-2xl">
            <SheetHeader className="px-0">
                <SheetTitle>New product</SheetTitle>
                <SheetDescription>
                    One row per thing you actually count on the shelf.
                </SheetDescription>
            </SheetHeader>

            <div className="py-4">
                <ProductFields
                    data={form.data}
                    errors={form.errors}
                    onChange={(key, value) => form.setData(key, value)}
                    autoFocus
                />
            </div>

            <div className="flex justify-end gap-2 pb-2">
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Add product
                </Button>
            </div>
        </form>
    );
}
