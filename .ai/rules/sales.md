---
paths:
  - 'app/Actions/Sales/**'
---

# Sales

## Posting a sale: cost comes from the ledger, never from the catalogue
A sale is a **draft** until posted. Posting issues each line through `InventoryService::issue()`, which draws oldest-batch-first and records exactly which batches paid. Posted sales are immutable — no edit, no delete.

**Nothing stores a cost of sale.** `SaleLine::costOfGoodsSold()` reads it back off `stock_batch_consumptions` via the line's stock movements. It is derived, exact, and can always be replayed. Never add a `cost` column to `sale_lines` — a copy would drift from the ledger, and the ledger is the truth.

Likewise `unit_price` and `discount` on the line are what the customer was actually charged. Repricing a product tomorrow must never change what yesterday sold for.

`PostSaleAction` pre-flights every line before issuing any of them, so a short sale reports **all** its shortages at once rather than one per attempt. It also sums per product, because several lines can name the same product. If stock moves between the pre-flight and the issue, the whole sale rolls back — a half-posted sale is worse than none.

Profit only appears once posted; a draft has taken nothing off the shelf, so it has no cost yet. The show screen renders "—" rather than a misleading zero.

**Not built, deliberately** (say so before adding):
- No `sale_payments` table. `payment_method` is a single enum on the sale; split and part payment are additive later (new table plus a nullable column).
- No returns scaffolding (`parent_sale_id` / `SaleType`). A nullable column with no UI is the same speculative layer that `location_id` and units turned out to be.
