export type PurchaseStatus = 'draft' | 'posted';

export type PurchaseListRow = {
    id: number;
    number: string;
    supplier: string;
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
    code: string;
    default_cost_price: string | null;
};

export type AllocationMethodOption = {
    value: string;
    label: string;
    description: string;
};

/** Decimal strings on the form so inputs round-trip exactly. */
export type PurchaseLineForm = {
    product_id: number | null;
    quantity: string;
    unit_cost: string;
    discount: string;
};

export type AdditionalCostForm = {
    label: string;
    amount: string;
    allocation_method: string;
};

export type PurchaseFormData = {
    id?: number;
    number?: string;
    supplier_id: number | null;
    invoiced_on: string;
    notes: string | null;
    lines: PurchaseLineForm[];
    additional_costs: AdditionalCostForm[];
};

export type PurchaseDetailLine = {
    id: number;
    product: string;
    code: string;
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
    supplier: string;
    invoiced_on: string;
    status: PurchaseStatus;
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
