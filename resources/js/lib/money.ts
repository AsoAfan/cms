/**
 * Money on the frontend is always a whole number of minor units (cents),
 * matching `App\Support\Money` on the server. Amounts arrive from Inertia as
 * plain integers — never format or compare them as major-unit floats.
 */
export type MinorUnits = number;

const SUBUNITS = 100;

/**
 * Fallbacks for the rare caller outside a React tree. Components should reach
 * for `useFormatMoney()`, which binds to the currency the server shares.
 */
const FALLBACK = {
    currency: 'USD',
    locale: 'en-US',
};

export type FormatMoneyOptions = {
    currency?: string;
    locale?: string;
    /** Drop the currency symbol — for table columns that label it in the header. */
    bare?: boolean;
    /** Force a leading `+` on positive amounts, e.g. for a profit delta. */
    signed?: boolean;
};

/**
 * Render minor units for display: `formatMoney(123456)` → `$1,234.56`.
 */
export function formatMoney(
    minorUnits: MinorUnits,
    {
        currency = FALLBACK.currency,
        locale = FALLBACK.locale,
        bare = false,
        signed = false,
    }: FormatMoneyOptions = {},
): string {
    return new Intl.NumberFormat(locale, {
        ...(bare ? { style: 'decimal' } : { style: 'currency', currency }),
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        signDisplay: signed ? 'exceptZero' : 'auto',
    }).format(minorUnits / SUBUNITS);
}

/**
 * Convert minor units to a major-unit number, for chart axes and other places
 * that need a scalar. Do not run arithmetic on the result and send it back.
 */
export function toMajorUnits(minorUnits: MinorUnits): number {
    return minorUnits / SUBUNITS;
}

/**
 * Turn typed input such as `"1,234.56"` into minor units, for live totals while
 * a user is still typing. Returns `null` when the text is not a usable amount.
 *
 * Unlike `Money::fromDecimal`, which rejects excess precision outright, this
 * truncates past the second decimal so a half-typed figure never throws. The
 * server remains the authority on what is actually accepted.
 */
export function parseMoney(input: string): MinorUnits | null {
    const cleaned = input.trim().replace(/,/g, '');

    if (
        cleaned === '' ||
        cleaned === '.' ||
        !/^[+-]?\d*(\.\d*)?$/.test(cleaned)
    ) {
        return null;
    }

    const [major = '', fraction = ''] = cleaned.replace(/^[+-]/, '').split('.');
    const minorUnits =
        Number(major || '0') * SUBUNITS +
        Number(fraction.slice(0, 2).padEnd(2, '0'));

    return cleaned.startsWith('-') ? -minorUnits : minorUnits;
}
