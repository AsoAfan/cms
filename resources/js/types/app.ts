import type { LucideIcon } from 'lucide-react';

/** One currency an amount may be typed or read in. Mirrors config/money.php. */
export type CurrencyOption = {
    code: string;
    name: string;
    symbol: string;
    /** Decimal places to show. Dinars show none. */
    fraction_digits: number;
    /**
     * Base major units per one unit of this currency, scaled by `RATE_SCALE`.
     * The base currency is its own unit, so exactly `RATE_SCALE`.
     */
    rate: number;
    /** The date that rate was recorded for, or null for the base currency. */
    rate_on: string | null;
    source: string | null;
};

/**
 * Mirrors the `currency` prop from HandleInertiaRequests.
 *
 * Every money figure on the wire is in `base`. `display` is only what the user
 * has asked to look at; converting for display is `useFormatMoney()`'s job.
 */
export type Currency = {
    base: string;
    display: string;
    locale: string;
    currencies: CurrencyOption[];
};

/** The theme the user picked. `system` follows the OS preference. */
export type Appearance = 'light' | 'dark' | 'system';

export type FlashToastType = 'success' | 'error' | 'warning' | 'info';

/** Mirrors App\Support\Flash. */
export type FlashToast = {
    type: FlashToastType;
    message: string;
};

export type BreadcrumbItem = {
    title: string;
    /** Omit on the final crumb — the current page is not a link. */
    href?: string;
};

export type NavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Match the item as active for any URL beneath `href`, not just an exact hit. */
    matchNested?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};

/**
 * A Laravel length-aware paginator as Inertia serializes it.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Mirrors the array returned by App\Support\Table\TableQuery::state(). */
export type TableState = {
    search: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: Record<string, string>;
};
