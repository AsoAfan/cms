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
    id?: number;
    number?: string;
    sold_on: string;
    payment_method: string;
    /** What the customer handed over, and the default for every amount on it. */
    currency: string;
    notes: string | null;
    lines: SaleLineForm[];
};

export type SaleDetailLine = {
    id: number;
    product: string;
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
    /** What it was paid in, and the rate it was converted at. */
    currency: string;
    exchange_rate: string;
    notes: string | null;
    posted_at: string | null;
    total: number;
    cost_of_goods_sold: number;
    gross_profit: number;
    lines: SaleDetailLine[];
};
