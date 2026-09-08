---
paths:
  - 'app/Actions/Purchasing/**'
---

# Purchasing

## A purchase runs ordered → on the way → proceed, and only the last is stock
There is no draft/posted pair any more, and nothing about a purchase is
one-way. `SetPurchaseStatusAction` moves it and brings the ledger with it:
reaching `proceed` receives the goods, leaving it puts them back
(`RevertPurchaseAction`). Goods somebody has promised are not goods on the
shelf, and nothing counts towards what was spent until the invoice is here.

`committed_at`, not the status, is what says whether the ledger holds the
receipt. An invoice whose goods have already been sold on cannot be moved back,
edited or deleted — `StockAlreadyConsumedException` says so plainly rather than
letting a batch disappear from under a sale.

## Landed cost, and why it must reconcile
`ReceivePurchaseAction` spreads every invoice-wide cost across the lines before
anything reaches the ledger, because freight belongs *inside* the cost of the
goods. A curtain at $18 with $2 of freight cost $20; selling it for $44 made
$24, not $26. Get this wrong and every profit figure in P7 is wrong.

- `CostAllocationMethod::ByQuantity` — freight, handling. Weight is line quantity.
- `CostAllocationMethod::ByValue` — duty, insurance. Weight is line net total.
- Spreading uses `Money::allocate()` (largest remainder), so shares add back to
  the cost exactly.
- `assertReconciles()` throws a `LogicException` if the landed totals ever fail
  to equal the invoice total. Do not soften this into a warning — silent drift
  here is undetectable later.

**Landed cost rarely divides evenly by quantity.** $40.00 over 3 units is
$13.33 each with a penny left over. `InventoryService::receiveAtTotalCost()`
allocates across the individual units and groups equal costs, so that line
becomes two batches — 2 at $13.33 and 1 at $13.34. Never divide and round
instead; the penny goes missing from inventory on every such line.

The invariant to test any change against: after receiving, total inventory
valuation increases by exactly the invoice total.

Line `unit_cost` and `discount` are what the supplier charged — facts at
transaction time. The landed cost is NOT stored on the line; it is derived on
receipt and recorded where it matters, on the stock batches.
