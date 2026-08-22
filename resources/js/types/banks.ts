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

/** A bank on the settings screen, with how much history is behind it. */
export type BankRow = BankOption & {
    account_number: string | null;
    notes: string | null;
    sales_count: number;
    expenses_count: number;
    payments_count: number;
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
