export type SaleStatus = 'draft' | 'posted';

export type SaleListRow = {
    id: number;
    number: string;
    sold_on: string;
    status: SaleStatus;
    payment_method: string;
    lines_count: number;
    /** Minor units. */
    total: number;
};

export type PaymentMethodOption = { value: string; label: string };

export type SellableProduct = {
    id: number;
    name: string;
    code: string;
    default_selling_price: string | null;
    /** Derived from the ledger, so the till can warn before overselling. */
    on_hand: number;
};

/** Decimal strings so inputs round-trip exactly. */
export type SaleLineForm = {
    product_id: number | null;
    quantity: string;
    unit_price: string;
    discount: string;
};

export type SaleFormData = {
    id?: number;
    number?: string;
    sold_on: string;
    payment_method: string;
    notes: string | null;
    lines: SaleLineForm[];
};

export type SaleDetailLine = {
    id: number;
    product: string;
    code: string;
    quantity: number;
    unit_price: number;
    discount: number;
    net_total: number;
    cost_of_goods_sold: number;
    gross_profit: number;
};

export type SaleDetail = {
    id: number;
    number: string;
    sold_on: string;
    status: SaleStatus;
    payment_method: string;
    notes: string | null;
    posted_at: string | null;
    total: number;
    cost_of_goods_sold: number;
    gross_profit: number;
    lines: SaleDetailLine[];
};
