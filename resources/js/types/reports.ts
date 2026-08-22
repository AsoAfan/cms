import type { MinorUnits } from '@/lib/money';

/** Mirrors App\Support\ReportPeriod::jsonSerialize(). */
export type ReportPeriod = {
    from: string;
    to: string;
    preset: string | null;
    label: string;
    days: number;
};

/** Mirrors App\Enums\ReportPreset. */
export type ReportPresetOption = { value: string; label: string };

/** The props the report screen and the dashboard share. */
export type PeriodProps = {
    period: ReportPeriod;
    presets: ReportPresetOption[];
};

/** Money over three horizons — App\Support\ReportPeriod::averages(). */
export type Averages = {
    per_day: MinorUnits;
    per_week: MinorUnits;
    per_month: MinorUnits;
};

/**
 * Mirrors App\Queries\CashFlowQuery. A cash view: outcome is what was paid
 * out in the window, so net is money left over, not profit.
 */
export type CashFlow = {
    /** What was sold on delivered invoices, paid for or not. */
    income: MinorUnits;
    /** What actually came in: taken at the till, plus repayments received. */
    collected: MinorUnits;
    purchases: MinorUnits;
    expenses: MinorUnits;
    outcome: MinorUnits;
    net: MinorUnits;
    days: number;
    averages: {
        income: Averages;
        outcome: Averages;
        net: Averages;
    };
};

/** The kinds of document an activity table lists, plus the combined view. */
export type ActivityKind = 'sale' | 'purchase' | 'expense';

/**
 * One document, normalised so a single table can render all three kinds —
 * mirrors a row from App\Queries\ActivityQuery.
 *
 * `label` is what the row is called (an invoice number, or an expense's title)
 * and `detail` is the supporting text beside it (the payment method, the
 * supplier, the expense's category).
 */
export type ActivityRow = {
    kind: ActivityKind;
    id: number;
    date: string;
    label: string;
    detail: string | null;
    /** Not yet in any total: ordered, or still on its way. */
    pending: boolean;
    total: MinorUnits;
};

/** Mirrors App\Queries\ActivityQuery::get(). */
export type Activity = {
    sales: ActivityRow[];
    purchases: ActivityRow[];
    expenses: ActivityRow[];
};
