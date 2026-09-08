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
**Every stored amount is minor units of the base currency**, which is the `currencies` row flagged `is_base` — read it with `CurrencyService::base()`, never `config('money.currency')`. Foreign currency exists at exactly two edges: an amount may be TYPED in one, and the whole UI may be VIEWED in one. Nothing in between knows other currencies exist.

- Conversion on the way in happens in `App\Http\Requests\Concerns\ConvertsToBaseCurrency` and NOWHERE else. Actions, Services, Models, `App\Queries\*` and `App\Support\Csv` receive base-currency minor units only. An arch test enforces this — do not weaken it.
- Every money field may carry a sibling `{field}_currency` key, so one field on a form can be in dollars while its neighbours stay in dinars. A field with no currency of its own follows the document's `currency`.
- Requests validate currency fields against `CurrencyService::enterable()`, which lists only currencies with a rate on record. That is why `MissingExchangeRateException` is an internal guard, not an error users can provoke.
- Conversion reads the rate in force on the **document's own date** (`invoiced_on` / `sold_on` / `spent_on`), matching how report queries scope. Back-dated paperwork costs at the rate of its own day.
- `purchases`, `sales` and `expenses` carry `currency` + `exchange_rate` — what the money changed hands in, and the scaled rate applied. Recorded facts, not settings. **One rate per document**: complete for two currencies, but a third would have to move the rate down to the individual amount.

## Currencies are rows; rates are typed in, never fetched
Which currencies this business deals in, and which one the books are kept in, are rows in `currencies` — managed on Settings → Currencies. `config('money.currency')` is only the opening base and a pre-first-row fallback. See `.ai/rules/services.md` for the full rules, including why moving the base is refused once money is recorded.

- A rate is base major units per one foreign major unit, as a fixed-point integer scaled by `ExchangeRates::SCALE` (10⁶). 1,320.50 IQD/USD is `1_320_500_000`. Mirrored as `RATE_SCALE` in `resources/js/lib/money.ts`.
- Conversion belongs to `App\Support\ExchangeRates` — framework-free, so every rule is unit-testable without a database. `CurrencyService` only looks rates up and hands one out.
- Nothing anywhere calls the network for a rate. A published feed was built and removed; an arch test keeps it that way.
- Lookups take the newest rate **on or before** the date, so a rate stands until a newer one is recorded.
- **Trap:** `effective_on` is stored `Y-m-d 00:00:00`, so `max('effective_on')` and `updateOrCreate` on it both silently fail to match a `Y-m-d` string. Use `whereDate()`, as `CurrencyService::record()` does and explains.

## Where business logic lives: Actions, Services, Queries
Controllers validate (Form Request), delegate, and return a response. No business logic in them — this is enforced by `tests/Unit/ArchTest.php`.

- `App\Actions\*` — one write operation that changes state, e.g. `ReceivePurchaseAction`. Final, single `handle()` entry point, wraps its own DB transaction, returns a typed result.
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
- ~~Posted financial documents are immutable; Draft → Posted is one-way~~ → **documents run ordered → on the way → proceed, and the status moves both ways.** Moving one back reverses exactly what it put in the ledger rather than writing a reversal document, and editing one re-runs it. What cannot be undone is a batch somebody has already sold from — see `.ai/rules/purchasing.md` and `.ai/rules/sales.md`.
- "Never store calculated values" has one exception: recorded historical facts. A sale line's unit price and a batch's landed unit cost are facts at transaction time. Totals, profit and stock levels stay derived.
- Quantities are integers — unsigned on document lines (with a `> 0` check constraint), signed on `stock_movements`.

## The catalogue is a flat product list, and the name is the identity
A **product is the stock-keeping entity** — the thing counted, bought and sold. One row, one **unique name**, one cost and one selling price. Purchases, sales and the stock ledger reference `products` directly.

There are deliberately **no variants, options/attributes, categories, brands, units, `code` or `is_active`**. All were built and then removed as layers to maintain with no matching gain. Do not reintroduce any of them without asking; each is additive.

- **No `code`.** A second identifier to invent, type and keep unique bought nothing the name did not. `products.name` carries the `unique` index and `ProductRequest` validates against it, so a product is still identifiable everywhere it is listed, picked or reported — the name is what every product dropdown reads back.
- **No `is_active`.** Archiving was a filter on every product query and a badge on every row for a state nobody used. A product that is finished with is deleted; one with stock history cannot be, and `ProductController::destroy` says so rather than letting the FK surface as a 500.
- **`cost_price` and `selling_price` are both required**, and both hold base currency. They were `default_cost_price` / `default_selling_price` and nullable, where null meant "not priced yet" — a state that bought a null to handle on every screen, every prefill and every report and helped nobody. A product nobody has priced cannot be sold. A cost of zero is allowed (a free sample really is free); a selling price of zero is not. Each transaction still records its own price as a fact at transaction time, so editing these never rewrites history.

Where goods differ, each difference is its own product: a curtain at 117×137 and one at 168×183 are two rows with two names. That is also what keeps quantities whole numbers — a product is counted in pieces, never measured — so the integer-quantity Guiding Decision holds through Phase 3's FIFO costing. If anything ever needs selling by the metre or by area, reopen that decision before building the ledger.

Product create/update is plain Eloquent in the controller — there is no Action, because there is no multi-step write to wrap. Buying and selling from the catalogue **do** span tables, and those go through `QuickPurchaseAction` / `QuickSaleAction`.

## Customers exist; a purchase names no supplier
Both halves of this reversed:

- ~~No customers~~ → **every sale names a buyer.** `sales.customer_id` is REQUIRED, and counter trade goes to the seeded **Walk-in** customer that every sale form opens on, so the requirement costs no keystrokes. Customers have a statement screen and take payments against their loans. See `.ai/rules/models.md` and `.ai/rules/customers.md`.
- ~~A purchase's supplier is optional~~ → **`purchases` has no supplier column at all.** It was nullable, had to be filled in or explicitly skipped on every invoice, and nothing downstream ever read it. An invoice is the goods, the money and the date. `ActivityQuery` lists where the order stands in the column that used to name the supplier.

`suppliers` is still a table with its own screen — who you buy from is worth keeping a list of — it is simply not on the invoice. Only `name` is required: a supplier is often just a name and a phone number when first written down, and the form must not stand in the way of recording that.

Reporting has no customer, supplier or product analysis: the reports were cut back to income/outcome/net — see `.ai/rules/queries.md`.
