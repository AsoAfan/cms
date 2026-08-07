import { useFormatMoney } from '@/hooks/use-currency';
import type { MinorUnits } from '@/lib/money';
import { cn } from '@/lib/utils';

export type MoneyDisplayProps = {
    /** Minor units, exactly as the server sent them. */
    amount: MinorUnits;
    /** Tint negatives red — for profit and margin columns, not for costs. */
    colored?: boolean;
    /** Force a leading `+` on positive amounts. */
    signed?: boolean;
    /** Drop the currency symbol, for columns that label it in the header. */
    bare?: boolean;
    className?: string;
};

/**
 * Renders a monetary amount. Tabular figures keep decimal points aligned down
 * a column, which is the whole point of a money column.
 */
export function MoneyDisplay({
    amount,
    colored = false,
    signed = false,
    bare = false,
    className,
}: MoneyDisplayProps) {
    const format = useFormatMoney();

    return (
        <span
            className={cn(
                'font-mono tabular-nums',
                colored && amount < 0 && 'text-destructive',
                className,
            )}
        >
            {format(amount, { signed, bare })}
        </span>
    );
}
