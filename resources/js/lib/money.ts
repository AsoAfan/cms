/**
 * Money on the frontend is always a whole number of minor units, matching
 * `App\Support\Money` on the server, and always in the BASE currency. Amounts
 * arrive from Inertia as plain integers — never format or compare them as
 * major-unit floats.
 *
 * Two decimal places for every currency, whatever it is conventionally quoted
 * in. How many places a currency SHOWS is a display setting that travels with
 * it (`fraction_digits`), and dinars show none.
 */
export type MinorUnits = number;

const SUBUNITS = 100;

/**
 * Fixed-point denominator for an exchange rate, mirroring
 * `App\Support\ExchangeRates::SCALE`. A rate is base major units per one
 * foreign major unit: 1,320.50 to the dollar is 1_320_500_000.
 */
export const RATE_SCALE = 1_000_000;

/**
 * Fallbacks for the rare caller outside a React tree. Components should reach
 * for `useFormatMoney()`, which binds to the currency the server shares.
 */
const FALLBACK = {
    currency: 'IQD',
    locale: 'en-US',
    fractionDigits: 0,
};

export type FormatMoneyOptions = {
    currency?: string;
    locale?: string;
    /** Decimal places to show. Defaults to the currency's own convention. */
    fractionDigits?: number;
    /** Drop the currency symbol — for table columns that label it in the header. */
    bare?: boolean;
    /** Force a leading `+` on positive amounts, e.g. for a profit delta. */
    signed?: boolean;
};

/**
 * Render minor units for display: `formatMoney(123456)` → `IQD 1,235`.
 */
export function formatMoney(
    minorUnits: MinorUnits,
    {
        currency = FALLBACK.currency,
        locale = FALLBACK.locale,
        fractionDigits = FALLBACK.fractionDigits,
        bare = false,
        signed = false,
    }: FormatMoneyOptions = {},
): string {
    return new Intl.NumberFormat(locale, {
        ...(bare ? { style: 'decimal' } : { style: 'currency', currency }),
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
        signDisplay: signed ? 'exceptZero' : 'auto',
    }).format(minorUnits / SUBUNITS);
}

/**
 * Convert an amount typed in some currency into the base currency, exactly as
 * `ExchangeRates::toBase()` does on the server — integer arithmetic, rounding
 * half away from zero.
 *
 * Matching the server's rounding is the whole point: the running total a form
 * shows while someone types must be the figure that gets stored, to the last
 * dinar, or the invoice appears to change the moment it is saved.
 */
export function convertToBase(
    minorUnits: MinorUnits,
    rate: number,
): MinorUnits {
    return scale(minorUnits, rate, RATE_SCALE);
}

/**
 * Convert a base-currency amount for display in another currency. Display
 * only — never send the result back to the server.
 */
export function convertFromBase(
    minorUnits: MinorUnits,
    rate: number,
): MinorUnits {
    return scale(minorUnits, RATE_SCALE, rate);
}

/**
 * `minorUnits × numerator / denominator`, rounded half away from zero.
 */
function scale(
    minorUnits: MinorUnits,
    numerator: number,
    denominator: number,
): MinorUnits {
    if (denominator === 0) {
        return 0;
    }

    const product = minorUnits * numerator;
    const sign = product < 0 !== denominator < 0 ? -1 : 1;

    return (
        sign *
        Math.floor(
            (Math.abs(product) * 2 + Math.abs(denominator)) /
                (Math.abs(denominator) * 2),
        )
    );
}

/**
 * Minor units as the plain decimal string an input holds and the server's
 * `decimal:0,2` rule accepts: `toDecimalString(1234)` → `"12.34"`.
 *
 * Deliberately not `formatMoney(…, { bare: true })` — that groups thousands,
 * and `"1,234.56"` fails validation on the way back.
 */
export function toDecimalString(minorUnits: MinorUnits): string {
    const absolute = Math.abs(minorUnits);
    const fraction = String(absolute % SUBUNITS).padStart(2, '0');

    return `${minorUnits < 0 ? '-' : ''}${Math.floor(absolute / SUBUNITS)}.${fraction}`;
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
