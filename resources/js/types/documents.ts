/**
 * Purchases and sales run through the same three states, so the badge, the
 * picker and the stepper are written once and used by both.
 *
 * They mean slightly different things on each side — a purchase on its way is
 * the supplier's van, a sale on its way is ours — and, importantly, they move
 * stock at different points: a purchase at `proceed`, a sale at `on_the_way`.
 * The server owns that rule; the frontend only shows where a document is.
 */
export type DocumentStatus = 'ordered' | 'on_the_way' | 'proceed';

export type DocumentStatusOption = {
    value: DocumentStatus;
    label: string;
    /** The one line under the label, straight from the enum. */
    description: string;
};

/**
 * How a document was paid for.
 *
 * `uses_bank` mirrors `PaymentMethod::usesBank()` — the server decides which
 * methods move through an account, and a form asks for a bank on exactly those
 * rather than hardcoding which ones they are.
 */
export type PaymentMethodOption = {
    value: string;
    label: string;
    uses_bank: boolean;
};
