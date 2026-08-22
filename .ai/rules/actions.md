---
paths:
  - 'app/Actions/**'
---

# Actions

## Quick buy/sell write a real document, at `ordered`
`QuickPurchaseAction` and `QuickSaleAction` back the Buy/Sell buttons on the
catalogue. Each writes a real single-line document through `Save*Action`, so
everything downstream sees an ordinary purchase or sale.

**Both land at `ordered`, and therefore move no stock.** They used to go
straight in at `proceed` — buying was "something you have in your hand",
selling was "the counter" — and that made the catalogue the one place where
goods appeared and vanished without anybody confirming they had. The catalogue
takes the order; the document's own screen moves it along, and that is what
touches the ledger.

- **A quick sale records nothing as paid** (`amount_paid` is `'0'`). Money
  against goods still on the shelf is a deposit, and only the sale screen has
  room to say one was taken. Booking it paid in full was the old counter
  assumption and it made every quick sale read as settled before the customer
  had anything.
- Selling more than is on hand is allowed here: an order can be placed for
  goods still coming in. It is refused when the sale is sent out, which is the
  point where stock actually has to exist.
- Because nothing is committed, neither dialog can fail on stock, and neither
  leaves a half-written document behind.
- Both controllers' flash messages say what was written **and** where the stock
  moves ("Stock arrives when you mark it Proceed"). Keep that: an action that
  quietly does less than it used to reads as a bug otherwise.
