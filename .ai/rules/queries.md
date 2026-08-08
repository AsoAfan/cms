---
paths:
  - 'app/Queries/**'
---

# Queries

## Every figure in reporting is a cash view
`CashFlowQuery` is the whole of the arithmetic: `income` (posted sales), `outcome` (posted purchases + expenses), `net = income − outcome`, and each of the three averaged per day, week and month. (`ActivityQuery` lists the documents behind those figures but derives nothing new — see below.)

**Outcome is money paid out, not cost of goods sold.** A month with a big stock order reads as a loss even where every sale was profitable — the goods are on the shelf, not in the figures. That is the chosen trade: a shop owner can check three numbers daily, and no figure on the screen needs an accounting explanation. An arch test keeps the FIFO batch tables (`StockBatch`, `StockBatchConsumption`, `StockMovement`) and the inventory queries out of it, so the two views can never be mixed and a purchase counted twice — once when paid for, once when sold.

Six report screens and seven queries (sales, purchases, expenses, profit, product profitability, supplier summary, inventory report) were built and then **deleted** as layers nobody opened. Gross profit, COGS, margins, per-product profitability, supplier summaries and trend series are gone from reporting. Do not reintroduce any of them without asking; the FIFO ledger still records everything they would need, so each stays possible later.

`StockOnHandQuery` and `InventoryValuationQuery` are **not** reporting — they serve the domain (stock checks when posting a sale, valuation in tests) and stay.

## Scope by document date, posted documents only
Filter on the **document's own date** (`sales.sold_on`, `purchases.invoiced_on`, `expenses.spent_on`), never on `stock_movements.occurred_at`. The report then always describes exactly one set of documents, whichever side of a period boundary the ledger happened to move.

Only posted documents count: a draft has taken nothing off the shelf and nobody has paid. Expenses have no draft state — an expense is recorded once it is paid.

Date columns are stored `Y-m-d 00:00:00`, so use `whereDate()` (as `TableQuery::dateRange()` does), not `whereBetween` on raw date strings — the latter silently drops the last day.

`ReportAccuracyTest` pins every figure against a fixture worked out on paper first; a failure there means the report is wrong, not that the test drifted.

## ActivityQuery lists the documents behind the cash figures
Reporting is now two queries, not one. `CashFlowQuery` says how much; `ActivityQuery` says what it was made of — the sales, purchases and expenses in the period, one normalised row each (`kind`, `date`, `label`, `detail`, `draft`, `total`).

Both are scoped identically — the document's own date, `whereDate`, posted only — so a listed row and the tile above it can never describe different things. `ActivityQuery::get($period, limit:, drafts:)` opens that up for the dashboard alone: a handful of rows per kind, drafts included, because an unfinished invoice is work still to do rather than a figure. Nothing a draft contains reaches any total.

This is NOT a return of the deleted report queries — there is still no gross profit, COGS, margin, per-product profitability, supplier summary or trend series anywhere in reporting. It is a document list, and it stays one.

Totals are summed in SQL (`withSum` over the same line expressions `CashFlowQuery` uses), never by hydrating lines — a year of trading is three queries. Alias the aggregate to a name no model casts (`net_minor_units`, `goods_minor_units`) and cast with `(int)`; `withSum` applies no cast of its own.

The report screen sends the whole period unpaginated. The period is the bound: a report showing only the first few would not be a report.
