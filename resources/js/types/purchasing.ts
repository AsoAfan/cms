export type PurchaseStatus = 'draft' | 'posted';

export type PurchaseListRow = {
    id: number;
    number: string;
    /** Null when the invoice was recorded without naming who it came from. */
    supplier: string | null;
    invoiced_on: string;
    status: PurchaseStatus;
    lines_count: number;
    /** Minor units. */
    total: number;
};

export type SupplierOption = { id: number; name: string };

export type ProductOption = {
    id: number;
    name: string;
    /** A base-currency decimal string, prefilled onto a new line. */
    cost_price: string;
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
    id?: number;
    number?: string;
    supplier_id: number | null;
    invoiced_on: string;
    /** What the invoice was written in, and the default for every amount on it. */
    currency: string;
    notes: string | null;
    lines: PurchaseLineForm[];
    additional_costs: AdditionalCostForm[];
};

export type PurchaseDetailLine = {
    id: number;
    product: string;
    quantity: number;
    unit_cost: number;
    discount: number;
    net_total: number;
    landed_total: number;
    batches: { quantity: number; unit_cost: number }[];
};

export type PurchaseDetail = {
    id: number;
    number: string;
    supplier: string | null;
    invoiced_on: string;
    status: PurchaseStatus;
    /** What it was invoiced in, and the rate it was converted at. */
    currency: string;
    exchange_rate: string;
    notes: string | null;
    posted_at: string | null;
    goods_total: number;
    additional_costs_total: number;
    total: number;
    lines: PurchaseDetailLine[];
    additional_costs: {
        label: string;
        amount: number;
        allocation_method: string;
        allocation_label: string;
    }[];
};
