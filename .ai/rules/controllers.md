---
paths:
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/LoanController.php
---

# Controllers

## Index screens use the table query helpers
Every list screen goes through `InteractsWithTables` (already on the base `Controller`), which feeds the frontend `DataTable`:

    $table = $this->table(Product::query())
        ->searchable(['name', 'sku'])
        ->sortable(['name', 'created_at'], default: 'name')
        ->filterable(['category_id']);

    return Inertia::render('products/index', $this->tableProps($table));

`tableProps()` returns `rows` (paginator) and `table` (search/sort/filters/per_page state) — the exact props `DataTable` expects.

Sortable and filterable columns are whitelists: request input never reaches an ORDER BY or WHERE by name. A filter needing more than equality takes a closure, keyed by the name the request uses. Page size is clamped at 200.

## Renaming a document is its own endpoint, not update()
`PATCH purchases/{purchase}/number` and `PATCH sales/{sale}/number` (`*.rename`) change only the filing reference, edited in place on the document's own page by `<EditableReference>`.

They exist because `update()` runs the whole document back through `Save*Action`, which reverts the stock and re-issues it — an invoice whose goods have been sold on refuses that outright, so renaming through `update()` would leave a reference nobody can correct. A reference is filing, not ledger data. Keep the two apart: never fold rename back into `update`, and never let `rename` touch anything but `number`.

The rules for the reference itself live in `App\Http\Requests\Concerns\FilesUnderAReference`, shared by the drawer request (blank = keep the current number) and the rename request (`required`).

## Loans is one screen for both directions, and it derives everything
`/loans` (Analysis → Loans) shows both sides at once: goods owed out (`GoodsOwedQuery`) and money owed in (`CustomerBalanceQuery`). They are made of different things but they are one question to whoever runs the shop, and split across two screens neither got read.

- Every figure is a **position, not a flow** — what stands today. That is why this screen takes no date range, unlike the report beside it.
- The goods table is the shopping list: demand summed across every waiting order against on-hand, with the sale numbers each line is holding up (`GoodsOwedQuery::waitingOn()`, kept off `get()` so the sale screen does not pay for it). Never sum what each invoice is short of — that under-buys.
- Customer rows come from the same query their own screen uses, so the two can never disagree. A negative balance (a credit) is listed, not hidden: dropping it would leave the total unexplainable from the rows.
- The dashboard and report tiles stay as glance figures; the detail lives here.
