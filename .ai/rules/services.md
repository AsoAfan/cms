---
paths:
  - 'app/Services/**'
  - app/Services/CurrencyService.php
---

# Services

## InventoryService is the only thing that writes stock
Never insert a `stock_movements`, `stock_batches` or `stock_batch_consumptions` row directly — not in a controller, an action, a seeder or a test fixture. Go through `App\Services\InventoryService`:

- `receive($product, $quantity, $unitCost, $type, $occurredAt, $source, $reason)` — writes the movement and its FIFO batch together.
- `issue($product, $quantity, $type, $occurredAt, $source, $reason)` — allocates oldest-first and records exactly which batches paid, which is what makes COGS a fact. Throws `InsufficientStockException` and writes nothing if the ledger cannot cover it.
- `adjust($product, $delta, $reason, $unitCost, $occurredAt)` — delegates to the two above. A positive delta REQUIRES a unit cost; stock appearing from nowhere still has to be valued or the valuation understates.

Phase 4 and 5 post through `receive()`/`issue()` with the purchase or sale line as `$source`.

Invariants the service upholds and tests pin:

- Stock never goes negative. An issue that cannot be covered is refused whole.
- An issue only draws on batches with `received_at <= occurred_at` — stock that had not arrived cannot have been sold.
- Back-dating a receipt does NOT rewrite allocations already settled. The ledger is append-only; correcting means writing a further movement.
- Nothing is ever updated or deleted. Products with stock history cannot be deleted (FK restrict).

`StockOnHandQuery` and `InventoryValuationQuery` derive quantity and value; there is no balance column anywhere. Both accept an `asAt` date and rewind correctly.

**Trap:** `StockBatch::remainingQuantity()` checks whether the `consumptions_sum_quantity` aggregate is *present*, not whether it is truthy. A date-constrained `withSum` returns NULL when nothing matched, and falling back to an unfiltered sum there silently ignores the constraint — that bug made an as-at valuation report today's figure.

## There is no stock screen, and no stock-adjustment UI
`/stock` and its count dialog were removed at the user's direction. Quantity on hand now shows as a column on the catalogue, still derived by `StockOnHandQuery` — no balance column appeared.

Stock therefore moves **only** through purchases and sales. `InventoryService::adjust()` survives as a service method with its ledger tests, but nothing calls it: it is the hook a write-off screen would use, not dead weight to delete. Ask before either wiring it back up or removing it.

Consequence to remember: there is no way to record an opening balance, damage or a miscount. Opening stock is entered as a purchase, which is also what values it correctly.

## Exchange rates come from the feed, never from a user
`exchange_rates` is written only by `SyncExchangeRatesAction` (`php artisan currency:sync`, scheduled daily in routes/console.php). There is deliberately NO screen for typing a rate in and no `source` column — one was built and removed at the user's direction. A rate is a fact about the market, not a preference, and a form for entering one is a form for entering a wrong one that then costs every invoice behind it. Ask before adding one back.

Nothing calls the feed during a page request: screens read the table, so the app keeps working when the feed is down. An arch test asserts CurrencyService never touches the Http facade.

A rate is base major units per one foreign major unit, scaled by `ExchangeRates::SCALE` (10^6) — 1320.50 IQD/USD is 1_320_500_000. Conversion itself lives in the framework-free `App\Support\ExchangeRates`, so it is unit-testable with no database.

Lookups take the newest rate ON OR BEFORE the date, so a missed sync carries yesterday's figure forward.

TRAP: `effective_on` stores `Y-m-d 00:00:00`, so `max('effective_on')` returns a datetime string and `updateOrCreate` on it never matches a `Y-m-d`. Always `whereDate()` — see `record()` and `latestRowOn()`.
