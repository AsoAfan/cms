import type { MinorUnits } from '@/lib/money';

export type ProductListRow = {
    id: number;
    name: string;
    description: string | null;
    /** Base-currency minor units. Both are required on a product. */
    cost_price: MinorUnits;
    selling_price: MinorUnits;
    /** On hand, summed from the stock ledger rather than stored. */
    quantity: number;
};

/**
 * Decimal strings so the inputs round-trip exactly, each with the currency it
 * is being typed in. The server converts to the base currency on the way in.
 */
export type ProductFormData = {
    name: string;
    description: string;
    cost_price: string;
    cost_price_currency: string;
    selling_price: string;
    selling_price_currency: string;
};

/** The one-line purchase recorded from the catalogue. */
export type QuickPurchaseForm = {
    quantity: string;
    unit_cost: string;
    unit_cost_currency: string;
    currency: string;
    invoiced_on: string;
};

/** The one-line sale recorded from the catalogue. */
export type QuickSaleForm = {
    quantity: string;
    unit_price: string;
    unit_price_currency: string;
    currency: string;
    payment_method: string;
    /** Which account took the money. Empty on cash. */
    bank_id: string;
    sold_on: string;
    /** Who bought it. Walk-in unless somebody is named. */
    customer_id: number | null;
};
