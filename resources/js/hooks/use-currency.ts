import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

import { formatMoney } from '@/lib/money';
import type { FormatMoneyOptions, MinorUnits } from '@/lib/money';
import type { Currency } from '@/types';

/**
 * The application's currency, as shared by HandleInertiaRequests.
 */
export function useCurrency(): Currency {
    return usePage().props.currency;
}

/**
 * A money formatter bound to the application's currency.
 *
 *     const format = useFormatMoney();
 *     format(123456); // "$1,234.56"
 */
export function useFormatMoney(): (
    minorUnits: MinorUnits,
    options?: FormatMoneyOptions,
) => string {
    const { code, locale } = useCurrency();

    return useCallback(
        (minorUnits: MinorUnits, options: FormatMoneyOptions = {}) =>
            formatMoney(minorUnits, { currency: code, locale, ...options }),
        [code, locale],
    );
}
