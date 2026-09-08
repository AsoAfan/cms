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

## The printed invoice is the same page, laid out as paperwork
`SaleInvoiceDocument` is rendered on the invoice screen `hidden print:block`
and the rest of the screen is `print:hidden`, so Print is `window.print()` and
nothing else. There is no print route, no PDF service and no second copy of the
figures to keep in step.

- **Two things are configurable, both from the chevron beside Print**: how many
  items go on a page (`useLinesPerPage`, 1–20) and what currency the figures
  come out in (`usePrintCurrency`, defaulting to whatever the user is reading
  the app in). Both are remembered per browser through the same `localStorage`
  store, because both are facts about the paper and the person at the counter
  rather than about the books.
- **Convert each figure, then add up what was converted — never convert a sum.**
  The printed total is the sum of the printed line totals and the discount is
  what separates that from the gross, so a customer running a finger down the
  TOTAL column arrives at the TOTAL. Converting the lines and a separately
  converted total leaves the two a few cents apart. A non-base printing also
  states the rate and its date on the page: figures nobody can reconcile against
  the books are worse than no figures.
- Row padding tightens in steps as the item count rises so the page still fits; **both the steps and the ceiling of 20 were measured in a browser
  against a 297mm sheet**, the worst case being a full last page that also
  carries the totals. Loosen either and that page silently spills onto another
  sheet — re-measure before changing them.
- Lines are chunked in the component; each page repeats the masthead and the
  money lands once, on the last one.
- **TRAP — a 1px rule is not a rule you can see.** The line under the table head
  went missing from one page and not another, seemingly at random. It was in the
  PDF the whole time: Chrome rounds border widths to whole device pixels, so a
  hairline lands on a fraction of one and antialiases to almost nothing the
  moment the page is viewed at less than full size — which is exactly how a
  print preview shows it. `0.35mm` does not help; it computes straight back to
  1px. The structural rules are `border-2` in a soft grey (`#999`), which
  survives any zoom while reading no heavier than a fine line: two pixels at
  40% ink carries about what one at 80% did.
  **Verify print changes by rasterising the real PDF** (`page.pdf()` then
  `pdftoppm`) and looking at it downscaled — an element screenshot paginates
  nothing, and reading a hairline off a scaled-down image proves nothing either
  way. Measure the pixels if in doubt.
- **Cost and profit are never on it.** It is the customer's copy.
- `@page` has NO margin and the document pads itself in millimetres — that is
  what lets the masthead bar bleed off the left edge. Undo it and there is a
  white gutter beside the bar.
- Colours are stated outright (`#333`) or come from the brand tokens
  (`text-brand` #004aad on the masthead, `border-brand-light` #549bfc on the
  footer stamp), never from the theme's own: a dark theme would print pale text
  onto white paper. Anything relying on a background needs
  `data-print-ink="exact"`, because browsers drop backgrounds from printouts by
  default — which is also why the footer stamp is drawn in borders.
- The shell is hidden by `@media print` in `resources/css/app.css`, keyed on the
  sidebar/topbar/toast `data-slot`s. Adding chrome to the layout means adding it
  there.

**Not built, deliberately** (say so before adding): no `sale_payments` table —
`payment_method` is a single enum on the sale, and later payments are
`CustomerPayment` allocations. No returns scaffolding.
