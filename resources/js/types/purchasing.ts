import type { BankOption } from '@/types/banks';
import type {
    DocumentStatus,
    DocumentStatusOption,
    PaymentMethodOption,
} from '@/types/documents';

export type PurchaseStatus = DocumentStatus;
export type PurchaseStatusOption = DocumentStatusOption;

export type { PaymentMethodOption };

export type PurchaseListRow = {
    id: number;
    number: string;
    invoiced_on: string;
    status: PurchaseStatus;
    payment_method: string;
    /** Which account paid for it. Null on cash. */
    bank: string | null;
    lines_count: number;
    /** Minor units. */
    total: number;
};

export type ProductOption = {
    id: number;
    name: string;
    /** A base-currency decimal string, prefilled onto a new line. */
    cost_price: string;
};

/**
 * A line a new invoice opens with, from a screen that already knows what has to
 * be bought — the loans list, filling in the order it is telling you to place.
 *
 * Only the product and how many: the cost comes off the catalogue when the form
 * builds the line, exactly as picking the product by hand would fill it in.
 */
export type PurchaseLineSeed = {
    product_id: number;
    quantity: number;
};

export type AllocationMethodOption = {
    value: string;
    label: string;
    description: string;
};

/**
 * Decimal strings on the form so inputs round-trip exactly, each amount paired
 * with the currency it is being typed in. The server converts to the base
 * currency; nothing here ever sends a converted figure.
 */
export type PurchaseLineForm = {
    product_id: number | null;
    quantity: string;
    unit_cost: string;
    unit_cost_currency: string;
    discount: string;
    discount_currency: string;
};

export type AdditionalCostForm = {
    label: string;
    amount: string;
    amount_currency: string;
    allocation_method: string;
};

export type PurchaseFormData = {
    /**
     * What this invoice is filed under. Prefilled with the next in sequence and
     * editable; left empty, the server keeps the number the invoice already has.
     */
    number: string;
    invoiced_on: string;
    status: PurchaseStatus;
    payment_method: string;
    /**
     * Which account paid for it. Empty on cash — a select cannot hold null,
     * and the server reads an empty string as no bank.
     */
    bank_id: string;
    /** What the invoice was written in, and the default for every amount on it. */
    currency: string;
    notes: string;
    lines: PurchaseLineForm[];
    additional_costs: AdditionalCostForm[];
};

export type PurchaseDetailLine = {
    id: number;
    product_id: number;
    product: string;
    quantity: number;
    /** Minor units, for display. */
    unit_cost: number;
    discount: number;
    net_total: number;
    /** Base-currency decimal strings, for reopening the form. */
    unit_cost_decimal: string;
    discount_decimal: string;
};

export type PurchaseAdditionalCostDetail = {
    label: string;
    /** Minor units, for display. */
    amount: number;
    /** A base-currency decimal string, for reopening the form. */
    amount_decimal: string;
    allocation_method: string;
    allocation_label: string;
};

export type PurchaseDetail = {
    id: number;
    number: string;
    invoiced_on: string;
    status: PurchaseStatus;
    payment_method: string;
    payment_method_label: string;
    /** The account it was paid out of, named. Null on cash. */
    bank: string | null;
    /** The same account as the drawer's select holds it: empty means none. */
    bank_id: string;
    /** What it was invoiced in, and the rate it was converted at. */
    currency: string;
    exchange_rate: string;
    notes: string | null;
    /** When the goods reached the ledger, or null while they have not. */
    committed_at: string | null;
    goods_total: number;
    additional_costs_total: number;
    total: number;
    total_quantity: number;
    lines: PurchaseDetailLine[];
    additional_costs: PurchaseAdditionalCostDetail[];
    /** Stored amounts are in this currency, so the form reopens in it. */
    base_currency: string;
};

/**
 * What a screen needs to open the purchase drawer for a NEW invoice — mirrors
 * App\Http\Concerns\InteractsWithPurchaseForm::newPurchaseOptions().
 *
 * The purchases list is not the only screen that writes one: the loans list
 * opens the drawer prefilled with the order it is telling you to place, and
 * both are served the same options so the drawer is one form everywhere.
 */
export type PurchaseFormOptions = {
    products: ProductOption[];
    allocationMethods: AllocationMethodOption[];
    statuses: PurchaseStatusOption[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    nextNumber: string;
};
