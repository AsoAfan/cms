export type ProductListRow = {
    id: number;
    name: string;
    code: string;
    /** Minor units, or null when not priced yet. */
    default_cost_price: number | null;
    default_selling_price: number | null;
    is_active: boolean;
};

export type ProductFormData = {
    id?: number;
    name: string;
    code: string;
    description: string | null;
    /** Decimal strings so the input round-trips exactly. */
    default_cost_price: string | null;
    default_selling_price: string | null;
    is_active: boolean;
};
