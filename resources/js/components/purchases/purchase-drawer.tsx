import { PurchaseForm } from '@/components/purchases/purchase-form';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import type { BankOption } from '@/types/banks';
import type {
    AllocationMethodOption,
    PaymentMethodOption,
    ProductOption,
    PurchaseDetail,
    PurchaseLineSeed,
    PurchaseStatusOption,
} from '@/types/purchasing';

export type PurchaseDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    statuses: PurchaseStatusOption[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    /** Present when editing; absent when writing a new invoice. */
    purchase?: PurchaseDetail;
    nextNumber?: string;
    /** Lines a new invoice opens with. Ignored when editing. */
    prefill?: PurchaseLineSeed[];
};

/**
 * An invoice is written in a drawer over the screen it was opened from — the
 * list stays behind it on the way in, the invoice itself on the way back.
 *
 * The form is mounted only while the drawer is open, so each opening starts
 * from stored values rather than from whatever was abandoned last time.
 */
export function PurchaseDrawer({
    open,
    onOpenChange,
    products,
    allocationMethods,
    statuses,
    paymentMethods,
    banks,
    purchase,
    nextNumber,
    prefill,
}: PurchaseDrawerProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[92svh] overflow-y-auto rounded-t-xl"
            >
                {open && (
                    <PurchaseForm
                        products={products}
                        allocationMethods={allocationMethods}
                        statuses={statuses}
                        paymentMethods={paymentMethods}
                        banks={banks}
                        purchase={purchase}
                        nextNumber={nextNumber}
                        prefill={prefill}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
