import { router, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { DateRangePicker } from '@/components/date-range-picker';
import type { DateRange } from '@/components/date-range-picker';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import reports from '@/routes/reports';
import type { ReportPeriod, ReportPresetOption } from '@/types/reports';

const CUSTOM = 'custom';

/**
 * Merge period parameters into the current URL.
 *
 * The period lives in the query string and nowhere else, so a report someone
 * bookmarks or sends to a colleague shows them the same figures. Setting a
 * preset clears the explicit dates and vice versa — the server takes dates over
 * a preset, and leaving both behind would make the URL lie about what it shows.
 */
function periodUrl(
    current: string,
    next: { preset?: string | null; from?: string | null; to?: string | null },
): string {
    const url = new URL(current, window.location.origin);

    for (const key of ['preset', 'from', 'to'] as const) {
        url.searchParams.delete(key);
    }

    for (const [key, value] of Object.entries(next)) {
        if (value) {
            url.searchParams.set(key, value);
        }
    }

    return `${url.pathname}${url.search}`;
}

/** `YYYY-MM-DD` in the user's own timezone, not UTC. */
function isoDate(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

export type ReportPeriodFilterProps = {
    period: ReportPeriod;
    presets: ReportPresetOption[];
    /** Report name for the CSV route, e.g. `sales`. Omit to hide the button. */
    exportAs?: string;
};

/**
 * The date control every report screen carries. One filter, one URL contract,
 * so moving between reports keeps the window you were looking at.
 */
export function ReportPeriodFilter({
    period,
    presets,
    exportAs,
}: ReportPeriodFilterProps) {
    const currentUrl = usePage().url;

    function visit(next: Parameters<typeof periodUrl>[1]) {
        router.get(
            periodUrl(currentUrl, next),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    const range: DateRange = {
        from: new Date(`${period.from}T00:00:00`),
        to: new Date(`${period.to}T00:00:00`),
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select
                value={period.preset ?? CUSTOM}
                onValueChange={(value) =>
                    String(value) !== CUSTOM && visit({ preset: String(value) })
                }
            >
                <SelectTrigger className="w-40" aria-label="Reporting period">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {presets.map((preset) => (
                        <SelectItem key={preset.value} value={preset.value}>
                            {preset.label}
                        </SelectItem>
                    ))}
                    {/* Only reachable by picking dates, never by choosing it. */}
                    <SelectItem value={CUSTOM} disabled>
                        Custom range
                    </SelectItem>
                </SelectContent>
            </Select>

            <DateRangePicker
                value={range}
                onChange={(next) =>
                    next?.from &&
                    visit({
                        from: isoDate(next.from),
                        to: isoDate(next.to ?? next.from),
                    })
                }
            />

            {exportAs && (
                <Button
                    variant="outline"
                    render={
                        <a
                            href={reports.export.url(exportAs, {
                                query: {
                                    from: period.from,
                                    to: period.to,
                                },
                            })}
                            download
                        />
                    }
                >
                    <Download data-icon="inline-start" />
                    Export
                </Button>
            )}
        </div>
    );
}
