import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import type { ReactNode } from 'react';

import { MoneyDisplay } from '@/components/money-display';
import { Card, CardContent } from '@/components/ui/card';
import type { MinorUnits } from '@/lib/money';
import { cn } from '@/lib/utils';

export type StatTileProps = {
    label: string;
    /** Minor units when `money`, otherwise rendered as given. */
    value: MinorUnits | string;
    money?: boolean;
    /** Tint a negative value red. For profit, not for costs. */
    colored?: boolean;
    /** The same figure for the previous period, to compute the change. */
    previous?: MinorUnits;
    /** A line of context beneath the figure — an average, a count, a share. */
    hint?: ReactNode;
    className?: string;
};

/**
 * Change against the comparison period, as a whole percent.
 *
 * Returns null when there is nothing to compare against: "up from zero" is
 * infinite, and a tile reading "+∞%" tells nobody anything.
 */
function changePercent(value: MinorUnits, previous: MinorUnits): number | null {
    if (previous === 0) {
        return null;
    }

    return Math.round(((value - previous) / Math.abs(previous)) * 100);
}

/**
 * One headline figure, with how it compares to the stretch before it.
 *
 * The comparison period is the same number of days immediately before, not the
 * previous calendar month — see `ReportPeriod::previous()` for why.
 */
export function StatTile({
    label,
    value,
    money = false,
    colored = false,
    previous,
    hint,
    className,
}: StatTileProps) {
    const change =
        money && previous !== undefined && typeof value === 'number'
            ? changePercent(value, previous)
            : null;

    const Direction =
        change === null || change === 0
            ? Minus
            : change > 0
              ? ArrowUpRight
              : ArrowDownRight;

    return (
        <Card className={cn('gap-0', className)}>
            <CardContent className="flex flex-col gap-1">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>

                <span className="text-2xl font-semibold tracking-tight">
                    {money && typeof value === 'number' ? (
                        <MoneyDisplay amount={value} colored={colored} />
                    ) : (
                        <span className="font-mono tabular-nums">{value}</span>
                    )}
                </span>

                <div className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                    {change !== null && (
                        <span
                            className={cn(
                                'inline-flex items-center gap-0.5 font-medium',
                                change > 0 && 'text-foreground',
                                change < 0 && 'text-destructive',
                            )}
                        >
                            <Direction className="size-3" />
                            {Math.abs(change)}%
                        </span>
                    )}
                    {hint}
                </div>
            </CardContent>
        </Card>
    );
}
