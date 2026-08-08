import { useId, useState } from 'react';

import { useFormatMoney } from '@/hooks/use-currency';
import type { MinorUnits } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { ReportPeriod } from '@/types/reports';

/*
 * Drawn as inline SVG rather than with a charting library: two series over a
 * date axis is a few dozen lines of geometry, and a dependency for it would be
 * larger than the whole of this file.
 *
 * The two series are told apart by FORM as well as tone — a soft area wash for
 * the context series against a 2px line for the one that matters. The palette
 * is the application's own, which is achromatic by design, so form is what
 * carries identity: the separation survives any colour vision, a black-and-white
 * print, and forced-colours mode. Every tone below is a theme token, so light
 * and dark each get their own step rather than one being flipped from the other.
 */

const HEIGHT = 220;
const WIDTH = 720;
const PADDING = { top: 16, right: 16, bottom: 28, left: 60 };

const PLOT_WIDTH = WIDTH - PADDING.left - PADDING.right;
const PLOT_HEIGHT = HEIGHT - PADDING.top - PADDING.bottom;

export type TrendSeries = {
    key: string;
    label: string;
    values: MinorUnits[];
    /** A filled wash for context; a line for the series the chart is about. */
    shape: 'area' | 'line';
};

export type TrendChartProps = {
    buckets: string[];
    series: TrendSeries[];
    interval: ReportPeriod['interval'];
    className?: string;
};

/** Round a value up to a clean axis top: 1, 2 or 5 times a power of ten. */
function niceCeiling(value: number): number {
    if (value <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 2.5, 5, 10].find((n) => value <= n * magnitude) ?? 10;

    return step * magnitude;
}

function formatBucket(bucket: string, interval: ReportPeriod['interval']) {
    const date = new Date(`${bucket}T00:00:00`);

    return date.toLocaleDateString(undefined, {
        day: interval === 'month' ? undefined : 'numeric',
        month: 'short',
        year: interval === 'month' ? '2-digit' : undefined,
    });
}

/**
 * Takings and profit over the period.
 *
 * Both series are money on one scale, so they share one axis — a second scale
 * would let any two lines be made to tell any story.
 */
export function TrendChart({
    buckets,
    series,
    interval,
    className,
}: TrendChartProps) {
    const format = useFormatMoney();
    const clipId = useId();
    const [hovered, setHovered] = useState<number | null>(null);

    const values = series.flatMap((line) => line.values);
    const highest = niceCeiling(Math.max(0, ...values) / 100) * 100;
    const lowest = Math.min(0, ...values);
    const floor = lowest < 0 ? -niceCeiling(-lowest / 100) * 100 : 0;
    const span = highest - floor || 1;

    const x = (index: number) =>
        PADDING.left +
        (buckets.length < 2
            ? PLOT_WIDTH / 2
            : (index / (buckets.length - 1)) * PLOT_WIDTH);

    const y = (value: number) =>
        PADDING.top + PLOT_HEIGHT - ((value - floor) / span) * PLOT_HEIGHT;

    const ticks = [floor, floor + span / 2, highest].filter(
        (tick, index, all) => all.indexOf(tick) === index,
    );

    // Enough labels to orient the reader, never so many that they collide.
    const labelEvery = Math.max(1, Math.ceil(buckets.length / 7));

    function pathFor(line: TrendSeries): string {
        return line.values
            .map(
                (value, index) =>
                    `${index === 0 ? 'M' : 'L'}${x(index)},${y(value)}`,
            )
            .join(' ');
    }

    return (
        <figure className={cn('flex flex-col gap-3', className)}>
            <figcaption className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                {series.map((line) => (
                    <span key={line.key} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className={cn(
                                'inline-block h-2.5 w-2.5 rounded-sm',
                                line.shape === 'area'
                                    ? 'bg-muted-foreground/25'
                                    : 'bg-foreground',
                            )}
                        />
                        {line.label}
                    </span>
                ))}
            </figcaption>

            <div
                className="relative w-full"
                onMouseLeave={() => setHovered(null)}
            >
                <svg
                    viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                    className="h-56 w-full touch-none"
                    role="img"
                    aria-label={`${series.map((line) => line.label).join(' and ')} over time`}
                    onMouseMove={(event) => {
                        const bounds =
                            event.currentTarget.getBoundingClientRect();
                        const position =
                            ((event.clientX - bounds.left) / bounds.width) *
                                WIDTH -
                            PADDING.left;

                        setHovered(
                            Math.min(
                                buckets.length - 1,
                                Math.max(
                                    0,
                                    Math.round(
                                        (position / PLOT_WIDTH) *
                                            (buckets.length - 1),
                                    ),
                                ),
                            ),
                        );
                    }}
                >
                    <defs>
                        <clipPath id={clipId}>
                            <rect
                                x={PADDING.left}
                                y={PADDING.top}
                                width={PLOT_WIDTH}
                                height={PLOT_HEIGHT}
                            />
                        </clipPath>
                    </defs>

                    {/* Gridlines and axis labels, recessive by design. */}
                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={PADDING.left}
                                x2={WIDTH - PADDING.right}
                                y1={y(tick)}
                                y2={y(tick)}
                                className="stroke-border"
                                strokeWidth={1}
                            />
                            <text
                                x={PADDING.left - 8}
                                y={y(tick) + 4}
                                textAnchor="end"
                                className="fill-muted-foreground text-[10px] tabular-nums"
                            >
                                {format(tick, { bare: true })}
                            </text>
                        </g>
                    ))}

                    {buckets.map((bucket, index) =>
                        index % labelEvery === 0 ? (
                            <text
                                key={bucket}
                                x={x(index)}
                                y={HEIGHT - 8}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[10px]"
                            >
                                {formatBucket(bucket, interval)}
                            </text>
                        ) : null,
                    )}

                    <g clipPath={`url(#${clipId})`}>
                        {series.map((line) =>
                            line.shape === 'area' ? (
                                <path
                                    key={line.key}
                                    d={`${pathFor(line)} L${x(line.values.length - 1)},${y(floor)} L${x(0)},${y(floor)} Z`}
                                    className="fill-muted-foreground/20"
                                />
                            ) : (
                                <path
                                    key={line.key}
                                    d={pathFor(line)}
                                    fill="none"
                                    strokeWidth={2}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="stroke-foreground"
                                />
                            ),
                        )}
                    </g>

                    {hovered !== null && (
                        <g>
                            <line
                                x1={x(hovered)}
                                x2={x(hovered)}
                                y1={PADDING.top}
                                y2={PADDING.top + PLOT_HEIGHT}
                                className="stroke-border"
                                strokeWidth={1}
                            />
                            {series.map((line) => (
                                <circle
                                    key={line.key}
                                    cx={x(hovered)}
                                    cy={y(line.values[hovered] ?? 0)}
                                    r={4}
                                    strokeWidth={2}
                                    className={cn(
                                        'stroke-card',
                                        line.shape === 'area'
                                            ? 'fill-muted-foreground'
                                            : 'fill-foreground',
                                    )}
                                />
                            ))}
                        </g>
                    )}
                </svg>

                {hovered !== null && (
                    <div
                        className="pointer-events-none absolute top-2 rounded-lg border bg-popover px-3 py-2 text-xs shadow-md"
                        style={{
                            left: `${(x(hovered) / WIDTH) * 100}%`,
                            transform:
                                hovered > buckets.length / 2
                                    ? 'translateX(calc(-100% - 12px))'
                                    : 'translateX(12px)',
                        }}
                    >
                        <div className="font-medium">
                            {formatBucket(buckets[hovered], interval)}
                        </div>
                        {series.map((line) => (
                            <div
                                key={line.key}
                                className="mt-1 flex items-center justify-between gap-4"
                            >
                                <span className="text-muted-foreground">
                                    {line.label}
                                </span>
                                <span className="font-mono tabular-nums">
                                    {format(line.values[hovered] ?? 0)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </figure>
    );
}
