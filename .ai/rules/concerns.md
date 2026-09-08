---
paths:
  - app/Http/Concerns/InteractsWithPurchaseForm.php
---

# Concerns

## The purchase drawer belongs to more than the purchases screen
`InteractsWithPurchaseForm` serves the drawer's props (`products`, `allocationMethods`, `statuses`, `paymentMethods`, `banks`, and `nextNumber` via `newPurchaseOptions()`). `PurchaseController` and `LoanController` both use it — a drawer offering different products or statuses depending on where it was opened from would be two forms wearing one name. Any new screen that mounts `<PurchaseDrawer>` takes the trait rather than assembling the props again.

The loans screen passes `prefill` (a `PurchaseLineSeed[]` of product + quantity) so the drawer opens holding the order the list is telling you to place — "Buy" per row, "Buy everything owed" for the lot. Seeds carry NO price: `seededLines()` fills the cost off the catalogue in the base currency, exactly as picking the product by hand does, and drops a seed whose product is no longer listed. `prefill` is ignored when editing — an existing invoice's lines are the invoice. Nothing is recorded until the drawer is saved, and receiving the goods is what clears the loan.
