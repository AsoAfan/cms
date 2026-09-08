export type ExpenseCategoryRow = {
    id: number;
    name: string;
    expenses_count?: number;
};

export type ExpenseRow = {
    id: number;
    title: string;
    category: string;
    category_id: number;
    /** Minor units. */
    amount: number;
    spent_on: string;
    payment_method: string;
    payment_method_label: string;
    /** Which account it went out of. Null on cash, and on anything predating banks. */
    bank_id: number | null;
    bank: string | null;
    notes: string | null;
};

export type { PaymentMethodOption } from '@/types/documents';
