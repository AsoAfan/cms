import {
    endOfMonth,
    endOfYear,
    format,
    startOfMonth,
    startOfYear,
    subDays,
    subMonths,
} from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

export type { DateRange };

type Preset = {
    label: string;
    range: () => DateRange;
};

/**
 * The ranges a business actually asks for. `ReportPeriod` (P7.T1) mirrors
 * these on the server so a preset means the same thing in both places.
 */
const presets: Preset[] = [
    {
        label: 'Last 7 days',
        range: () => ({ from: subDays(new Date(), 6), to: new Date() }),
    },
    {
        label: 'Last 30 days',
        range: () => ({ from: subDays(new Date(), 29), to: new Date() }),
    },
    {
        label: 'This month',
        range: () => ({ from: startOfMonth(new Date()), to: new Date() }),
    },
    {
        label: 'Last month',
        range: () => ({
            from: startOfMonth(subMonths(new Date(), 1)),
            to: endOfMonth(subMonths(new Date(), 1)),
        }),
    },
    {
        label: 'This year',
        range: () => ({ from: startOfYear(new Date()), to: new Date() }),
    },
    {
        label: 'Last year',
        range: () => ({
            from: startOfYear(subMonths(new Date(), 12)),
            to: endOfYear(subMonths(new Date(), 12)),
        }),
    },
];

export type DateRangePickerProps = {
    value?: DateRange;
    onChange: (range: DateRange | undefined) => void;
    placeholder?: string;
    align?: 'start' | 'center' | 'end';
    className?: string;
};

function label(range: DateRange | undefined, placeholder: string): string {
    if (!range?.from) {
        return placeholder;
    }

    if (!range.to) {
        return format(range.from, 'd MMM yyyy');
    }

    return `${format(range.from, 'd MMM yyyy')} – ${format(range.to, 'd MMM yyyy')}`;
}

/**
 * Picks the reporting period: a two-month calendar with the presets a user
 * reaches for most, so the common cases take one click rather than six.
 */
export function DateRangePicker({
    value,
    onChange,
    placeholder = 'Select a period',
    align = 'start',
    className,
}: DateRangePickerProps) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <Button
                        variant="outline"
                        className={cn(
                            'justify-start font-normal',
                            !value?.from && 'text-muted-foreground',
                            className,
                        )}
                    />
                }
            >
                <CalendarIcon data-icon="inline-start" />
                {label(value, placeholder)}
            </PopoverTrigger>
            <PopoverContent
                align={align}
                className="flex w-auto flex-col p-0 sm:flex-row"
            >
                <div className="flex shrink-0 flex-row gap-1 overflow-x-auto p-2 sm:flex-col sm:overflow-visible">
                    {presets.map((preset) => (
                        <Button
                            key={preset.label}
                            variant="ghost"
                            size="sm"
                            className="justify-start whitespace-nowrap"
                            onClick={() => {
                                onChange(preset.range());
                                setOpen(false);
                            }}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>
                <Separator orientation="vertical" className="hidden sm:block" />
                <Calendar
                    mode="range"
                    numberOfMonths={2}
                    defaultMonth={value?.from}
                    selected={value}
                    onSelect={onChange}
                    autoFocus
                    className="p-2"
                />
            </PopoverContent>
        </Popover>
    );
}
