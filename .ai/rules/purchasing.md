---
paths:
  - 'app/Actions/Purchasing/**'
---

# Purchasing

## Posting a purchase: landed cost, and why it must reconcile
A purchase is a **draft** until posted. Posting is what writes stock, happens once, and is one-way. A posted purchase is never edited or deleted — controllers and `SavePurchaseAction` both refuse it. Undoing one means a reversal document (not built yet).

`PostPurchaseAction` spreads every invoice-wide cost across the lines before anything reaches the ledger, because freight belongs *inside* the cost of the goods. A curtain at $18 with $2 of freight cost $20; selling it for $44 made $24, not $26. Get this wrong and every profit figure in P7 is wrong.

- `CostAllocationMethod::ByQuantity` — freight, handling. Weight is line quantity.
- `CostAllocationMethod::ByValue` — duty, insurance. Weight is line net total.
- Spreading uses `Money::allocate()` (largest remainder), so shares add back to the cost exactly.
- `assertReconciles()` throws a `LogicException` if the landed totals ever fail to equal the invoice total. Do not soften this into a warning — silent drift here is undetectable later.

**Landed cost rarely divides evenly by quantity.** $40.00 over 3 units is $13.33 each with a penny left over. `InventoryService::receiveAtTotalCost()` allocates across the individual units and groups equal costs, so that line becomes two batches — 2 at $13.33 and 1 at $13.34. Never divide and round instead; the penny goes missing from inventory on every such line.

The invariant to test any change against: after posting, total inventory valuation increases by exactly the invoice total.

Line `unit_cost` and `discount` are what the supplier charged — facts at transaction time. The landed cost is NOT stored on the line; it is derived at posting and recorded where it matters, on the stock batches.
