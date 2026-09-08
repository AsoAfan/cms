import type { MinorUnits } from '@/lib/money';

/**
 * An account non-cash money moves through.
 *
 * Named on a sale, an expense or a customer repayment whenever the payment
 * method uses one — `PaymentMethodOption.uses_bank` is what says which do.
 */
export type BankOption = {
    id: number;
    name: string;
};

/**
 * A bank on the settings screen: how much history is behind it, and what it
 * holds.
 *
 * The balance is never stored — `App\Queries\BankBalanceQuery` derives it from
 * everything that moved through the account, in base-currency minor units like
 * every figure on the wire.
 */
export type BankRow = BankOption & {
    account_number: string | null;
    notes: string | null;
    sales_count: number;
    purchases_count: number;
    expenses_count: number;
    payments_count: number;
    adjustments_count: number;
    balance: MinorUnits;
};

/**
 * Money put into or taken out of an account by hand — the opening balance,
 * cash deposited, interest, a charge.
 *
 * `amount` is SIGNED: negative is money out. The screen reads the direction off
 * the sign rather than a second field that could disagree with it.
 */
export type BankAdjustmentRow = {
    id: number;
    bank_id: number;
    bank: string;
    reason: string;
    amount: MinorUnits;
    occurred_on: string;
};

/** Mirrors App\Enums\BankAdjustmentDirection. */
export type BankAdjustmentDirectionOption = {
    value: string;
    label: string;
    description: string;
};

/**
 * The amount is typed positive and the direction says which way it went; the
 * server applies the sign. See `BankAdjustmentRequest`.
 */
export type BankAdjustmentForm = {
    direction: string;
    reason: string;
    amount: string;
    amount_currency: string;
    occurred_on: string;
};

export type BankForm = {
    name: string;
    account_number: string;
    notes: string;
};

/**
 * What a form holds for the bank field.
 *
 * A select cannot hold null, so "no bank" is the empty string on the way out
 * and the server reads it as nothing — see `NamesPayingBank`.
 */
export const NO_BANK = '';
