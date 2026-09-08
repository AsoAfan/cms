---
paths:
  - 'app/Http/Controllers/Expenses/**'
---

# Expenses

## Expenses are not purchases, and must never touch stock
Buying goods to resell **increases inventory** and only becomes a cost when those goods are sold. Rent is a cost **the moment it is paid**. Mixing them would double-count the first and mistime the second.

So nothing in `App\Models\Expense`, `App\Http\Controllers\Expenses` or `App\Http\Requests\Expenses` may reference `InventoryService`, `StockMovement`, `StockBatch`, or the stock queries — and inventory may not reference expenses. Two arch tests in `tests/Unit/ArchTest.php` enforce both directions. If you ever find yourself wanting to cross that line, the answer is a purchase, not an expense.

For P7: net profit = gross profit (revenue − COGS) − expenses. Expenses enter the accounts only at that last subtraction, never inside COGS.

`expense_categories` is a table, not an enum — unlike payment methods, every business names its own costs, and "what did we spend on X" is only answerable if the user could name X. A category with expenses against it cannot be deleted (FK restrict, plus a friendly guard in the controller).

`TableQuery::sum()` totals a column across everything the current filters match, ignoring pagination. The expenses screen uses it for the "shown total"; report screens in P7 should too.

## An expense is identified by a required title, not a reference number
`expenses.title` is required and free text — "February rent", "Van diesel". The receipt/invoice `reference` column was removed: a category and an amount alone leave a row nobody can identify a month later, and a receipt number identified the paper rather than the spending.

Consequences: the list searches `title` and `notes`; `ActivityQuery` sends `label` = title and `detail` = category name (so the expense tab reads Title/Category, mirroring Number/Supplier on purchases). Do not reintroduce a reference field without asking — notes covers tracing a figure back to paperwork.
