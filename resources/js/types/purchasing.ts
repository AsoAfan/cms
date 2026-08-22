import type { DocumentStatus, DocumentStatusOption } from '@/types/documents';

export type PurchaseStatus = DocumentStatus;
export type PurchaseStatusOption = DocumentStatusOption;

export type PurchaseListRow = {
    id: number;
    number: string;
    invoiced_on: string;
    status: PurchaseStatus;
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
    invoiced_on: string;
    status: PurchaseStatus;
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
