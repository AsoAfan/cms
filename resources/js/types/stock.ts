export type StockRow = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    /** Derived from the ledger, not stored. */
    on_hand: number;
    /** Minor units, at cost. */
    value: number;
    /** Decimal string, used to prefill the cost when adding stock. */
    default_cost_price: string | null;
};
