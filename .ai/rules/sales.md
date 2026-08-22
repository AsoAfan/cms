---
paths:
  - 'app/Actions/Sales/**'
  - 'app/Http/Controllers/Sales/**'
  - 'resources/js/pages/sales/**'
  - 'resources/js/components/sales/**'
---

# Sales

## A sale runs ordered → on the way → proceed, and stock leaves in the middle
There is no draft/posted pair any more, and nothing about a sale is one-way.
`SetSaleStatusAction` moves it and brings the ledger with it: `on_the_way`
issues the lines FIFO through `InventoryService::issue()`, `ordered` puts them
back, and `proceed` only records that the customer has them and moves nothing.

- **Stock leaves at `on_the_way`, not at `proceed`.** Goods handed to a driver
  are off the shelf whatever happens next; a shop that still counts them will
  sell them twice.
- `committed_at`, not the status, is what says whether the ledger holds the
  issue — read it (`Sale::isCommitted()`) when you mean "have the goods gone".
- A sale that has gone out can still be edited or deleted: `SaveSaleAction`
  reverts the stock, rewrites the lines and re-issues, all in one transaction,
  so an edit that would leave the shop short fails whole rather than halfway.
- `IssueSaleAction` pre-flights every line before issuing any of them, summing
  per product, so a short sale reports **all** its shortages in one message.

**Nothing stores a cost of sale.** `SaleLine::costOfGoodsSold()` reads it back
off `stock_batch_consumptions` via the line's stock movements. Never add a
`cost` column to `sale_lines` — a copy would drift from the ledger. Likewise
`unit_price` and `discount` are what the customer was charged, facts at
transaction time; repricing a product tomorrow must not change what yesterday
sold for. Profit only exists once the goods have left; the show screen renders
"—" rather than a misleading zero.

## Sales are a list with a drawer over it, exactly like purchases
`/sales` and `/sales/{sale}` are the whole UI. Routes are `index`, `store`,
`show`, `update`, `destroy` plus `status` — **there is no create or edit page**,
and `SaleTest` asserts both 404.

- `SaleDrawer` (bottom sheet) rings one up from the list and corrects one from
  the invoice; `SaleForm` is mounted only while it is open, so each opening
  starts from stored values.
- `store` and `update` return `back()`, so the drawer closes over the screen it
  was opened from rather than navigating away.
- `SaleController::detail()` sends each amount twice: minor units for the
  figures on the page, and `*_decimal` strings for the fields in the drawer.
  Never let the client divide by 100 to refill a form.
- Lines take a **product dropdown per row**, exactly as a purchase does, each
  option labelled `name · N in stock`. The type-a-name-and-Enter scan box it
  replaced is gone: two ways to put a product on a document was one too many.
  Enter on the last line still adds another.
- The show page is the invoice — product, qty, price, discount, total. Cost and
  profit are the shop's side of it and belong in the summary block, not on the
  lines.
- The summary's figures share ONE currency dropdown: wrap them in
  `<MoneyReviewGroup>` and put a single `<MoneyReviewSwitch>` in the block. See
  `.ai/rules/components.md`.

**Not built, deliberately** (say so before adding): no `sale_payments` table —
`payment_method` is a single enum on the sale, and later payments are
`CustomerPayment` allocations. No returns scaffolding.
