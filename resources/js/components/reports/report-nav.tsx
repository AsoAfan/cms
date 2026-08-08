import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import reports from '@/routes/reports';
import type { ReportPeriod } from '@/types/reports';

const tabs = [
    { label: 'Summary', href: reports.summary.url },
    { label: 'Sales', href: reports.sales.url },
    { label: 'Purchases', href: reports.purchases.url },
    { label: 'Expenses', href: reports.expenses.url },
    { label: 'Products', href: reports.products.url },
    { label: 'Inventory', href: reports.inventory.url },
];

/**
 * Moves between reports without losing the window you were looking at.
 *
 * Each link carries the current period, so switching from sales to expenses
 * answers for the same dates — the alternative is re-picking the range on every
 * screen, which is how people give up on a reports section.
 */
export function ReportNav({ period }: { period: ReportPeriod }) {
    const current = usePage().url.split('?')[0];

    const query = period.preset
        ? { preset: period.preset }
        : { from: period.from, to: period.to };

    return (
        <nav className="-mx-1 flex gap-1 overflow-x-auto border-b pb-px">
            {tabs.map((tab) => {
                const href = tab.href({ query });
                const active = href.split('?')[0] === current;

                return (
                    <Link
                        key={tab.label}
                        href={href}
                        prefetch
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'shrink-0 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                            active
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
