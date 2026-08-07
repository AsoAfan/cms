---
paths:
  - 'app/**'
---

# App

## Money is integer minor units, never a float
Every monetary column is `bigInteger` holding minor units (cents), cast with `App\Support\Money` (`'total' => Money::class` — Money is Castable).

- Never use float/double for money, and never `round()` a monetary figure by hand.
- Build from user input with `Money::fromDecimal('12.34')`; it rejects anything with more precision than a cent rather than silently truncating.
- Splitting an amount (landed costs, invoice-level discounts) MUST go through `Money::allocate($weights)` or `->split($n)`. It uses largest-remainder allocation with ties breaking to the last line, so parts always sum back to exactly the original — $100.00 across 3 becomes 33.33/33.33/33.34.
- Percentages stay exact via `multipliedByFraction($numerator, $denominator)` (12.5% is `(125, 1000)`), not a float multiply.
- `jsonSerialize()` returns the raw minor-unit integer. The frontend formats it with `useFormatMoney()` / `<MoneyDisplay>`; do not send pre-formatted strings.

## Where business logic lives: Actions, Services, Queries
Controllers validate (Form Request), delegate, and return a response. No business logic in them — this is enforced by `tests/Unit/ArchTest.php`.

- `App\Actions\*` — one write operation that changes state, e.g. `PostPurchaseAction`. Final, single `handle()` entry point, wraps its own DB transaction, returns a typed result.
- `App\Services\*` — a cohesive set of related operations over one domain, e.g. `InventoryService` with `receive()`/`issue()`/`adjust()`. Final.
- `App\Queries\*` — read models that derive figures from transactions, e.g. `StockOnHandQuery`, `SalesReportQuery`. Final, single `get()` entry point.
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

## Catalogue vocabulary and shape: product → items → options
The catalogue is deliberately shallow. There are **no categories, brands or units** — each was dropped as a screen to maintain with no matching gain. Do not reintroduce one without asking; all three are additive.

Vocabulary, which the UI and tests must follow:

- **Product** — the catalogue entry a customer recognises (`products`).
- **Item** — the stock-keeping entity that is counted, bought and sold (`product_variants`). Its identifier column is `code`, NOT `sku` — the user asked for the plainer word and the schema matches the UI.
- **Option** — an axis a product varies along (`attributes`), with values (`attribute_values`).

The table and relation names keep Eloquent's conventions (`variants()`, `attributeValues()`); everything a person reads says item / code / option.

**Sizing is an option, not a quantity.** This business sells curtains: an item *is* a finished size ("117cm / 137cm") counted in whole pieces. That is what keeps the integer-quantity Guiding Decision valid through FIFO costing. If anything ever needs selling by the metre or by area, reopen that decision before building Phase 3.

`ProductVariant::optionLabel()` orders values by `attribute_id` (creation order), not alphabetically — a curtain must read "Width / Drop", and sorting by name renders it backwards.
