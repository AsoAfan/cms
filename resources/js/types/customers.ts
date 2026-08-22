import type { MinorUnits } from '@/lib/money';

export type CustomerListRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    /** What they still owe, derived from their delivered sales and payments. */
    balance: MinorUnits;
};

export type CustomerFormData = {
    id?: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
};

/** A customer as the sale screen picks them. */
export type SaleCustomer = {
    id: number;
    name: string;
};

/** One invoice on a statement. Mirrors App\Queries\CustomerBalanceQuery. */
export type StatementSale = {
    id: number;
    number: string;
    sold_on: string;
    status: string;
    status_label: string;
    /** Nothing is owed until the goods are the customer's. */
    delivered: boolean;
    total: MinorUnits;
    paid: MinorUnits;
    outstanding: MinorUnits;
};

export type PaymentAllocation = {
    sale_id: number;
    number: string;
    amount: MinorUnits;
};

export type StatementPayment = {
    id: number;
    received_on: string;
    amount: MinorUnits;
    payment_method: string;
    /** Which account it came into. Null on cash. */
    bank: string | null;
    currency: string;
    exchange_rate: string;
    notes: string | null;
    /** Which invoices this payment settled, and by how much. */
    allocations: PaymentAllocation[];
};

export type CustomerStatement = {
    balance: MinorUnits;
    invoiced: MinorUnits;
    paid: MinorUnits;
    sales: StatementSale[];
    payments: StatementPayment[];
};

/** An invoice a payment can be applied to. */
export type OpenSale = {
    id: number;
    number: string;
    sold_on: string;
    total: MinorUnits;
    paid: MinorUnits;
    outstanding: MinorUnits;
};

/**
 * Decimal strings so the inputs round-trip exactly, each amount paired with the
 * currency it is being typed in. The server converts to the base currency.
 */
export type PaymentAllocationForm = {
    sale_id: number;
    amount: string;
    amount_currency: string;
};

export type CustomerPaymentForm = {
    amount: string;
    amount_currency: string;
    currency: string;
    received_on: string;
    payment_method: string;
    /** Which account it came into. Empty on cash. */
    bank_id: string;
    notes: string | null;
    allocations: PaymentAllocationForm[];
};
