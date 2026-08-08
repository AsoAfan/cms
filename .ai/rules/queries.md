---
paths:
  - 'app/Queries/**'
---

# Queries

## Report queries scope by document date, and only posted documents count
Every report query filters on the **document's own date** (`sales.sold_on`, `purchases.invoiced_on`, `expenses.spent_on`) and on `status = posted` — never on `stock_movements.occurred_at`.

That is what makes gross profit equal the sum of the per-line profits on those invoices. Filtering COGS by the movement date is the obvious alternative and drifts away from revenue at every period boundary, because a movement and its invoice can fall either side of one. Anchor both at the document and the report always describes exactly one set of invoices. `ReportAccuracyTest` pins this.

Only posted documents count: a draft has taken nothing off the shelf and nobody has paid.

Date columns are stored `Y-m-d 00:00:00`, so use `whereDate()` (as `TableQuery::dateRange()` does), not `whereBetween` on raw date strings — the latter silently drops the last day.

Bucketing for trend series happens in **PHP** via `ReportPeriod::fold()`, not in SQL. Group by the plain date column and fold; SQL date functions differ by driver and the alternative is one query shape per database.

Three arch tests guard the accounting — do not weaken them:
- expenses stay out of every query deriving COGS or a per-product margin;
- `ProfitReportQuery` may not reach for purchases (buying stock is not a cost until it sells);
- inventory queries stay blind to expenses.

Only `ProfitReportQuery` knows about both sides, and only at `net = gross − expenses`.
