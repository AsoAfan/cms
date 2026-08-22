import type {
    DocumentStatus,
    DocumentStatusOption,
    PaymentMethodOption,
} from '@/types/documents';

export type SaleStatus = DocumentStatus;
export type SaleStatusOption = DocumentStatusOption;

export type SaleListRow = {
    id: number;
    number: string;
    customer: string;
    customer_id: number;
    sold_on: string;
    status: SaleStatus;
    payment_method: string;
    /** Which account took the money. Null on cash. */
    bank: string | null;
    lines_count: number;
    /** Minor units. */
    total: number;
    /** Still on the customer's loan. Zero until the sale is delivered. */
    outstanding: number;
};

export type { PaymentMethodOption };

export type SellableProduct = {
    id: number;
    name: string;
    /** A base-currency decimal string, prefilled onto a new line. */
    selling_price: string;
    /** Derived from the ledger, so the till can warn before overselling. */
    on_hand: number;
};

/**
 * Decimal strings so inputs round-trip exactly, each amount paired with the
 * currency it is being typed in. The server converts to the base currency.
 */
export type SaleLineForm = {
    product_id: number | null;
    quantity: string;
    unit_price: string;
    unit_price_currency: string;
    discount: string;
    discount_currency: string;
};

export type SaleFormData = {
    /** Every sale names a buyer; counter trade is the walk-in customer's. */
    customer_id: number | null;
    sold_on: string;
    status: SaleStatus;
    payment_method: string;
    /**
     * Which account took the money. Empty on cash — a select cannot hold null,
     * and the server reads an empty string as no bank.
     */
    bank_id: string;
    /**
     * The ordinary case. When true the server takes the amount from the lines,
     * so the stored figure is the invoice exactly and nothing is converted on
     * the client.
     */
    paid_in_full: boolean;
    /**
     * What they handed over now, when it is not the lot. Anything short of the
     * total is their loan against this invoice.
     */
    amount_paid: string;
    amount_paid_currency: string;
    /** What the customer handed over, and the default for every amount on it. */
    currency: string;
    notes: string | null;
    lines: SaleLineForm[];
};

export type SaleDetailLine = {
    id: number;
    product_id: number;
    product: string;
    quantity: number;
    /** Minor units, for display. */
    unit_price: number;
    discount: number;
    net_total: number;
    /** Base-currency decimal strings, for reopening the form. */
    unit_price_decimal: string;
    discount_decimal: string;
};

export type SaleDetail = {
    id: number;
    number: string;
    customer: string;
    customer_id: number;
    sold_on: string;
    status: SaleStatus;
    /** The enum value, which the drawer's select holds. */
    payment_method: string;
    /** The same thing said in words, for the invoice itself. */
    payment_method_label: string;
    /** Which account took the money. Null on cash. */
    bank: string | null;
    /** Empty on cash — a select cannot hold null. */
    bank_id: string;
    /** Handed over at the time of sale. Minor units, then decimal for the form. */
    amount_paid: number;
    amount_paid_decimal: string;
    /** That, plus every payment since applied to this invoice. */
    paid_to_date: number;
    /** What is left on the loan. Zero until delivered — nothing is owed before. */
    outstanding: number;
    delivered: boolean;
    /** What it was paid in, and the rate it was converted at. */
    currency: string;
    exchange_rate: string;
    notes: string | null;
    /** When the goods left the ledger, or null while they have not. */
    committed_at: string | null;
    total: number;
    total_quantity: number;
    cost_of_goods_sold: number;
    gross_profit: number;
    lines: SaleDetailLine[];
    /** Stored amounts are in this currency, so the form reopens in it. */
    base_currency: string;
};
