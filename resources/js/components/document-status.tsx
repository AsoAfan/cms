import { Check, Truck, ClipboardList } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { DocumentStatus, DocumentStatusOption } from '@/types/documents';

const PRESENTATION: Record<
    DocumentStatus,
    { icon: LucideIcon; variant: 'outline' | 'secondary' | 'default' }
> = {
    ordered: { icon: ClipboardList, variant: 'outline' },
    on_the_way: { icon: Truck, variant: 'secondary' },
    proceed: { icon: Check, variant: 'default' },
};

/** The order they run in, for working out what is behind and what is ahead. */
const SEQUENCE: DocumentStatus[] = ['ordered', 'on_the_way', 'proceed'];

export function StatusBadge({
    status,
    statuses,
}: {
    status: DocumentStatus;
    statuses: DocumentStatusOption[];
}) {
    const { icon: Icon, variant } = PRESENTATION[status];
    const label =
        statuses.find((option) => option.value === status)?.label ?? status;

    return (
        <Badge variant={variant}>
            <Icon data-icon="inline-start" />
            {label}
        </Badge>
    );
}

/**
 * The three states as one row of buttons, for a form.
 *
 * A select would hide two of three options behind a click. There are only ever
 * three, they are the thing most likely to be changed, and seeing all of them
 * is also how someone learns what the states are.
 */
export function StatusPicker({
    value,
    statuses,
    onChange,
    disabled,
}: {
    value: DocumentStatus;
    statuses: DocumentStatusOption[];
    onChange: (status: DocumentStatus) => void;
    disabled?: boolean;
}) {
    return (
        <div
            role="radiogroup"
            aria-label="Status"
            className="grid gap-2 sm:grid-cols-3"
        >
            {statuses.map((option) => {
                const { icon: Icon } = PRESENTATION[option.value];
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'flex flex-col items-start gap-0.5 rounded-lg border px-3 py-2 text-left transition-colors',
                            'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            'disabled:pointer-events-none disabled:opacity-50',
                            selected
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:bg-muted',
                        )}
                    >
                        <span className="flex items-center gap-1.5 text-sm font-medium">
                            <Icon className="size-3.5" />
                            {option.label}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {option.description}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Where the document is and what the next move is, for the document's own page.
 *
 * Every state stays reachable, not just the next one: an order marked arrived
 * by mistake has to be able to go back, and the server reverses the stock when
 * it does.
 */
export function StatusStepper({
    status,
    statuses,
    onChange,
    busy,
}: {
    status: DocumentStatus;
    statuses: DocumentStatusOption[];
    onChange: (status: DocumentStatus) => void;
    busy?: boolean;
}) {
    const position = SEQUENCE.indexOf(status);

    return (
        <div className="flex flex-wrap items-center gap-1">
            {statuses.map((option, index) => {
                const { icon: Icon } = PRESENTATION[option.value];
                const current = option.value === status;
                const done = index < position;

                return (
                    <Button
                        key={option.value}
                        type="button"
                        size="sm"
                        variant={current ? 'default' : 'ghost'}
                        disabled={busy || current}
                        title={option.description}
                        className={cn(
                            !current && done && 'text-muted-foreground',
                        )}
                        onClick={() => onChange(option.value)}
                    >
                        <Icon data-icon="inline-start" />
                        {option.label}
                    </Button>
                );
            })}
        </div>
    );
}
