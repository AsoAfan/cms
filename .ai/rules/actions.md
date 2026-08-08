---
paths:
  - 'app/Actions/**'
---

# Actions

## Quick buy/sell compose Save + Post in one transaction
`QuickPurchaseAction` and `QuickSaleAction` back the Buy/Sell buttons on the catalogue. They are not a shortcut around the invoice: each writes a real single-line document through `Save*Action` and then posts it through `Post*Action`, so stock arrives and leaves by exactly the path a typed-up invoice takes and every report downstream sees an ordinary purchase or sale.

Both wrap save and post in **one** transaction. That is the whole point: when `PostSaleAction` refuses a short sale, the draft it would otherwise have left behind is rolled back too. A mystery draft nobody asked for is worse than the error.

Because posting is one-way, a quick trade is immutable the moment it succeeds — correcting one means a reversal document, which is not built. The dialogs pre-flight what they can (the sell dialog disables submit above on-hand) so the common mistake never reaches the ledger.
