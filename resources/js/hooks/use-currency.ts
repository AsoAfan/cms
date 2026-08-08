import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

import {
    convertFromBase,
    convertToBase,
    formatMoney,
    parseMoney,
    RATE_SCALE,
    toDecimalString,
} from '@/lib/money';
import type { FormatMoneyOptions, MinorUnits } from '@/lib/money';
import type { Currency, CurrencyOption } from '@/types';

/**
 * The application's currency settings, as shared by HandleInertiaRequests.
 */
export function useCurrency(): Currency {
    return usePage().props.currency;
}

/**
 * Every currency an amount may be typed in, base first.
 */
export function useCurrencyOptions(): CurrencyOption[] {
    return useCurrency().currencies;
}

/**
 * One currency's settings, falling back to the base currency for a code that is
 * no longer on offer — a form left open while a rate was removed still renders.
 */
export function useCurrencyOption(code: string): CurrencyOption {
    const { base, currencies } = useCurrency();

    return (
        currencies.find((currency) => currency.code === code) ??
        currencies.find((currency) => currency.code === base) ?? {
            code: base,
            name: base,
            symbol: base,
            fraction_digits: 2,
            rate: RATE_SCALE,
            rate_on: null,
            source: null,
        }
    );
}

/**
 * The currency the user is reading figures in, and whether that is the one they
 * are stored in.
 */
export function useDisplayCurrency(): CurrencyOption & { isBase: boolean } {
    const { base, display } = useCurrency();
    const option = useCurrencyOption(display);

    return { ...option, isBase: option.code === base };
}

/**
 * A money formatter bound to whichever currency the user is reading in.
 *
 *     const format = useFormatMoney();
 *     format(123456); // "IQD 1,235"
 *
 * The amount passed in is always base-currency minor units, exactly as the
 * server sent it. Converting for a non-base view happens here and nowhere else,
 * so every screen switches together and no figure is converted twice.
 */
export function useFormatMoney(): (
    minorUnits: MinorUnits,
    options?: FormatMoneyOptions,
) => string {
    const { locale } = useCurrency();
    const { code, rate, fraction_digits, isBase } = useDisplayCurrency();

    return useCallback(
        (minorUnits: MinorUnits, options: FormatMoneyOptions = {}) =>
            formatMoney(
                isBase ? minorUnits : convertFromBase(minorUnits, rate),
                {
                    currency: code,
                    locale,
                    fractionDigits: fraction_digits,
                    ...options,
                },
            ),
        [code, fraction_digits, isBase, locale, rate],
    );
}

/**
 * Read an amount as typed, in whatever currency the field is set to, as base
 * currency minor units.
 *
 *     const toBase = useToBase();
 *     const total = lines.reduce(
 *         (sum, line) => sum + toBase(line.unit_price, line.unit_price_currency),
 *         0,
 *     );
 *
 * Every running total on a form has to be summed this way: an invoice with a
 * dollar line and a dinar line has no meaningful total in either currency alone.
 * Blank or half-typed text reads as zero, so a total never disappears mid-entry.
 */
export function useToBase(): (value: string, currency: string) => MinorUnits {
    const { base, currencies } = useCurrency();

    return useCallback(
        (value: string, currency: string) => {
            const typed = parseMoney(value) ?? 0;

            if (currency === base) {
                return typed;
            }

            const option = currencies.find((item) => item.code === currency);

            return option === undefined
                ? typed
                : convertToBase(typed, option.rate);
        },
        [base, currencies],
    );
}

/**
 * Restate an amount as typed from one currency into another, keeping what it is
 * worth rather than the digits.
 *
 *     const restate = useRestate();
 *     restate('18.50', 'USD', 'IQD'); // "24420.00"
 *
 * This is what a field's currency dropdown does: pick dinars on a field showing
 * $18.50 and it becomes 24,420 — the same money, said differently. Swapping the
 * label and leaving the number would silently turn eighteen dollars into
 * eighteen dinars.
 *
 * Blank or half-typed text is returned untouched, so switching currency
 * mid-keystroke never replaces what somebody is still typing with a "0".
 */
export function useRestate(): (
    value: string,
    from: string,
    to: string,
) => string {
    const toBase = useToBase();
    const { base, currencies } = useCurrency();

    return useCallback(
        (value: string, from: string, to: string) => {
            if (from === to || parseMoney(value) === null) {
                return value;
            }

            const asBase = toBase(value, from);

            if (to === base) {
                return toDecimalString(asBase);
            }

            const option = currencies.find((item) => item.code === to);

            return option === undefined
                ? value
                : toDecimalString(convertFromBase(asBase, option.rate));
        },
        [base, currencies, toBase],
    );
}

/**
 * Convert a base-currency amount into a named currency, for the "≈ 24,429 IQD"
 * line under a money field. Formats in that currency regardless of what the user
 * is otherwise reading in — the point of the hint is to name both sides.
 */
export function useFormatMoneyIn(): (
    minorUnits: MinorUnits,
    currency: string,
    options?: FormatMoneyOptions,
) => string {
    const { base, locale, currencies } = useCurrency();

    return useCallback(
        (
            minorUnits: MinorUnits,
            currency: string,
            options: FormatMoneyOptions = {},
        ) => {
            const option = currencies.find((item) => item.code === currency);
            const amount =
                option === undefined || currency === base
                    ? minorUnits
                    : convertFromBase(minorUnits, option.rate);

            return formatMoney(amount, {
                currency,
                locale,
                fractionDigits: option?.fraction_digits ?? 2,
                ...options,
            });
        },
        [base, currencies, locale],
    );
}
