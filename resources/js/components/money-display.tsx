import { createContext, useContext, useMemo, useState } from 'react';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    useCurrency,
    useCurrencyOptions,
    useFormatMoney,
    useFormatMoneyIn,
} from '@/hooks/use-currency';
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

/**
 * The currency a block of figures is being read in, shared by everything
 * inside a `MoneyReviewGroup`.
 *
 * Null outside one, which is how `MoneyReview` knows it is on its own and has
 * to carry its own dropdown.
 */
const ReadingContext = createContext<{
    reading: string;
    setReading: (currency: string) => void;
} | null>(null);

/**
 * One dropdown for a whole block of figures.
 *
 * A summary card is one statement about one sale — a total, what the goods
 * cost, what was made, what is owed. Reading the total in dollars and the
 * profit in dinars is not a thing anybody wants, and a dropdown per line turns
 * four figures into eight controls. Wrap the block in this, put a single
 * `<MoneyReviewSwitch>` in its heading, and every `<MoneyReview>` inside
 * follows it.
 */
export function MoneyReviewGroup({ children }: { children: React.ReactNode }) {
    const { base } = useCurrency();
    const [reading, setReading] = useState(base);

    const value = useMemo(() => ({ reading, setReading }), [reading]);

    return (
        <ReadingContext.Provider value={value}>
            {children}
        </ReadingContext.Provider>
    );
}

/**
 * The dropdown for the group it sits in. Renders nothing when there is only
 * one currency to read in, so a single-currency shop never sees it — and the
 * label goes with it, rather than sitting there on its own.
 */
export function MoneyReviewSwitch({
    label,
    className,
}: {
    label?: string;
    className?: string;
}) {
    const group = useContext(ReadingContext);
    const options = useCurrencyOptions();

    if (group === null || options.length < 2) {
        return null;
    }

    return (
        <span className={cn('inline-flex items-center gap-1.5', className)}>
            {label && (
                <span className="text-xs text-muted-foreground">{label}</span>
            )}
            <ReadingSelect value={group.reading} onChange={group.setReading} />
        </span>
    );
}

export type MoneyReviewProps = {
    /** Minor units, exactly as the server sent them. */
    amount: MinorUnits;
    colored?: boolean;
    signed?: boolean;
    className?: string;
    /** Larger type for a headline total. */
    size?: 'default' | 'lg';
};

/**
 * A figure you can read in another currency without changing anything.
 *
 * For standalone amounts — an invoice total, a profit, a KPI tile — where "what
 * is that in dollars?" is a question worth answering in place. Switching affects
 * this figure alone and lasts until you leave the screen; nothing is stored, so
 * no figure is ever silently sitting in a currency somebody chose last week.
 *
 * Inside a `MoneyReviewGroup` it drops its own dropdown and follows the group's
 * one instead.
 *
 * Read-only by construction: it converts for display from the base-currency
 * minor units the server sent and never sends anything back.
 */
export function MoneyReview({
    amount,
    colored = false,
    signed = false,
    className,
    size = 'default',
}: MoneyReviewProps) {
    const { base } = useCurrency();
    const options = useCurrencyOptions();
    const formatIn = useFormatMoneyIn();
    const group = useContext(ReadingContext);
    const [own, setOwn] = useState(base);
    const reading = group?.reading ?? own;

    const figure = (
        <span
            className={cn(
                'font-mono tabular-nums',
                size === 'lg' && 'text-lg font-semibold',
                colored && amount < 0 && 'text-destructive',
            )}
        >
            {formatIn(amount, reading, { signed })}
        </span>
    );

    // Either the group carries the control, or there is nothing to review
    // against and no control to explain away.
    if (group !== null || options.length < 2) {
        return <span className={className}>{figure}</span>;
    }

    return (
        <span className={cn('inline-flex items-center gap-1.5', className)}>
            {figure}
            <ReadingSelect value={own} onChange={setOwn} />
        </span>
    );
}

/**
 * The control itself.
 *
 * Deliberately not borderless: flush against a right-aligned number with no
 * divider or tint it reads as decoration, and users reported it as missing.
 */
function ReadingSelect({
    value,
    onChange,
    className,
}: {
    value: string;
    onChange: (currency: string) => void;
    className?: string;
}) {
    const options = useCurrencyOptions();

    return (
        <Select value={value} onValueChange={(next) => onChange(String(next))}>
            <SelectTrigger
                size="sm"
                aria-label="Read these amounts in another currency"
                title="Read these amounts in another currency"
                className={cn(
                    'h-6 cursor-pointer gap-1 border-input bg-muted/60 px-1.5 font-mono text-xs font-medium hover:bg-muted dark:bg-input/50 dark:hover:bg-input',
                    className,
                )}
            >
                {/* No `items` on the root, so the trigger shows the raw value —
                    the code, which is the whole label this control has room
                    for. The popup's items spell it out. */}
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.code} value={option.code}>
                        <span className="font-mono">{option.code}</span>
                        <span className="text-muted-foreground">
                            {option.name}
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
