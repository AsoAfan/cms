import { ChevronDown, Printer } from 'lucide-react';
import { useCallback, useState, useSyncExternalStore } from 'react';

import { FormField } from '@/components/form-field';
import { Logo } from '@/components/logo';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    useCurrency,
    useCurrencyOption,
    useCurrencyOptions,
} from '@/hooks/use-currency';
import { RATE_SCALE, convertFromBase, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { SaleDetail, SaleDetailLine } from '@/types/sales';

/** How many lines fit a page before anyone has changed it. */
export const DEFAULT_LINES_PER_PAGE = 10;

const MIN_LINES_PER_PAGE = 1;
// Measured, not guessed: with the design's masthead on A4 there is about
// 160mm left for lines, and twenty of them at the tightest spacing is what
// fits. Promising more would silently spill onto another sheet, which is the
// one thing this setting exists to stop.
const MAX_LINES_PER_PAGE = 20;

/**
 * What the printer settings are filed under. Per browser, not per business:
 * both are facts about the paper and the person at the counter, not about the
 * books.
 */
const LINES_PER_PAGE_KEY = 'invoice.lines-per-page';
const CURRENCY_KEY = 'invoice.currency';

function clampLinesPerPage(value: number): number {
    if (!Number.isFinite(value)) {
        return DEFAULT_LINES_PER_PAGE;
    }

    return Math.min(
        MAX_LINES_PER_PAGE,
        Math.max(MIN_LINES_PER_PAGE, Math.trunc(value)),
    );
}

/**
 * The stored settings, as an external store `useSyncExternalStore` can read.
 *
 * In memory as well as in storage: a private window can refuse `localStorage`
 * outright, and a setting should still hold for the session rather than
 * snapping back to the default on the next render.
 */
const listeners = new Set<() => void>();
const inMemory = new Map<string, string>();

function readSetting(key: string): string | null {
    try {
        return window.localStorage.getItem(key) ?? inMemory.get(key) ?? null;
    } catch {
        return inMemory.get(key) ?? null;
    }
}

function storeSetting(key: string, value: string): void {
    inMemory.set(key, value);

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Held for this session only, which is better than not at all.
    }

    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);
    window.addEventListener('storage', listener);

    return () => {
        listeners.delete(listener);
        window.removeEventListener('storage', listener);
    };
}

/** Nothing is stored during SSR, so the server renders the default. */
function noSetting(): string | null {
    return null;
}

/**
 * One remembered printer setting.
 *
 * Read through `useSyncExternalStore` rather than copied into state on mount:
 * storage is an external store, the server has no view of it, and this is the
 * hook that reconciles exactly that without an effect writing state.
 */
function usePrintSetting(
    key: string,
): [string | null, (value: string) => void] {
    const read = useCallback(() => readSetting(key), [key]);
    const write = useCallback(
        (value: string) => storeSetting(key, value),
        [key],
    );

    return [useSyncExternalStore(subscribe, read, noSetting), write];
}

/** How many lines go on a printed page, remembered for next time. */
export function useLinesPerPage(): [number, (value: number) => void] {
    const [stored, store] = usePrintSetting(LINES_PER_PAGE_KEY);

    const linesPerPage =
        stored === null
            ? DEFAULT_LINES_PER_PAGE
            : clampLinesPerPage(Number(stored));

    return [
        linesPerPage,
        (value: number) => store(String(clampLinesPerPage(value))),
    ];
}

/**
 * The currency the invoice is printed in — dollars for one customer, dinars
 * for the next.
 *
 * Falls back to whatever the user is already reading the app in, so the printed
 * copy matches the screen unless somebody says otherwise. A currency no longer
 * on record falls back too, rather than printing figures in a currency there is
 * no rate for.
 */
export function usePrintCurrency(): [string, (value: string) => void] {
    const { display } = useCurrency();
    const options = useCurrencyOptions();
    const [stored, store] = usePrintSetting(CURRENCY_KEY);

    const known = options.some((option) => option.code === stored);

    return [known && stored !== null ? stored : display, store];
}

/**
 * Print, with how much fits on a page behind the chevron.
 *
 * One click for the ordinary case: the number is set once for the paper and
 * the printer in front of you, and then it stays set.
 */
export function PrintInvoiceButton({
    linesPerPage,
    onLinesPerPageChange,
    currency,
    onCurrencyChange,
}: {
    linesPerPage: number;
    onLinesPerPageChange: (value: number) => void;
    currency: string;
    onCurrencyChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <ButtonGroup>
            <Button variant="outline" onClick={() => window.print()}>
                <Printer data-icon="inline-start" />
                Print
            </Button>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="How much fits on a printed page"
                        >
                            <ChevronDown />
                        </Button>
                    }
                />
                <PopoverContent align="end" className="w-64">
                    {/* Mounted per opening, so the field starts from the stored
                        value rather than from whatever it was first rendered
                        with — and needs no effect to catch up. */}
                    {open && (
                        <div className="grid gap-4">
                            <LinesPerPageField
                                value={linesPerPage}
                                onChange={onLinesPerPageChange}
                            />
                            <PrintCurrencyField
                                value={currency}
                                onChange={onCurrencyChange}
                            />
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </ButtonGroup>
    );
}

/**
 * Typing goes through a string of its own, so half a number on the way to a
 * whole one is not clamped back under the cursor as it is typed.
 */
function LinesPerPageField({
    value,
    onChange,
}: {
    value: number;
    onChange: (value: number) => void;
}) {
    const [draft, setDraft] = useState(String(value));

    return (
        <FormField
            label="Items per page"
            description={`${MIN_LINES_PER_PAGE}–${MAX_LINES_PER_PAGE}. Remembered on this device.`}
        >
            {(control) => (
                <Input
                    {...control}
                    autoFocus
                    inputMode="numeric"
                    className="text-right tabular-nums"
                    value={draft}
                    onChange={(event) => {
                        setDraft(event.target.value);

                        if (event.target.value.trim() !== '') {
                            onChange(Number(event.target.value));
                        }
                    }}
                    onBlur={() => setDraft(String(value))}
                />
            )}
        </FormField>
    );
}

/**
 * Which currency the figures come out in.
 *
 * Renders nothing where there is only one currency on record — a shop that
 * deals in dinars alone has no choice to make.
 */
function PrintCurrencyField({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const options = useCurrencyOptions();

    if (options.length < 2) {
        return null;
    }

    return (
        <FormField
            label="Currency"
            description="Converted at today's rate for this one."
        >
            {(control) => (
                <OptionSelect
                    {...control}
                    className="w-full"
                    value={value}
                    options={options.map((option) => ({
                        value: option.code,
                        label: `${option.code} — ${option.name}`,
                    }))}
                    onChange={onChange}
                />
            )}
        </FormField>
    );
}

/**
 * Split the lines into printed pages. An invoice with nothing on it still
 * prints one page, so the paperwork exists even when the sale is empty.
 */
function paginate(
    lines: SaleDetailLine[],
    perPage: number,
): SaleDetailLine[][] {
    const size = clampLinesPerPage(perPage);
    const pages: SaleDetailLine[][] = [];

    for (let start = 0; start < lines.length; start += size) {
        pages.push(lines.slice(start, start + size));
    }

    return pages.length > 0 ? pages : [[]];
}

/**
 * The invoice as it goes on paper.
 *
 * Hidden on screen and shown only to the printer, so what is printed is the
 * markup the page already has the data for — there is no second route, no PDF
 * service and nothing to keep in step.
 *
 * **Cost and profit are not on it.** This is the customer's copy; what the
 * goods cost the shop stays on the screen, in the summary block.
 *
 * Colours are stated outright rather than in theme tokens: browsers do not
 * print backgrounds by default, so a dark theme would otherwise send pale text
 * to a white page.
 */
/**
 * The head cells' shared look — including the rule under them.
 *
 * **The rule is two pixels in a softer grey, and both halves of that matter.**
 * A 1px rule is genuinely in the PDF but disappears the moment the page is
 * viewed at anything less than full size — which is how a print preview shows
 * it — so the heading looked underlined on one page and bare on the next at
 * random. Chrome rounds border widths to whole device pixels, so 1px and 2px
 * are the only choices there are; `0.35mm` computes straight back to 1px. Two
 * pixels always survives, and dropping the ink to `#999` takes back the weight
 * that buys: two pixels at 40% ink carries about as much as one at 80%, which
 * is the hairline this replaced.
 *
 * It also lives on the CELLS rather than the row, which is where a border on a
 * repeated table head belongs.
 */
const headCell = 'border-b-2 border-[#999] pb-2 font-bold tracking-[0.15em]';

export function SaleInvoiceDocument({
    sale,
    linesPerPage,
    currency,
}: {
    sale: SaleDetail;
    linesPerPage: number;
    /** What the figures are printed in. Stored amounts never move. */
    currency: string;
}) {
    const { base, locale } = useCurrency();
    const printed = useCurrencyOption(currency);

    // Convert ONCE, per figure, and add up what has been converted — never
    // convert a total that was added up in another currency. A customer runs
    // their finger down the TOTAL column and expects it to come to the total;
    // rounding each line and then rounding a separately-converted sum leaves
    // the two a few cents apart, which is exactly the sort of thing that
    // earns a phone call.
    const toPrinted = useCallback(
        (amount: number) =>
            currency === base ? amount : convertFromBase(amount, printed.rate),
        [base, currency, printed.rate],
    );

    const format = useCallback(
        (amount: number, options?: { bare?: boolean }) =>
            formatMoney(amount, {
                currency,
                locale,
                fractionDigits: printed.fraction_digits,
                ...options,
            }),
        [currency, locale, printed.fraction_digits],
    );

    // The invoice's arithmetic, in the currency it is being printed in. The
    // total is the sum of the line totals as printed, and the discount is what
    // separates that from the gross — so every column on the page adds up.
    const printedLines = sale.lines.map((line) => ({
        ...line,
        unit_price: toPrinted(line.unit_price),
        discount: toPrinted(line.discount),
        net_total: toPrinted(line.net_total),
        gross: toPrinted(line.quantity * line.unit_price),
    }));

    const subtotal = printedLines.reduce((sum, line) => sum + line.gross, 0);
    const total = printedLines.reduce((sum, line) => sum + line.net_total, 0);
    const discount = subtotal - total;
    const paid = toPrinted(sale.paid_to_date);

    const pages = paginate(printedLines, linesPerPage);

    // The masthead is a fixed height whatever the sale, so the rows are what
    // has to give when somebody asks for more of them on a sheet. Six items on
    // A4 get the design's own airy spacing; thirty get a tight one and still
    // land on one page, which is the whole point of the setting.
    // Each step is measured against the worst page there is: a full one that
    // ALSO carries the totals, which is what the last page of an invoice whose
    // lines divide exactly looks like. Loosening any step spills that page
    // onto another sheet.
    const rowPadding =
        linesPerPage <= 9
            ? 'py-3'
            : linesPerPage <= 11
              ? 'py-2'
              : linesPerPage <= 15
                ? 'py-1'
                : 'py-0';

    return (
        <div className="hidden print:block">
            {pages.map((lines, page) => {
                const last = page === pages.length - 1;

                return (
                    <section
                        key={page}
                        className={cn(
                            'flex min-h-[var(--invoice-page-height)] flex-col pt-[28mm] pb-[14mm] text-[#333]',
                            !last && 'break-after-page',
                        )}
                    >
                        {/* The masthead: INVOICE reversed out of a bar that
                            runs off the left edge of the paper, the business
                            opposite it. */}
                        <div className="flex items-center justify-between gap-8 pr-[18mm]">
                            <p
                                data-print-ink="exact"
                                className="w-[34%] bg-[#3a3a3a] py-5 text-center text-[15pt] font-bold tracking-[0.25em] text-white"
                            >
                                INVOICE
                            </p>
                            <Logo className="text-[34pt] text-brand" />
                        </div>

                        <div className="max-h-[30mm] min-h-[8mm] flex-1" />

                        <div className="flex justify-between gap-8 px-[18mm] text-[10pt] tracking-[0.02em]">
                            <div>
                                <p className="font-bold tracking-[0.15em]">
                                    ISSUED TO:
                                </p>
                                <p className="mt-2">{sale.customer}</p>
                                {sale.customer_address && (
                                    <p className="whitespace-pre-line">
                                        {sale.customer_address}
                                    </p>
                                )}
                                {sale.customer_phone && (
                                    <p>{sale.customer_phone}</p>
                                )}
                            </div>

                            {/* Labels and values in two columns, both right
                                aligned, as the design has them. */}
                            <dl className="flex gap-6 text-right leading-relaxed">
                                <div className="font-bold tracking-[0.15em]">
                                    <dt>INVOICE NO:</dt>
                                    <dt>DATE:</dt>
                                    <dt>PAYMENT:</dt>
                                </div>
                                <div className="font-bold">
                                    <dd className="font-mono">{sale.number}</dd>
                                    <dd>{sale.sold_on}</dd>
                                    <dd>
                                        {sale.payment_method_label}
                                        {sale.bank && ` · ${sale.bank}`}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {/* No rules between rows: the design separates them
                            with space, and rules only under the head and above
                            the money. */}
                        <div className="max-h-[28mm] min-h-[8mm] flex-1" />

                        <div className="px-[18mm]">
                            <table className="w-full border-collapse text-[10pt] tracking-[0.02em]">
                                <thead>
                                    <tr className="text-left align-bottom">
                                        <th className={headCell}>
                                            DESCRIPTION
                                        </th>
                                        <th
                                            className={cn(
                                                headCell,
                                                'w-32 text-right',
                                            )}
                                        >
                                            UNIT PRICE
                                        </th>
                                        <th
                                            className={cn(
                                                headCell,
                                                'w-20 text-right',
                                            )}
                                        >
                                            QTY
                                        </th>
                                        {discount > 0 && (
                                            <th
                                                className={cn(
                                                    headCell,
                                                    'w-28 text-right',
                                                )}
                                            >
                                                DISCOUNT
                                            </th>
                                        )}
                                        <th
                                            className={cn(
                                                headCell,
                                                'w-36 text-right',
                                            )}
                                        >
                                            TOTAL
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((line) => (
                                        <tr key={line.id}>
                                            <td className={rowPadding}>
                                                {line.product}
                                            </td>
                                            <td
                                                className={cn(
                                                    rowPadding,
                                                    'text-right tabular-nums',
                                                )}
                                            >
                                                {format(line.unit_price, {
                                                    bare: true,
                                                })}
                                            </td>
                                            <td
                                                className={cn(
                                                    rowPadding,
                                                    'text-right tabular-nums',
                                                )}
                                            >
                                                {line.quantity}
                                            </td>
                                            {discount > 0 && (
                                                <td
                                                    className={cn(
                                                        rowPadding,
                                                        'text-right tabular-nums',
                                                    )}
                                                >
                                                    {line.discount === 0
                                                        ? '—'
                                                        : format(
                                                              line.discount,
                                                              {
                                                                  bare: true,
                                                              },
                                                          )}
                                                </td>
                                            )}
                                            <td
                                                className={cn(
                                                    rowPadding,
                                                    'text-right tabular-nums',
                                                )}
                                            >
                                                {format(line.net_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* The money lands once, on the last page — a total on
                            page one of three is a total of nothing. */}
                        {last ? (
                            <div className="mt-2 px-[18mm] text-[10pt]">
                                <div className="flex items-baseline justify-between border-t-2 border-[#999] pt-4 font-bold tracking-[0.15em]">
                                    <span>SUBTOTAL</span>
                                    <span className="w-36 text-right tracking-normal tabular-nums">
                                        {format(subtotal)}
                                    </span>
                                </div>

                                {discount > 0 && (
                                    <div className="mt-2 flex items-baseline justify-end gap-8">
                                        <span>Discount</span>
                                        <span className="w-36 text-right tabular-nums">
                                            −{format(discount)}
                                        </span>
                                    </div>
                                )}

                                <div className="mt-3 flex items-baseline justify-end gap-8 text-[15pt] font-bold">
                                    <span className="tracking-[0.05em]">
                                        TOTAL
                                    </span>
                                    <span className="w-36 text-right tabular-nums">
                                        {format(total)}
                                    </span>
                                </div>

                                {/* Beyond the design, and only when it says
                                    something the total does not: an invoice
                                    settled in full needs no line telling the
                                    customer so. */}
                                {paid !== total && (
                                    <>
                                        <div className="mt-4 flex items-baseline justify-end gap-8">
                                            <span>Paid</span>
                                            <span className="w-36 text-right tabular-nums">
                                                {format(paid)}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex items-baseline justify-end gap-8 font-bold">
                                            <span>Balance due</span>
                                            <span className="w-36 text-right tabular-nums">
                                                {format(total - paid)}
                                            </span>
                                        </div>
                                    </>
                                )}

                                {/* A converted invoice has to say what it was
                                    converted at, or nobody can reconcile it
                                    against the books it was written in. */}
                                {currency !== base && (
                                    <p className="mt-4 text-right text-[8pt] text-[#333]/70">
                                        Amounts in {currency} at 1 {currency} ={' '}
                                        {(
                                            printed.rate / RATE_SCALE
                                        ).toLocaleString(undefined, {
                                            maximumFractionDigits: 2,
                                        })}{' '}
                                        {base}
                                        {printed.rate_on &&
                                            ` · ${printed.rate_on}`}
                                    </p>
                                )}

                                {sale.notes && (
                                    <p className="mt-8 max-w-[60%] text-[9pt] whitespace-pre-line text-[#333]/80">
                                        {sale.notes}
                                    </p>
                                )}
                            </div>
                        ) : null}

                        {/* Pressed, not set: off true, in a lighter ink, and
                            under the total rather than at the foot of the page
                            — it closes the invoice, so it goes on the sheet
                            that carries the money. */}
                        {last && (
                            <div className="mt-8 px-[18mm]">
                                <span className="ml-[20%] inline-block rotate-[10deg] text-brand-light">
                                    <Logo className="text-[22pt]" />
                                </span>
                            </div>
                        )}

                        <div className="min-h-[10mm] flex-1" />

                        {/* The design is a single sheet and says nothing
                            about pages; an invoice that runs to three has to —
                            in the invoice's own voice, and carrying the number,
                            because the one thing a loose second sheet needs to
                            say is which invoice it fell out of. */}
                        {pages.length > 1 && (
                            <footer className="px-[18mm]">
                                <div className="flex items-baseline justify-between border-t border-[#333]/25 pt-2 text-[8pt] font-bold tracking-[0.15em] text-[#333]/70">
                                    <span className="font-mono tracking-[0.08em]">
                                        {sale.number}
                                    </span>
                                    <span>
                                        PAGE {page + 1} OF {pages.length}
                                        {!last && ' · CONTINUED'}
                                    </span>
                                </div>
                            </footer>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
