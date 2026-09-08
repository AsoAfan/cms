import type { MinorUnits } from '@/lib/money';

/** An order held up by a product that is not on the shelf. */
export type WaitingSale = {
    id: number;
    number: string;
};

/**
 * One product the business owes — mirrors a row from App\Queries\GoodsOwedQuery
 * with the orders waiting on it attached.
 *
 * `quantity` is what every waiting order asks for between them, so `short` is
 * what has to be bought once, not once per invoice.
 */
export type GoodsOwedRow = {
    product_id: number;
    product: string;
    quantity: number;
    on_hand: number;
    short: number;
    /** Minor units, at cost — what buying it in will take. */
    value: MinorUnits;
    sales: WaitingSale[];
};

/** What a customer still owes on their delivered invoices. */
export type CustomerLoanRow = {
    id: number;
    name: string;
    /** Minor units. Negative when they are in credit. */
    balance: MinorUnits;
};

/** The two sides of the loans screen — App\Http\Controllers\LoanController. */
export type LoansPage = {
    goods: {
        rows: GoodsOwedRow[];
        value: MinorUnits;
        items: number;
    };
    customers: {
        rows: CustomerLoanRow[];
        total: MinorUnits;
    };
};
