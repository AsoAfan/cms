export type ExpenseCategoryRow = {
    id: number;
    name: string;
    expenses_count?: number;
};

export type ExpenseRow = {
    id: number;
    category: string;
    category_id: number;
    /** Minor units. */
    amount: number;
    spent_on: string;
    payment_method: string;
    payment_method_label: string;
    reference: string | null;
    notes: string | null;
};

export type PaymentMethodOption = { value: string; label: string };
