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
    /** Movements between this account and another, both directions together. */
    transfers_count: number;
    balance: MinorUnits;
};

/**
 * One thing moved by hand, whichever kind it is — mirrors
 * `BankController::movements()`, which normalises the two into one row shape
 * the way `ActivityQuery` does for documents.
 *
 * On an `adjustment` the amount is SIGNED (negative is money out) and `detail`
 * names the account. On a `transfer` it is always positive — the money never
 * left the business — and `detail` reads "from → to".
 */
export type BankMovementRow = {
    kind: 'adjustment' | 'transfer';
    id: number;
    label: string;
    detail: string;
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
/**
 * Money moved between two of the business's own accounts. Always positive and
 * always from → to; moving it back is the same form the other way round.
 */
export type BankTransferForm = {
    from_bank_id: string;
    to_bank_id: string;
    amount: string;
    amount_currency: string;
    occurred_on: string;
    reason: string;
};

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
