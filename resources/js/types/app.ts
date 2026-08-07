import type { LucideIcon } from 'lucide-react';

export type Currency = {
    code: string;
    locale: string;
};

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
