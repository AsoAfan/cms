import type { MinorUnits } from '@/lib/money';

/** Mirrors App\Support\ReportPeriod::jsonSerialize(). */
export type ReportPeriod = {
    from: string;
    to: string;
    preset: string | null;
    label: string;
    days: number;
    interval: 'day' | 'week' | 'month';
};

/** Mirrors App\Enums\ReportPreset. */
export type ReportPresetOption = { value: string; label: string };

/** The props every report screen and the dashboard share. */
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

export type SalesReport = {
    revenue: MinorUnits;
    cost_of_goods_sold: MinorUnits;
    gross_profit: MinorUnits;
    invoice_count: number;
    units: number;
    average_invoice: MinorUnits;
    average_unit_price: MinorUnits;
    average_unit_cost: MinorUnits;
    average_unit_profit: MinorUnits;
};

export type PurchaseReport = {
    goods: MinorUnits;
    additional_costs: MinorUnits;
    total: MinorUnits;
    invoice_count: number;
    supplier_count: number;
    units: number;
    average_invoice: MinorUnits;
    average_unit_cost: MinorUnits;
};

export type ExpenseCategoryTotal = {
    id: number;
    name: string;
    total: MinorUnits;
    count: number;
};

export type ExpenseReport = {
    total: MinorUnits;
    count: number;
    categories: ExpenseCategoryTotal[];
    largest_category: string | null;
};

export type ProfitReport = {
    revenue: MinorUnits;
    cost_of_goods_sold: MinorUnits;
    gross_profit: MinorUnits;
    expenses: MinorUnits;
    net_profit: MinorUnits;
    invoice_count: number;
    units: number;
    days: number;
    averages: {
        income: Averages;
        outcome: Averages;
        net_profit: Averages;
    };
};

export type ProductProfitability = {
    id: number;
    name: string;
    code: string;
    units: number;
    revenue: MinorUnits;
    cost_of_goods_sold: MinorUnits;
    gross_profit: MinorUnits;
    average_unit_price: MinorUnits;
    average_unit_cost: MinorUnits;
};

export type SupplierSummary = {
    id: number;
    name: string;
    invoice_count: number;
    units: number;
    goods: MinorUnits;
    additional_costs: MinorUnits;
    total: MinorUnits;
    average_invoice: MinorUnits;
    last_invoiced_on: string | null;
};

export type PaymentMethodTotal = {
    method: string;
    label: string;
    invoice_count: number;
    revenue: MinorUnits;
};

export type InventoryReportRow = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    on_hand: number;
    value: MinorUnits;
    units_sold: number;
    last_sold_on: string | null;
    is_dead: boolean;
};

export type InventoryReport = {
    total_value: MinorUnits;
    total_units: number;
    stocked_count: number;
    dead_value: MinorUnits;
    dead_units: number;
    dead_count: number;
    products: InventoryReportRow[];
};

export type InventorySummary = {
    total_value: MinorUnits;
    dead_value: MinorUnits;
    dead_count: number;
};

/* Trend series — one row per bucket, always including the quiet ones. */

export type ProfitSeriesRow = {
    bucket: string;
    revenue: MinorUnits;
    cost_of_goods_sold: MinorUnits;
    gross_profit: MinorUnits;
    expenses: MinorUnits;
    net_profit: MinorUnits;
};

export type SalesSeriesRow = {
    bucket: string;
    revenue: MinorUnits;
    cost_of_goods_sold: MinorUnits;
    gross_profit: MinorUnits;
};

export type PurchaseSeriesRow = {
    bucket: string;
    goods: MinorUnits;
    additional_costs: MinorUnits;
    total: MinorUnits;
};

export type ExpenseSeriesRow = {
    bucket: string;
    total: MinorUnits;
};

export type RecentActivity = {
    sales: {
        id: number;
        number: string;
        date: string;
        status: string;
        total: MinorUnits;
    }[];
    purchases: {
        id: number;
        number: string;
        date: string;
        status: string;
        supplier: string;
        total: MinorUnits;
    }[];
    expenses: {
        id: number;
        category: string;
        date: string;
        reference: string | null;
        total: MinorUnits;
    }[];
};
