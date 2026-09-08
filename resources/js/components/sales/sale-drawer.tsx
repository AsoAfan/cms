import { SaleForm } from '@/components/sales/sale-form';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import type { BankOption } from '@/types/banks';
import type { SaleCustomer } from '@/types/customers';
import type {
    PaymentMethodOption,
    SaleDetail,
    SaleStatusOption,
    SellableProduct,
} from '@/types/sales';

export type SaleDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    products: SellableProduct[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    statuses: SaleStatusOption[];
    customers: SaleCustomer[];
    /** Present when editing; absent when ringing up a new sale. */
    sale?: SaleDetail;
    nextNumber?: string;
};

/**
 * A sale is rung up in a drawer over the screen it was opened from — the list
 * stays behind it on the way in, the invoice itself on the way back.
 *
 * The form is mounted only while the drawer is open, so each opening starts
 * from stored values rather than from whatever was abandoned last time.
 */
export function SaleDrawer({
    open,
    onOpenChange,
    products,
    paymentMethods,
    banks,
    statuses,
    customers,
    sale,
    nextNumber,
}: SaleDrawerProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[92svh] overflow-y-auto rounded-t-xl"
            >
                {open && (
                    <SaleForm
                        products={products}
                        paymentMethods={paymentMethods}
                        banks={banks}
                        statuses={statuses}
                        customers={customers}
                        sale={sale}
                        nextNumber={nextNumber}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
