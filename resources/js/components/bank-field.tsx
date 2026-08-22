import { Link } from '@inertiajs/react';

import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import banks from '@/routes/settings/banks';
import type { BankOption } from '@/types/banks';
import { NO_BANK } from '@/types/banks';
import type { PaymentMethodOption } from '@/types/documents';

/**
 * Whether the chosen method moves money through an account.
 *
 * Read off the option the server sent rather than compared against 'cash' here:
 * `PaymentMethod::usesBank()` is the one place that decides, and a second copy
 * of the rule in the frontend is a second place for it to drift.
 */
export function usesBank(
    methods: PaymentMethodOption[],
    method: string,
): boolean {
    return (
        methods.find((option) => option.value === method)?.uses_bank ?? false
    );
}

/**
 * Which account the money moved through.
 *
 * Renders nothing at all on cash — the field is not merely disabled, because a
 * bank against a cash payment is refused by the server and a control that can
 * only produce an error is worse than no control. Whoever owns the form must
 * clear the value when switching to a method that does not use one; `onChange`
 * with `NO_BANK` is what to send.
 */
export function BankField({
    banks: list,
    methods,
    method,
    value,
    error,
    onChange,
}: {
    banks: BankOption[];
    methods: PaymentMethodOption[];
    /** The payment method currently chosen on the form. */
    method: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    if (!usesBank(methods, method)) {
        return null;
    }

    if (list.length === 0) {
        return (
            <FormField label="Bank" error={error}>
                <p className="text-sm text-muted-foreground">
                    No banks yet.{' '}
                    <Link
                        href={banks.index.url()}
                        className="underline underline-offset-4"
                    >
                        Add one in Settings
                    </Link>{' '}
                    to record a card or transfer.
                </p>
            </FormField>
        );
    }

    return (
        <FormField
            label="Bank"
            error={error}
            description="Which account the money moved through."
        >
            {(control) => (
                <OptionSelect
                    {...control}
                    className="w-full"
                    value={value}
                    options={list.map((bank) => ({
                        value: String(bank.id),
                        label: bank.name,
                    }))}
                    onChange={onChange}
                    placeholder="Pick a bank"
                />
            )}
        </FormField>
    );
}

/**
 * What the bank field should hold after the payment method changes.
 *
 * Switching card → cash must clear it: the server refuses a bank on cash, and a
 * value left behind from a method nobody chose any more would fail a form the
 * user cannot see anything wrong with.
 */
export function bankAfterMethodChange(
    methods: PaymentMethodOption[],
    method: string,
    current: string,
): string {
    return usesBank(methods, method) ? current : NO_BANK;
}
