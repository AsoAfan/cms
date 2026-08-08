import { Link } from '@inertiajs/react';
import { Receipt, ShoppingCart, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { MoneyDisplay } from '@/components/money-display';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { MinorUnits } from '@/lib/money';
import purchases from '@/routes/purchases';
import sales from '@/routes/sales';
import type { Activity, ActivityKind, ActivityRow } from '@/types/reports';

/** The combined view, alongside the three document kinds. */
export type ActivityTab = ActivityKind | 'all';

type KindPresentation = {
    icon: LucideIcon;
    /** Singular, for the kind column of the combined table. */
    noun: string;
    /** What the `label` column is called on this kind's own table. */
    labelHeader: string;
    /** What the `detail` column is called on this kind's own table. */
    detailHeader: string;
    empty: string;
};

export const ACTIVITY_KINDS: Record<ActivityKind, KindPresentation> = {
    sale: {
        icon: Receipt,
        noun: 'Sale',
        labelHeader: 'Number',
        detailHeader: 'Payment',
        empty: 'No sales in this period.',
    },
    purchase: {
        icon: ShoppingCart,
        noun: 'Purchase',
        labelHeader: 'Number',
        detailHeader: 'Supplier',
        empty: 'No purchases in this period.',
    },
    expense: {
        icon: Wallet,
        noun: 'Expense',
        labelHeader: 'Title',
        detailHeader: 'Category',
        empty: 'No expenses in this period.',
    },
};

/**
 * The three lists as one, newest first.
 *
 * `sort` is stable, so documents sharing a date keep the order the server sent
 * them in — sales, then purchases, then expenses.
 */
export function combineActivity(
    activity: Activity,
    limit?: number,
): ActivityRow[] {
    const rows = [
        ...activity.sales,
        ...activity.purchases,
        ...activity.expenses,
    ].sort((a, b) => (a.date === b.date ? 0 : a.date < b.date ? 1 : -1));

    return limit === undefined ? rows : rows.slice(0, limit);
}

/**
 * Where a row's document lives. Expenses are edited in a drawer on their own
 * screen rather than at a URL of their own, so they are not linked.
 */
function documentUrl(row: ActivityRow): string | null {
    if (row.kind === 'sale') {
        return row.draft ? sales.edit.url(row.id) : sales.show.url(row.id);
    }

    if (row.kind === 'purchase') {
        return row.draft
            ? purchases.edit.url(row.id)
            : purchases.show.url(row.id);
    }

    return null;
}

export type ActivityTableProps = {
    /** `all` adds a kind column and mixes the three together. */
    tab: ActivityTab;
    rows: ActivityRow[];
    /**
     * The period total for this kind, from the server rather than summed from
     * the rows, so the footer can never disagree with the tiles above it.
     * Omit on a table showing only the latest few.
     */
    total?: MinorUnits;
    emptyDescription?: string;
};

/**
 * The documents behind the figures, one row each.
 *
 * The same table serves the dashboard's latest-few view and the report's whole
 * period, and all four tabs of both, because `ActivityQuery` normalises every
 * kind of document into one row shape.
 */
export function ActivityTable({
    tab,
    rows,
    total,
    emptyDescription,
}: ActivityTableProps) {
    const combined = tab === 'all';
    const columnCount = combined ? 5 : 4;

    return (
        <div className="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        {combined && <TableHead>Kind</TableHead>}
                        <TableHead>
                            {combined
                                ? 'Document'
                                : ACTIVITY_KINDS[tab].labelHeader}
                        </TableHead>
                        <TableHead className="hidden sm:table-cell">
                            {combined
                                ? 'Detail'
                                : ACTIVITY_KINDS[tab].detailHeader}
                        </TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {rows.length === 0 ? (
                        <TableRow className="hover:bg-transparent">
                            <TableCell colSpan={columnCount} className="p-0">
                                <EmptyState
                                    title={
                                        combined
                                            ? 'Nothing recorded in this period.'
                                            : ACTIVITY_KINDS[tab].empty
                                    }
                                    description={emptyDescription}
                                />
                            </TableCell>
                        </TableRow>
                    ) : (
                        rows.map((row) => (
                            <ActivityTableRow
                                key={`${row.kind}-${row.id}`}
                                row={row}
                                showKind={combined}
                            />
                        ))
                    )}
                </TableBody>

                {total !== undefined && rows.length > 0 && (
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={columnCount - 1}>
                                Total
                            </TableCell>
                            <TableCell className="text-right font-medium">
                                <MoneyDisplay amount={total} />
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                )}
            </Table>
        </div>
    );
}

function ActivityTableRow({
    row,
    showKind,
}: {
    row: ActivityRow;
    showKind: boolean;
}) {
    const { icon: Icon, noun } = ACTIVITY_KINDS[row.kind];
    const url = documentUrl(row);

    return (
        <TableRow>
            <TableCell className="whitespace-nowrap text-muted-foreground">
                {row.date}
            </TableCell>

            {showKind && (
                <TableCell>
                    <span className="flex items-center gap-1.5 whitespace-nowrap text-muted-foreground">
                        <Icon className="size-3.5" />
                        {noun}
                    </span>
                </TableCell>
            )}

            <TableCell>
                <span className="flex items-center gap-2">
                    {url ? (
                        <Link
                            href={url}
                            className="font-medium hover:underline"
                        >
                            {row.label}
                        </Link>
                    ) : (
                        <span className="font-medium">{row.label}</span>
                    )}
                    {row.draft && <Badge variant="outline">Draft</Badge>}
                </span>
            </TableCell>

            <TableCell className="hidden text-muted-foreground sm:table-cell">
                {row.detail ?? '—'}
            </TableCell>

            <TableCell className="text-right">
                <MoneyDisplay amount={row.total} />
            </TableCell>
        </TableRow>
    );
}
