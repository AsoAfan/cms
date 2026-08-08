---
paths:
  - 'app/**'
---

# App

## Money is integer minor units, never a float
Every monetary column is `bigInteger` holding minor units, cast with `App\Support\Money` (`'total' => Money::class` — Money is Castable). Two decimal places for every currency, whatever it is conventionally quoted in — how many a currency SHOWS is a display setting (`fraction_digits` in `config/money.php`), and dinars show none.

- Never use float/double for money, and never `round()` a monetary figure by hand.
- Build from user input with `Money::fromDecimal('12.34')`; it rejects anything with more precision than a cent rather than silently truncating.
- Splitting an amount (landed costs, invoice-level discounts) MUST go through `Money::allocate($weights)` or `->split($n)`. It uses largest-remainder allocation with ties breaking to the last line, so parts always sum back to exactly the original — 100.00 across 3 becomes 33.33/33.33/33.34.
- Percentages stay exact via `multipliedByFraction($numerator, $denominator)` (12.5% is `(125, 1000)`), not a float multiply.
- `jsonSerialize()` returns the raw minor-unit integer. The frontend formats it with `useFormatMoney()` / `<MoneyDisplay>`; do not send pre-formatted strings.

## Currency is converted once, in the Form Request
**The base currency is IQD (`config/money.php`) and every stored amount is in it.** Foreign currency exists at exactly two edges — an amount may be TYPED in one, and the whole UI may be VIEWED in one. Nothing in between knows other currencies exist.

- Conversion on the way in happens in `App\Http\Requests\Concerns\ConvertsToBaseCurrency` and NOWHERE else. Actions, Services, Models, `App\Queries\*` and `App\Support\Csv` receive base-currency minor units only. An arch test enforces this — do not weaken it.
- Every money field may carry a sibling `{field}_currency` key, so one field on a form can be in dollars while its neighbours stay in dinars. A field with no currency of its own follows the document's `currency`.
- Requests validate currency fields against `CurrencyService::enterable()`, which lists only currencies with a rate on record. That is why `MissingExchangeRateException` is an internal guard, not an error users can provoke.
- Conversion reads the rate in force on the **document's own date** (`invoiced_on` / `sold_on` / `spent_on`), matching how report queries scope. Back-dated paperwork costs at the rate of its own day.
- `purchases`, `sales` and `expenses` carry `currency` + `exchange_rate` — what the money changed hands in, and the scaled rate applied. Recorded facts, not settings. **One rate per document**: complete for two currencies, but a third would have to move the rate down to the individual amount.

## Exchange rates come from the feed, never from a user
`exchange_rates` is written only by `SyncExchangeRatesAction` (`php artisan currency:sync`, scheduled daily in `routes/console.php`). There is deliberately **no screen for typing a rate in and no `source` column** — a rate is a fact about the market, not a preference, and a form for entering one is a form for entering a wrong one that then costs every invoice behind it. Do not add one back without asking.

- A rate is base major units per one foreign major unit, as a fixed-point integer scaled by `ExchangeRates::SCALE` (10⁶). 1,320.50 IQD/USD is `1_320_500_000`. Mirrored as `RATE_SCALE` in `resources/js/lib/money.ts`.
- Conversion belongs to `App\Support\ExchangeRates` — framework-free, so every rule is unit-testable without a database. `CurrencyService` only looks rates up and hands one out.
- Nothing calls the feed during a page request. Screens read the table, so the application keeps working when the feed is down.
- Lookups take the newest rate **on or before** the date, so a day the sync missed carries the previous day's figure forward.
- **Trap:** `effective_on` is stored `Y-m-d 00:00:00`, so `max('effective_on')` and `updateOrCreate` on it both silently fail to match a `Y-m-d` string. Use `whereDate()`, as `CurrencyService::record()` does and explains.

## Where business logic lives: Actions, Services, Queries
Controllers validate (Form Request), delegate, and return a response. No business logic in them — this is enforced by `tests/Unit/ArchTest.php`.

- `App\Actions\*` — one write operation that changes state, e.g. `PostPurchaseAction`. Final, single `handle()` entry point, wraps its own DB transaction, returns a typed result.
- `App\Services\*` — a cohesive set of related operations over one domain, e.g. `InventoryService` with `receive()`/`issue()`/`adjust()`. Final.
- `App\Queries\*` — read models that derive figures from transactions, e.g. `StockOnHandQuery`, `CashFlowQuery`. Final, single `get()` entry point.
- `App\Support\*` — framework-free value objects (`Money`, later `ReportPeriod`). Final, immutable, no facades so they stay unit-testable.
- `App\Enums\*` — enums, TitleCase cases.

Add the arch rule alongside the first class in a new namespace; several arch tests already guard these namespaces while they are still empty.

## Inventory ledger invariants (settled before Phase 3 is built)
Decisions from ROADMAP.md that Phase 3+ must implement, not re-litigate:

- Stock on hand is ALWAYS derived from the `stock_movements` ledger. No stored balance column — it drifts. Cache only if a benchmark proves a need (P8.T4/T5).
- Costing is FIFO via purchase batches. COGS comes from `stock_batch_consumptions`, the recorded allocation of a batch to an issuing movement — never from an average.
- Movements are append-only and signed (+ receive / − issue). Correcting a mistake means writing a reversing movement, never editing or deleting one.
- Posted financial documents are immutable. Draft → Posted is one-way; undoing means a reversal document.
- "Never store calculated values" has one exception: recorded historical facts. A sale line's unit price and a batch's landed unit cost are facts at transaction time. Totals, profit and stock levels stay derived.
- Quantities are integers — unsigned on document lines (with a `> 0` check constraint), signed on `stock_movements`.

## The catalogue is a flat product list, and the name is the identity
A **product is the stock-keeping entity** — the thing counted, bought and sold. One row, one **unique name**, one cost and one selling price. Purchases, sales and the stock ledger reference `products` directly.

There are deliberately **no variants, options/attributes, categories, brands, units, `code` or `is_active`**. All were built and then removed as layers to maintain with no matching gain. Do not reintroduce any of them without asking; each is additive.

- **No `code`.** A second identifier to invent, type and keep unique bought nothing the name did not. `products.name` carries the `unique` index and `ProductRequest` validates against it, so a product is still identifiable everywhere it is listed, picked or reported. The sale screen's scan box matches on name (exact first, then partial).
- **No `is_active`.** Archiving was a filter on every product query and a badge on every row for a state nobody used. A product that is finished with is deleted; one with stock history cannot be, and `ProductController::destroy` says so rather than letting the FK surface as a 500.
- **`cost_price` and `selling_price` are both required**, and both hold base currency. They were `default_cost_price` / `default_selling_price` and nullable, where null meant "not priced yet" — a state that bought a null to handle on every screen, every prefill and every report and helped nobody. A product nobody has priced cannot be sold. A cost of zero is allowed (a free sample really is free); a selling price of zero is not. Each transaction still records its own price as a fact at transaction time, so editing these never rewrites history.

Where goods differ, each difference is its own product: a curtain at 117×137 and one at 168×183 are two rows with two names. That is also what keeps quantities whole numbers — a product is counted in pieces, never measured — so the integer-quantity Guiding Decision holds through Phase 3's FIFO costing. If anything ever needs selling by the metre or by area, reopen that decision before building the ledger.

Product create/update is plain Eloquent in the controller — there is no Action, because there is no multi-step write to wrap. Buying and selling from the catalogue **do** span tables, and those go through `QuickPurchaseAction` / `QuickSaleAction`.

## Suppliers exist; customers deliberately do not
`suppliers` is the only counterparty table. Purchases reference it from Phase 4, optionally — see below.

There is **no `customers` table and no customer concept**. Sales are over the counter and are not tied to a named buyer. `SupplierTest` asserts `/customers` 404s; keep it that way.

Consequences for later phases — do not silently reintroduce a customer:

- **P5 (Sales):** a sale has no `customer_id`. It is a dated document with lines, payments and a total, and nothing more.
- **P7 (Reporting):** there is no customer analysis, and since the reports were cut back to income/outcome/net there is no supplier or product analysis either — see `.ai/rules/queries.md`.

If named customers are ever wanted, they arrive as their own table plus a **nullable** `customer_id` on sales — additive, with no migration of posted documents. Ask before building it.

Only `name` is required on a supplier. A supplier is often just a name and a phone number when first written down, and the form must not stand in the way of recording that.

## A purchase's supplier is optional
`purchases.supplier_id` is nullable, and both `PurchaseRequest` and `QuickPurchaseRequest` validate it as `nullable`. An invoice is the goods, the money and the date; who it came from is filing, and cash bought down the market must not wait on a supplier record being created first.

- Read it null-safely everywhere: `$purchase->supplier?->name` in `PurchaseController` (list + detail) and `ActivityQuery`. The frontend types are `supplier: string | null` and render `—`.
- The FK is still `restrictOnDelete()`: a supplier with purchases behind them cannot be deleted, and is never quietly detached from their history.
- The purchases list filter takes `supplier_id=none` (a closure filter, `whereNull`) so unattributed invoices can still be found.
- Selects cannot hold null, so both forms carry a `NO_SUPPLIER = 'none'` item — leaving it off is something you pick, not an untouched box.
