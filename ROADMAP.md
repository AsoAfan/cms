# Sales Management System — Phased Delivery Plan

Derived from the Project Requirements in [CLAUDE.md](CLAUDE.md).

Task IDs are `P{phase}.T{n}` — reference them in branches, commits, and issues
(e.g. `feat(P3.T4): FIFO inventory service`).

**Stack baseline:** Laravel 13 · Inertia 3 · React 19 · Tailwind 4 · shadcn (Base UI) ·
Pest 5 · Wayfinder · SQLite.

---

## Guiding Decisions

These resolve the tensions inside the requirements and are **settled**. Implement against
them rather than re-deciding per phase; revisit only if a phase turns up hard evidence
against one.

| Decision | Choice | Rationale |
| --- | --- | --- |
| Money storage | `bigInteger` minor units + `Money` cast | Floats break financial determinism |
| Quantity | `integer` — unsigned on document lines, **signed** on `stock_movements` | All items are countable whole units — no weights or volumes, so decimal rounding never enters stock or COGS. **Confirmed for curtains (P1):** each size is its own product counted in pieces, never a measured quantity, so a 117×137cm curtain is one whole piece. Movements are signed (+ receive / − issue); line quantities carry a `> 0` check constraint. |
| Costing method | **FIFO via purchase batches** | The only way to derive exact COGS and inventory valuation from transactions |
| Stock on hand | Derived from the `stock_movements` ledger | No stored balance that can drift; cache later only if proven slow |
| "Never store calculated values" | Exception for **recorded historical facts** | A sale line's unit price and a batch's landed unit cost are *facts at transaction time*, not caches. Totals, profit, and stock levels stay derived. |
| Counterparties | ~~Suppliers only~~ → **customers are named on every sale; purchases name nobody** | Reversed at the user's direction, both ways. Selling on account needs a buyer to file the loan under, so `sales.customer_id` is required and counter trade goes to a seeded **Walk-in** customer. A purchase's supplier, meanwhile, was optional, had to be skipped explicitly on every invoice, and nothing downstream read it — so the column is gone. `suppliers` remains as a list, just not on the invoice. |
| Catalogue depth | **Flat: a product is the stock-keeping entity.** No variants, options, categories, brands, units, `code` or `is_active` | Each was built and then removed as a layer to maintain without a matching gain. Goods that differ are separate products with separate **names**, and the name carries the unique index. All are additive to bring back. |
| Multi-warehouse | **Not built, and no placeholder columns** | The ledger was to carry a nullable `location_id` against a `locations` table that does not exist. An unused column is the same speculative layer units and variants turned out to be; adding one later is a single additive migration where NULL means "the one location". |
| Multi-currency | ~~Not built~~ → **Currencies are rows, one is the base, conversion happens once on the way in** | Reopened in Phase 10 at the user's direction. This trade is mostly in dinars and sometimes in dollars, so "one currency" was never true of the money — only of the storage, which is what it remains: every amount is still base-currency minor units, and the ledger, the FIFO costing and every report query were untouched. Which currencies exist and which is the base are managed on a screen; **the base is fixed once money is recorded**, because no single rate could restate history. |
| Returns | Scaffolding only in this scope | The requirements list returns as "future-ready", not required. P5.T8 lands the schema hooks so adding them later is additive, never a migration of posted data. |
| Rounding remainders | Largest-remainder allocation, residual to the last line by `id` | Splitting $100 across 3 units gives 3333/3333/3334. A fixed, deterministic rule guarantees allocated costs sum **exactly** to the invoice total, so reconciliation never drifts by a cent. Applies to landed costs and invoice-level discounts alike. |

---

## Phase 0 — Foundation & Conventions

**Goal:** a running authenticated app shell with the primitives every later phase depends on.

- [x] **P0.T1** — Auth scaffolding (login, logout, password reset). **Decided: hand-rolled starter-kit-style controllers, not Fortify** — no new dependency, and the flow stays editable. No registration route: accounts come from the seeder until P9.T1.
- [x] **P0.T2** — App shell layout: sidebar nav, topbar, breadcrumbs, flash-message toasts, responsive.
- [x] **P0.T3** — Install the shadcn set to be reused throughout: table, dialog, form (→ `field` in this style), input, select, combobox, card, badge, dropdown-menu, plus sidebar/breadcrumb/toast/empty and friends. The registry has no `date-range-picker`, so it is composed from `calendar` + `popover` in `components/date-range-picker.tsx`.
- [x] **P0.T4** — `Money` value object + Eloquent cast + `formatMoney` TS helper. Unit-tested (62 cases, incl. largest-remainder allocation).
- [x] **P0.T5** — Base conventions: `App\Actions\*` / `App\Services\*` / `App\Queries\*` structure, Form Requests, API Resources, enum location — enforced by `tests/Unit/ArchTest.php` and recorded in `.ai/rules`.
- [x] **P0.T6** — Shared frontend primitives: `DataTable` (sort/filter/paginate), `PageHeader`, `FormField`, `EmptyState`, `MoneyDisplay`, `DateRangePicker`.
- [x] **P0.T7** — Server-side pagination/filter/sort helper trait feeding `DataTable` (`TableQuery` + `InteractsWithTables` on the base controller).
- [x] **P0.T8** — Record conventions to `.ai/rules` via the `record-rule` tool (money handling, actions, ledger invariants).
- [x] **P0.T9** — CI: Pint, Larastan, ESLint, `tsc --noEmit`, Pest — all green via `composer ci:check`.

**Done when:** you can log in, see an empty dashboard inside the shell, and CI is green. ✅

> Added dependencies: `date-fns` and `react-day-picker` (both pulled in by the shadcn `calendar`,
> which the date-range picker needs). There is no JS test runner in the project, so `formatMoney`
> and `parseMoney` are covered only by `tsc`; the PHP `Money` is exhaustively unit-tested.

---

## Phase 1 — Product Catalog

**Goal:** a product list simple enough to keep current, and precise enough to cost from.

- [x] **P1.T1** — ~~`categories`, `brands`, `units`~~ — built, then all removed.
- [x] **P1.T2** — `products`. ~~`product_variants`~~ — built, then removed: a product **is** the stock-keeping entity, with its own name, cost and price.
- [x] **P1.T3** — ~~`attributes`, `attribute_values`, pivot~~ — built with a full variant matrix builder, then removed.
- [x] **P1.T4** — Model, factory, seeder (flat curtain catalogue, one row per size).
- [x] **P1.T5** — Pricing: ~~`default_cost_price` / `default_selling_price` as *defaults only*, null meaning "not priced yet"~~ → **`cost_price` / `selling_price`, both required** (P10). Transactions still record their own prices, so editing either never rewrites history. "Not priced yet" as a storable state bought a null to handle on every screen, every prefill and every report, for a case that helped nobody.
- [x] **P1.T6** — n/a — no reference data left to manage.
- [x] **P1.T7** — Product CRUD: one form, six fields.
- [x] **P1.T8** — Product list: search across name and description, sortable prices and **quantity on hand**, pagination, and per-row Buy/Sell — each writing a real document **at `ordered`**, so the catalogue takes the order and the document's own screen moves the stock. ~~status filter~~ — removed with `is_active`.
- [x] **P1.T9** — Feature tests for product CRUD, pricing precision, name uniqueness, ledger-derived quantity and its sort.

**Done when:** ~~you can create a product with 3 items across 2 options and find it by code~~ →
**you can create a product, find it by name, and see what is on the shelf.** ✅

> **The catalogue is flat.** Categories, brands, units, variants and options were each
> built and then removed at the user's direction — every one was a screen to maintain
> without a matching gain. Where goods differ, each difference is its own product:
> a curtain at 117×137 and one at 168×183 are two rows with two names.
>
> This also settles the quantity question: a product is counted in whole pieces, never
> measured, so the integer-quantity Guiding Decision holds and Phase 3's FIFO costing
> stays exact. If anything ever needs selling by the metre or by area, reopen that
> decision **before** building the ledger.
>
> Each removed layer is additive to bring back — none of them left a trace in the schema.

**Blocks:** Phases 3–5.

---

## Phase 2 — Suppliers

**Goal:** the counterparty you buy from. Independent of Phase 1 — parallelizable.

- [x] **P2.T1** — `suppliers` migration + model + factory + seeder.
- [x] **P2.T2** — ~~`customers`~~ — **not built.** Sales are over the counter and are not tied to a named buyer.
- [x] **P2.T3** — Supplier CRUD + list with search across name, phone, email and address.
- [x] **P2.T4** — ~~Customer CRUD~~ — not built.
- [x] **P2.T5** — ~~Detail pages with placeholder transaction-history panels~~ — deferred. An empty panel is not worth a screen; purchase history lands on the supplier when there is history to show (P7).
- [x] **P2.T6** — Feature tests.

**Done when:** you can add a supplier and find them by name or phone. ✅

> **No customers, by decision.** This changes two later phases, and neither should
> quietly reintroduce one:
>
> - **P5 (Sales)** — a sale has no `customer_id`. It is a dated document with lines,
>   payments and a total.
> - **P7 (Reporting)** — there is no `CustomerSummaryQuery`; sales analysis is by
>   product, period and payment method. `SupplierSummaryQuery` is unaffected.
>
> If named customers are ever wanted, they arrive as their own table plus a
> **nullable** `customer_id` on sales — additive, never a migration of posted data.
>
> Only `name` is required on a supplier: often that is all you have when you first
> write one down.

---

## Phase 3 — Inventory Ledger *(architectural core)*

**Goal:** one append-only source of truth for stock and cost. Build before anything writes stock.

- [x] **P3.T1** — `stock_movements`: product, signed qty, type enum, polymorphic `source`, `occurred_at`, `reason`. **No `location_id`** — see below.
- [x] **P3.T2** — `stock_batches`: product, `received_movement_id`, `quantity_received`, `unit_cost` (landed), `received_at`. Points at the creating *movement* rather than a purchase line, so receipts from adjustments and (from P4) purchases both work with no special case.
- [x] **P3.T3** — `stock_batch_consumptions`: batch, consuming movement, qty — the FIFO allocation record that produces exact COGS. The cost is **not** copied onto the row; the batch's `unit_cost` is immutable, so a second copy could only ever drift.
- [x] **P3.T4** — `InventoryService`: `receive()`, `issue()` (FIFO consumption), `adjust()`.
- [x] **P3.T5** — `StockOnHandQuery` — quantity per product at any date, per product or across the catalogue.
- [x] **P3.T6** — `InventoryValuationQuery` — value on hand at any date, rewinding both receipts and consumptions.
- [x] **P3.T7** — Insufficient-stock guard + `InsufficientStockException` carrying what was asked for and what was there.
- [x] **P3.T8** — ~~Stock screen: on-hand and value per product, total valuation, count dialog~~ — built, then removed. Quantity on hand is a column on the catalogue instead, still derived from the ledger. `InventoryService::adjust()` remains, with no UI calling it; stock moves only through purchases and sales.
- [x] **P3.T9** — 34 ledger tests: FIFO across multiple batches, partial consumption, exact-boundary consumption, same-day tie-breaking, ten-batch runs, out-of-order dates, back-dated receipts, negative-stock rejection, and as-at queries.

**Done when:** buying 10 @ $5 then 10 @ $7 and issuing 15 yields COGS of exactly $85 and
on-hand of 5 @ $7. ✅ — pinned by the first test in `InventoryServiceTest`.

> **Deviations, both deliberate:**
>
> - **No `location_id`.** The Guiding Decision called for a nullable column against a
>   `locations` table that does not exist. An unused column is the same speculative
>   layer that units, categories, brands and variants each turned out to be. Adding a
>   nullable column later is one additive migration, and every existing row reading
>   NULL means "the one location" — no backfill.
> - **Batches point at the creating movement, not a purchase line.** Purchases do not
>   exist until P4, and adjustments create stock too. The movement already carries the
>   polymorphic `source`, so nothing is lost.
>
> **Costing behaviour worth knowing before P4/P5:** an issue draws only on batches
> received on or before its own date, and a receipt back-dated after the fact does not
> retroactively rewrite allocations already settled. That is perpetual FIFO, and it is
> what keeps the ledger append-only.

> This phase is the risk concentration. If FIFO/COGS is wrong, every figure in Phase 7 is
> wrong. Over-test it.

---

## Phase 4 — Purchasing

- [x] **P4.T1** — `purchases` (~~optional supplier~~ — removed, number, invoice date, status, notes). No stored total — it is the sum of its parts.
- [x] **P4.T2** — `purchase_lines` (product, qty, unit cost, discount). The discount is an absolute amount, not a percentage: a percentage needs rounding to become money, and the amount is what the invoice actually says.
- [x] **P4.T3** — `purchase_additional_costs` (freight, duty) + `CostAllocationMethod` (by value / by quantity).
- [x] **P4.T4** — ~~`PostPurchaseAction`~~ → **`ReceivePurchaseAction`**: spread the invoice-wide costs, receive each line at its landed total, all inside one transaction. `assertReconciles()` throws if the allocation ever fails to equal the invoice to the penny.
- [x] **P4.T5** — ~~Draft → Posted, one-way; posted purchases refuse edit and delete~~ → **ordered → on the way → proceed, and the status moves both ways.** Reaching `proceed` receives the goods, leaving it puts them back, and an edit re-runs both. What is refused is touching an invoice whose goods have already been sold on.
- [x] **P4.T6** — Purchase entry form: line table, product select, Enter on the last line adds another, live goods/freight/total.
- [x] **P4.T7** — Purchase list with the invoice written in a **drawer over it**, and an invoice screen that reads like the piece of paper. ~~Landed cost per line and the batches it made~~ — removed from the screen: that is the ledger's side, not the invoice's.
- [x] **P4.T8** — 19 allocation tests plus 18 screen tests, including a table of awkward invoices asserting inventory rises by exactly the invoice total.

**Done when:** posting a purchase raises stock and sets the correct landed unit cost per batch. ✅

> **The ledger changed to make this exact.** Landed cost rarely divides evenly: $40.00 over
> 3 units is $13.33 each with a penny left over. A receipt now allocates its total across
> the individual units and groups equal costs, so that line becomes two batches — 2 at
> $13.33 and 1 at $13.34 — and inventory rises by exactly $40.00. `stock_batches` lost its
> unique index on `received_movement_id` to allow it, and `InventoryService` gained
> `receiveAtTotalCost()`.
>
> **Reversal replaced the reversal document.** Moving an invoice back down the statuses
> undoes exactly what it put in the ledger, rather than writing an offsetting document. It
> is refused once a batch has been sold from, because that stock is somebody else's cost
> now — `StockAlreadyConsumedException` says so instead of letting a batch vanish from
> under a sale.

---

## Phase 5 — Sales

- [x] **P5.T1** — `sales` (number, date, status, payment method, ~~no customer~~ → **required `customer_id`** and `amount_paid`, see Phase 11).
- [x] **P5.T2** — `sale_lines` (product, qty, unit price, discount).
- [x] **P5.T3** — ~~`payment_methods` table + `sale_payments`~~ → a `PaymentMethod` **enum** on the sale. A reference table meant another screen to manage; split and part payment are not needed yet and arrive additively.
- [x] **P5.T4** — ~~`PostSaleAction`~~ → **`IssueSaleAction`**: FIFO issue through `InventoryService`, pre-flighting every line so all shortages are reported at once. Cost of sale is **derived** from the batch consumptions, never stored.
- [x] **P5.T5** — ~~Draft → Posted, one-way; posted sales refuse edit and delete~~ → **ordered → on the way → proceed, and the status moves both ways.** Stock leaves at `on the way`, because goods handed to a driver are off the shelf whatever happens next. Editing a sale that has gone out reverts and re-issues in one transaction.
- [x] **P5.T6** — ~~Till-style entry: type a product name and press Enter, the same one again adds one more~~ → **a product dropdown per line, as on a purchase**, each option carrying what is on the shelf. Enter on the last line still adds another, and the on-hand warning and running total stayed. Two ways to put a product on a document was one too many, and the one that reads back what you picked is the one that survived.
- [x] **P5.T7** — Sale list with the sale rung up in a **drawer over it**, and an invoice screen that reads like the piece of paper. ~~Per-line cost and profit~~ → in the summary block instead, under one shared currency dropdown: the lines are what the customer bought, the summary is what the shop made.
- [x] **P5.T8** — ~~Return-ready scaffolding (`parent_sale_id`, `SaleType`)~~ — **not built.** A nullable column with no UI is the same speculative layer `location_id` and units turned out to be; returns arrive additively.
- [x] **P5.T9** — 38 tests: stock decrease, FIFO cost correctness, oversell rejection, all-or-nothing issue, moving the status back and forth, and the screens.

**Done when:** a sale takes stock out at the right cost and shows what it made. ✅

> Verified end to end in the running app: buying 10 at $18 with $20 freight lands stock at
> $20 a unit; selling 3 at $44 gives takings of $132, **cost of $60 — not $54** — and a
> gross profit of $72, with 7 left worth $140. The freight from the purchase is inside the
> cost of the sale, which is the whole point of the landed-cost work in Phase 4.
>
> ~~**Deferred:** there is still no reversal for a posted purchase or sale.~~ → **Settled:**
> a mistake is undone by moving the document back down its statuses, or by editing it,
> which reverts and re-applies the ledger in one transaction. Neither is possible once
> another document has consumed the stock, which is the one case a correcting document
> would still be needed for.

---

## Phase 6 — Expenses

Small and fully independent — good parallel work alongside Phase 4/5.

- [x] **P6.T1** — `expense_categories` migration + seeder (Rent, Salaries, Transport, Utilities, Marketing, Miscellaneous). A table rather than an enum: every business names its own costs.
- [x] **P6.T2** — `expenses` (category, title, amount, date, payment method, notes). A required title rather than a receipt reference: what the money went on is what identifies the row a month later.
- [x] **P6.T3** — Expense CRUD + list with a date-range picker, category and payment filters, and a running total for whatever is filtered.
- [x] **P6.T4** — Category management — a dialog on the expenses screen rather than a screen of its own. A category with expenses against it cannot be deleted.
- [x] **P6.T5** — 18 feature tests, plus **two** arch tests: expenses never reach inventory, and inventory never reaches expenses.

**Done when:** you can record what it costs to trade and total it over any period. ✅

> **Why the arch test matters.** Buying goods increases inventory and only becomes a cost
> when they sell; rent is a cost the moment it is paid. Letting expenses touch the ledger
> would double-count the first and mistime the second. In P7, expenses enter only at the
> last subtraction: net profit = gross profit − expenses, never inside COGS.
>
> This is also the first use of the `DateRangePicker` built in P0.T6, wired to `from`/`to`
> query params through `TableQuery::dateRange()`. `TableQuery` gained `sum()` so a filtered
> list can show its own total — P7's report screen will want the same.

---

## Phase 7 — Reporting Engine

**Goal:** every figure derived from transactions, over any date range.

- [x] **P7.T1** — `ReportPeriod` value object: inclusive range, presets, comparison period and averages. Immutable and facade-free, so all of it is unit-testable without a database.
- [x] **P7.T2** — `ReportPeriodFilter` — preset select + date-range picker, with the window held in the query string and nowhere else.
- [x] **P7.T3** — `SalesReportQuery` — revenue, COGS, gross profit, invoice count, average selling price, plus a split by payment method and a daily series.
- [x] **P7.T4** — `PurchaseReportQuery` — goods, freight and duty, total, average landed buying cost.
- [x] **P7.T5** — `ExpenseReportQuery` — totals by category, including the categories nothing was spent on.
- [x] **P7.T6** — `ProfitReportQuery` — gross profit (revenue − COGS), net profit (− expenses). Composes the sales and expense reports rather than merging their SQL.
- [x] **P7.T7** — `ProductProfitabilityQuery` — units, revenue, cost and profit per product, costed from the batches each sale actually consumed.
- [x] **P7.T8** — `SupplierSummaryQuery`. (No customer summary — see Phase 2.)
- [x] **P7.T9** — `InventoryReportQuery` — on-hand value as at the period end, plus dead stock.
- [x] **P7.T10** — Averages per day, week and month, on `ReportPeriod` and surfaced through `CashFlowQuery`.
- [x] **P7.T11** — Dashboard: the three figures with period-on-period change, plus recent activity.
- [x] **P7.T12** — CSV export of the report, through `App\Support\Csv`.
- [x] **P7.T13** — 19 accuracy tests against a hand-calculated fixture, 33 `ReportPeriod` unit tests, 16 CSV tests, 32 screen and export tests.
- [x] **P7.T14** — **Cut back to one screen** (8 Aug 2026). T3–T9, T11 and most of T12 were deleted; see below.

**Done when:** every figure on the report is derived from the transactions in its window,
over any date range. ✅ — pinned by `ReportAccuracyTest` against a fixture worked out on
paper first.

> **What the reporting engine is now.** One screen and one query. `CashFlowQuery` returns
> `income` (posted sales), `outcome` (posted purchases + expenses) and `net`, plus each of
> the three averaged per day, week and month. The screen shows the totals on one tab and
> the averages on the other; the dashboard shows the same three figures and the recent
> activity feed. Nothing else.
>
> **What was deleted, and why.** T3–T9 built seven report queries behind six screens —
> sales, purchases, expenses, products, inventory, and a summary with a P&L and a trend
> chart. It was accurate and nobody would have opened it. Simplicity beat completeness:
> the whole point is three numbers a shop owner checks daily. Gone with them:
> `SalesReportQuery`, `PurchaseReportQuery`, `ExpenseReportQuery`, `ProfitReportQuery`,
> `ProductProfitabilityQuery`, `SupplierSummaryQuery`, `InventoryReportQuery`,
> `ReportInterval`, `ReportPeriod`'s bucketing, `ReportNav`, `TrendChart`, and five of
> the six CSV exports. The FIFO ledger still records everything any of them needed, so
> each stays possible later — ask before rebuilding one.
>
> **The report is a cash view, not a profit and loss** — a deliberate change of meaning
> at T14. Outcome is what was paid out in the window, so a month with a big stock order
> reads as a loss even where every sale was profitable. Cost of goods sold no longer
> appears anywhere in reporting, which is why the old acceptance case (gross profit
> equals the sum of per-line profits) has been retired with the query that computed it.
> An arch test keeps the FIFO batch tables out of `CashFlowQuery` so the two views can
> never be mixed and a purchase counted twice.
>
> **What survived unchanged:**
>
> - **Scoping by the document's own date** (`sold_on`, `invoiced_on`, `spent_on`), never
>   by when the ledger moved, so a report always describes exactly one set of documents.
> - **Only posted documents count.** A draft has taken nothing off the shelf and nobody
>   has paid.
> - **Averages are arithmetic on a period, not a query.** They live on `ReportPeriod`
>   where they are unit-testable without a database. A month is the mean **30.4375**
>   days, held as an exact fraction, so a rate measured over a week and one measured
>   over a year are directly comparable.
> - **The window lives in the URL**, so a report can be bookmarked or sent to someone.
> - `Csv` escapes leading `=`, `+`, `@` in text fields — a supplier named after a formula
>   is a real attack, not a hypothetical — while leaving negative numbers alone.
>
> **Deferred to P8:** the report runs unindexed date scans over sales, purchases and
> expenses. That is P8.T4's index audit, not a correctness problem — and it is now three
> aggregates rather than a screenful.

---

## Phase 8 — UX & Performance Pass

- [ ] **P8.T1** — Keyboard shortcuts + command palette (`Ctrl+K`) for navigation and new-record actions.
- [ ] **P8.T2** — Full keyboard flow through purchase and sale entry, no mouse.
- [ ] **P8.T3** — Inertia deferred props + animated skeletons on report and dashboard pages.
- [ ] **P8.T4** — Index audit; `EXPLAIN` on report queries against seeded volume data.
- [ ] **P8.T5** — Optional read-model / materialized daily summary — **only if** P8.T4 proves a need.
- [ ] **P8.T6** — Mobile and tablet responsive pass.
- [ ] **P8.T7** — Validation-message and empty-state review across all forms.

---

## Phase 9 — Hardening

- [ ] **P9.T1** — Roles/permissions + Policies on every model.
- [ ] **P9.T2** — Audit trail on posted financial documents.
- [ ] **P9.T3** — Demo seeder: 12 months of realistic transactions.
- [ ] **P9.T4** — Arch tests: no business logic in controllers, actions return typed results.
- [ ] **P9.T5** — Backup/restore + deployment runbook.

---

## Phase 10 — Currency

**Goal:** trade in whatever currencies the business handles, keep the books in one of them,
and never let a figure below the form learn that a second currency exists.

- [x] **P10.T1** — `currencies` table: code, name, symbol, `fraction_digits`, `is_base`.
      Which currencies matter is a fact about a business, not a deployment, so they are rows.
      `fraction_digits` is display only — storage stays two decimal places for every currency,
      so `Money::allocate()` keeps landed costs exact and all 62 `MoneyTest` cases still hold.
      Dinars simply show none.
- [x] **P10.T2** — `App\Support\ExchangeRates`: framework-free, immutable, and the only thing
      that converts. A rate is base-major per foreign-major as a fixed-point integer scaled by
      10⁶, so $18.50 at 1,320.5 is exactly 24,429.25 dinars — one multiply, one divide, no float.
      38 unit tests.
- [x] **P10.T3** — `exchange_rates` + `CurrencyService`: the newest rate **on or before** a date,
      so a rate stands until a newer one is recorded and a back-dated invoice costs at the rate
      of its own day. FK to `currencies.code`, cascading, so a removed currency takes its quotes
      with it.
- [x] **P10.T4** — Settings → Currencies: add a currency, mark one as the default, remove one,
      and record what each is worth. **Entered by hand — nothing fetches a rate.**
- [x] **P10.T5** — `ConvertsToBaseCurrency` on every money-taking Form Request. **The one rule
      the whole phase rests on: conversion happens once, here.**
- [x] **P10.T6** — `currency` + `exchange_rate` on `purchases`, `sales` and `expenses` — what
      the money changed hands in and the rate applied, as recorded facts.
- [x] **P10.T7** — `<MoneyInput>`: an amount with its own currency dropdown, which **converts
      what is in the box**. Live "≈" preview using the same integer rounding as the server.
- [x] **P10.T8** — Topbar switcher to read the whole app in another currency, labelled with the
      rate. Display only; storage never moves.
- [x] **P10.T9** — Product prices required and renamed (see P1.T5).
- [x] **P10.T10** — 90 currency tests: 38 on the value object, 17 on entry, 18 on currency
      management, 12 on the rates screen, plus two arch tests.

**Done when:** an invoice typed in dollars stores dinars, and the reports do not change shape. ✅

> **What made this cheap.** Storage stays single-currency. Because every amount reaches the
> database already in base-currency minor units, `InventoryService`, every `App\Queries\*`, the
> receive/issue actions and `App\Support\Csv` needed **no changes at all** — the FIFO ledger and
> every report are as currency-blind as they were before. Two arch tests keep it that way:
> reports and the ledger may not touch `ExchangeRates` or `CurrencyService`, and nothing may
> fetch a rate over the network.
>
> **The rate source changed twice, and the second answer is the settled one.** A manual rates
> screen was built, replaced by a scheduled `currency:sync` against a free feed, then the feed
> was removed and the screen restored — all at the user's direction. The reason it stuck:
> the official rate and the rate this business actually trades at are rarely the same number,
> and it is the second one that costs an invoice correctly. `SyncExchangeRatesAction`, the
> command, the schedule, `RateSource` and the `source` column are all gone. Ask before adding
> a feed back.
>
> **Moving the base is refused once there is money on record.** Every stored amount is minor
> units of the base and each was recorded at a rate current when it happened, so no single rate
> could restate the history — converting at today's would quietly rewrite what past invoices
> cost. While the books are empty it moves freely, and it deletes every rate on the way, because
> those quoted the old base. If re-denominating a live set of books is ever wanted, that is a
> conversion run with its own audit trail, not a setting.
>
> **Deviations worth knowing:**
>
> - **One `exchange_rate` per document.** An amount on it is either base or the document's own
>   currency, and one rate covers both. A genuinely mixed-currency invoice — three currencies on
>   one document — would have to move the rate down to the individual amount.
> - **The dropdown converts, it does not relabel.** $18.50 switched to dinars becomes 24,420.
>   The alternative silently turns eighteen dollars into eighteen dinars.
> - **`CurrencyService` is a singleton**, because a purchase form posts a dozen amounts on one
>   date and each would otherwise repeat the same lookups.
>
> **Two real bugs the tests caught**, both the `Y-m-d 00:00:00` date-storage trap already
> recorded in `.ai/rules/queries.md`: `max('effective_on')` hands back a datetime string that
> `whereDate` can never match, and `updateOrCreate` keyed on a date silently misses and inserts
> a duplicate until the unique index refuses it.

---

## Phase 11 — Customers & Credit

**Goal:** a sale belongs to somebody, and somebody can owe you for it.

This reverses the **Counterparties** Guiding Decision at the user's direction. It cost no
migration of posted documents, exactly as that decision predicted: customers arrive as
their own table plus columns on `sales`.

- [x] **P11.T1** — `customers` migration + model + factory + seeder. The mirror of
      `suppliers`: only the name is required, and the name is **unique** because a customer
      has to be pickable on the sale screen and recognisable at the top of a statement. A
      seeded **Walk-in** customer carries counter trade.
- [x] **P11.T2** — `sales.customer_id`, **required**. Every sale names a buyer; the form
      opens on Walk-in, so the requirement costs no keystrokes. `restrictOnDelete`, and
      `CustomerController::destroy` says so rather than letting the key 500. Existing rows
      were attributed to Walk-in by the migration, and their `amount_paid` backfilled from
      their own lines — they were paid at the till.
- [x] **P11.T3** — `sales.amount_paid`: what was handed over at the time, a recorded fact
      like a line's `unit_price`. Paid in full / part paid / on account all fall out of one
      column. **"Paid in full" is settled from the lines on the server**, so the stored
      figure is the invoice exactly and the client never converts a total.
- [x] **P11.T4** — `customer_payments` + `customer_payment_allocations`. A payment is applied
      to **named invoices**, so "what is left on SAL-00031" is a fact rather than an
      inference. One payment can clear several invoices; one invoice can take several
      payments.
- [x] **P11.T5** — `RecordCustomerPaymentAction`, holding the three invariants a column
      cannot: allocations sum to exactly the payment, no invoice takes more than it owes,
      and only delivered invoices can be paid. No update action — a payment is either what
      came in or it is not, so a wrong one is deleted (which unwinds its allocations) and
      recorded again.
- [x] **P11.T6** — `CustomerBalanceQuery`: the only place a debt is worked out. No balance
      column anywhere.
- [x] **P11.T7** — Customer CRUD, plus a **statement screen** (`show`, which suppliers do not
      have): what they bought, what they paid, what is left on each invoice. Payments are
      recorded from there, because that is the only screen showing what is owed on what.
- [x] **P11.T8** — Payment dialog that fills the **oldest invoices first** the moment an
      amount is typed, with every row still editable, and the shortfall on screen before the
      button rather than in an error after it.
- [x] **P11.T9** — Reporting gains a second income figure. `income` is what was sold;
      `collected` is what came through the door. Plus **Owed to you** on the report and the
      dashboard — a position, not a flow, which is why it sits outside the period query.
- [x] **P11.T10** — 50 tests: balance derivation, allocation invariants, overpayment refusal,
      deletion putting a debt back, the two income figures diverging, and two arch tests
      keeping repayments out of the stock ledger.

**Done when:** a customer can take goods without paying, and you can see what they owe and
take money off it. ✅

> **When a debt begins: delivery, not dispatch.** A sale counts as income and as a debt at
> `proceed`, when the goods are the customer's. Stock still leaves one status earlier, at
> `on_the_way` — goods with a driver are off the shelf but not yet sold. That is the one
> place the ledger and the money view deliberately part company, and both halves say so.
> Money against an order not yet delivered is a **deposit**: it sits on the sale as
> `amount_paid` and owes nobody anything until delivery.
>
> **Two income figures, on purpose.** A sale on account is income the day it is delivered and
> cash on the day it is paid, which may be months later. Net is still measured on income, so
> a shop that sells well on credit does not read as a shop that is failing. This is the first
> figure added to the report since it was cut back at P7.T14, and it earns its place: on
> credit terms "what did I sell" and "what did I take" are two different questions.
>
> **Not built, deliberately** — ask before adding any of these:
>
> - **No credit limit and no ageing.** A balance and an invoice list answer "who owes me
>   what"; a limit is a policy nobody has stated, and 30/60/90 buckets are a report nobody
>   has asked for. Both are additive over what is here.
> - **No customer statement export or printing.** The screen is the statement.
> - **No editing a payment.** See P11.T5.
> - **Nothing on the supplier side.** Money you owe suppliers is a different table and a
>   different screen; `purchases` has no `amount_paid`.
>
> **Interaction to watch:** reverting a delivered sale back to `ordered` while payments are
> allocated to it would leave money against an invoice that owes nothing, and the customer's
> balance would silently drop. The revert path is being built alongside this (see the order
> status work) and should refuse a sale with payments against it.

---

## Critical Path & Parallelism

```
P0 ─┬─> P1 ─┬─> P3 ──> P4 ──> P5 ──┬──> P7 ──> P8 ──> P9
    ├─> P2 ─┘                      │
    └─> P6 ────────────────────────┘

P10 ── additive over P1/P4/P5/P6; changes no figure the ledger or the reports derive.
```

Phases 0–7 and 10 are done.

- Phases 2 and 6 can run alongside 1, 3, and 4.
- Phase 3 gates all financial correctness — treat its test suite as non-negotiable.
