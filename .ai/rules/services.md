---
paths:
  - 'app/Services/**'
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
