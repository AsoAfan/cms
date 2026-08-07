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
| Counterparties | **Suppliers only** | Sales are over the counter, so there is no customer to name. Customers would arrive as their own table plus a nullable `customer_id` on sales — additive. |
| Catalogue depth | **Flat: a product is the stock-keeping entity.** No variants, options, categories, brands or units | Each was built and then removed as a layer to maintain without a matching gain. Goods that differ are separate products with separate codes. All are additive to bring back. |
| Multi-warehouse / multi-currency | Schema-ready, not built | Ledger carries a nullable `location_id`; no currency column until needed |
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
- [x] **P1.T2** — `products`. ~~`product_variants`~~ — built, then removed: a product **is** the stock-keeping entity, with its own `code`, cost and price.
- [x] **P1.T3** — ~~`attributes`, `attribute_values`, pivot~~ — built with a full variant matrix builder, then removed.
- [x] **P1.T4** — Model, factory, seeder (flat curtain catalogue, one row per size).
- [x] **P1.T5** — Pricing: `default_cost_price` / `default_selling_price` as *defaults only*; transactions record their own prices. Null means "not priced yet" rather than zero.
- [x] **P1.T6** — n/a — no reference data left to manage.
- [x] **P1.T7** — Product CRUD: one form, six fields.
- [x] **P1.T8** — Product list: search across name and code, status filter, sortable prices, pagination.
- [x] **P1.T9** — Feature tests for product CRUD, pricing precision, code uniqueness and filtering.

**Done when:** ~~you can create a product with 3 items across 2 options and find it by code~~ →
**you can create a product and find it by name or code.** ✅

> **The catalogue is flat.** Categories, brands, units, variants and options were each
> built and then removed at the user's direction — every one was a screen to maintain
> without a matching gain. Where goods differ, each difference is its own product:
> a curtain at 117×137 and one at 168×183 are two rows with two codes.
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

- [ ] **P3.T1** — `stock_movements`: product, signed qty, type enum, polymorphic `source` (purchase line / sale line / adjustment), `occurred_at`, nullable `location_id`.
- [ ] **P3.T2** — `stock_batches`: product, source purchase line, `quantity_received`, `unit_cost` (landed), `received_at`.
- [ ] **P3.T3** — `stock_batch_consumptions`: batch, consuming movement, qty, cost — the FIFO allocation record that produces exact COGS.
- [ ] **P3.T4** — `InventoryService`: `receive()`, `issue()` (FIFO consumption), `adjust()`.
- [ ] **P3.T5** — `StockOnHandQuery` — derives quantity per product at any date from the ledger.
- [ ] **P3.T6** — `InventoryValuationQuery` — value on hand at any date.
- [ ] **P3.T7** — Insufficient-stock guard + domain exception.
- [ ] **P3.T8** — Manual stock adjustment screen (damage, count correction) with reason.
- [ ] **P3.T9** — Heavy unit tests: FIFO across multiple batches, partial consumption, out-of-order dates, negative-stock rejection.

**Done when:** buying 10 @ $5 then 10 @ $7 and issuing 15 yields COGS of exactly $85 and
on-hand of 5 @ $7.

> This phase is the risk concentration. If FIFO/COGS is wrong, every figure in Phase 7 is
> wrong. Over-test it.

---

## Phase 4 — Purchasing

- [ ] **P4.T1** — `purchases` (invoice header: supplier, number, date, status, notes).
- [ ] **P4.T2** — `purchase_lines` (product, qty, unit cost, discount).
- [ ] **P4.T3** — `purchase_additional_costs` (freight, customs) + allocation strategy enum (by value / by qty).
- [ ] **P4.T4** — `PostPurchaseAction`: allocate landed cost → create batches → write stock movements, inside a transaction. Use largest-remainder allocation with the residual on the last line by `id`; assert allocated costs sum exactly to the invoice total.
- [ ] **P4.T5** — Draft → Posted status machine; posted purchases are immutable (reversal, not edit).
- [ ] **P4.T6** — Purchase entry form: fast line entry, product combobox, keyboard-driven, live totals.
- [ ] **P4.T7** — Purchase list + detail view with movement trace.
- [ ] **P4.T8** — Feature tests including landed-cost allocation math.

**Done when:** posting a purchase raises stock and sets the correct landed unit cost per batch.

---

## Phase 5 — Sales

- [ ] **P5.T1** — `sales` (number, date, status). No customer — see Phase 2.
- [ ] **P5.T2** — `sale_lines` (product, qty, unit price, discount).
- [ ] **P5.T3** — `payment_methods` reference table + `sale_payments` (supports partial/split payment).
- [ ] **P5.T4** — `PostSaleAction`: FIFO issue via `InventoryService`, recording COGS per line.
- [ ] **P5.T5** — Draft → Posted status machine (returns-ready).
- [ ] **P5.T6** — Fast POS-style entry screen: barcode/SKU field, add line on Enter, running total, minimal clicks.
- [ ] **P5.T7** — Sale list + invoice detail with per-line profit.
- [ ] **P5.T8** — Return-ready scaffolding: nullable `parent_sale_id` + `SaleType` enum, no UI yet.
- [ ] **P5.T9** — Feature tests: stock decrease, COGS correctness, oversell rejection.

---

## Phase 6 — Expenses

Small and fully independent — good parallel work alongside Phase 4/5.

- [ ] **P6.T1** — `expense_categories` migration + seeder (Rent, Salaries, Transport, Utilities, Marketing, Misc).
- [ ] **P6.T2** — `expenses` (category, amount, date, payment method, reference, notes).
- [ ] **P6.T3** — Expense CRUD + list with date-range and category filters.
- [ ] **P6.T4** — Category management screen.
- [ ] **P6.T5** — Feature tests, plus an arch test asserting expenses never touch inventory.

---

## Phase 7 — Reporting Engine

**Goal:** every figure derived from transactions, over any date range.

- [ ] **P7.T1** — `ReportPeriod` value object (range, presets, comparison period).
- [ ] **P7.T2** — Shared date-range filter component + persisted URL state.
- [ ] **P7.T3** — `SalesReportQuery` — revenue, invoice count, average selling price.
- [ ] **P7.T4** — `PurchaseReportQuery` — total purchases, average buying cost.
- [ ] **P7.T5** — `ExpenseReportQuery` — totals by category.
- [ ] **P7.T6** — `ProfitReportQuery` — gross profit (revenue − COGS), net profit (− expenses).
- [ ] **P7.T7** — `ProductProfitabilityQuery` — profit per product.
- [ ] **P7.T8** — `SupplierSummaryQuery`. (No customer summary — see Phase 2.)
- [ ] **P7.T9** — `InventoryValuationReport` — on-hand value, dead stock.
- [ ] **P7.T10** — Averages: average income/outcome per day, week, and month over the selected period.
- [ ] **P7.T11** — Dashboard: KPI tiles, trend chart, recent activity.
- [ ] **P7.T12** — CSV export for each report.
- [ ] **P7.T13** — Accuracy tests: seeded fixture with hand-calculated expected figures.

**Done when:** gross profit from the report equals the sum of per-line profits computed independently.

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

## Critical Path & Parallelism

```
P0 ─┬─> P1 ─┬─> P3 ──> P4 ──> P5 ──┬──> P7 ──> P8 ──> P9
    ├─> P2 ─┘                      │
    └─> P6 ────────────────────────┘
```

Phases 0–2 are done.

- Phases 2 and 6 can run alongside 1, 3, and 4.
- Phase 3 gates all financial correctness — treat its test suite as non-negotiable.
