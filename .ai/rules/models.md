---
paths:
  - app/Models/Sale.php
  - app/Models/Currency.php
  - app/Models/Bank.php
  - 'app/Models/*.php'
  - app/Models/BankTransfer.php
---

# Models

## Every sale names a customer, and amount_paid is what turns the rest into a loan
`sales.customer_id` is REQUIRED (this reversed the old "no customers" decision). Counter trade goes to the seeded **Walk-in** customer, which the sale form and the catalogue's Sell dialog open on — so the requirement costs no keystrokes. `Customer::walkIn()` creates it if somebody removed it, because a counter sale must never fail for want of a buyer to file it under.

`sales.amount_paid` is what was handed over AT THE TIME — a recorded fact like a line's `unit_price`, not a cache. Total − amount_paid − allocations is the loan, and it is derived (`Sale::outstanding()`).

"Paid in full" is settled from the lines in `SaleRequest`, never from a figure the client totalled: the invoice total is not stored, and the client must not round it. `SaleRequest::after()` refuses `amount_paid` greater than the lines come to — an overpaid invoice would put a balance below zero, which nothing here can describe.

## Currencies are rows; the base is fixed once money is recorded
Which currencies this business deals in, and which one the books are kept in, live in `currencies` — managed on Settings → Currencies. Never read `config('money.currency')` to find the base; use `CurrencyService::base()`. The config value is only the seeder's opening code and a fallback for before the first row exists.

Exactly one row is `is_base`, and EVERY monetary column in the application is minor units of it.

`CurrencyService::makeBase()` refuses once any purchase, sale or expense exists. Each stored amount was recorded at a rate current at the time, so no single rate could restate the history — converting at today's would rewrite what past invoices cost. While the books are empty the base moves freely, and doing so deletes every exchange rate, because those quoted the old base.

A currency cannot be removed while it is the base or named on a document. Removing one cascades its rates away.

`enterable()` = base + currencies with a rate on record. A currency added but not yet priced cannot be typed into a money field, which is what keeps `MissingExchangeRateException` unreachable from a form.

## Banks are rows, managed in Settings; payment methods stay an enum
`banks` is a table for the same reason `expense_categories` is one and `payment_method` is not: how you were paid is three things everywhere, but which accounts a business holds is its own list, and "what came through this account" is only answerable if the user could name it. Managed on Settings → Banks (`Settings\BankController`), never seeded — invented bank names are noise in a real business's database.

Only `name` is required, and it is unique — it is the identity on every dropdown. `account_number` and `notes` are filing.

`bank_id` sits on all four tables that carry `payment_method`: `sales`, `purchases`, `expenses`, `customer_payments`. ~~Purchases are deliberately excluded~~ → **a purchase names how it was paid and out of which account too**, because money out of an account is the other half of money into one and a balance that never pays for stock only ever climbs. `restrictOnDelete` throughout, with `Bank::isInUse()` letting the controller say so plainly rather than surfacing the FK as a 500 — it counts manual movements as well, since those are the account's balance as much as a sale is.

A bank is NOT a currency. An account held in dollars still records base-currency minor units like everything else.

What an account holds is **never stored**: `BankBalanceQuery` derives it, and `bank_adjustments` carries the opening balance and anything else trade does not explain. See `.ai/rules/queries.md`.

## A document reference is the user's to set
`purchases.number` / `sales.number` are prefilled, not owned by the system. The drawer opens on `Purchase::nextNumber()` / `Sale::nextNumber()` and the field is editable, on create and on edit alike; the Form Request validates it `nullable` + `unique` (ignoring the row being edited), and a blank one is left out of the header so `Save*Action` keeps the number the document already has. Never re-assign a number the user typed.

`nextNumber()` (in `App\Models\Concerns\FiledUnderAReference`) is **the greatest reference on record plus one**, NOT `max('id') + 1` — counting off the id hands back a number from behind whatever was last filed. Greatest is by `LENGTH(number)` first and then the characters, so PUR-00010 outranks PUR-9 without the database parsing the column.

**Whatever shape that reference is written in is the shape the next one takes**, padding included: after INV/2026/014 comes INV/2026/015, after 999 comes 1000. A model supplies only `referencePrefix()`, which names the sequence used when the table is empty or nothing on it ends in a number. Taken numbers are stepped over — collations differ on case, and a suggestion that fails the unique rule is worse than none.

## A transfer between accounts is one row, and never trade
`bank_transfers` holds money moved between the business's own accounts as ONE row (`from_bank_id`, `to_bank_id`, unsigned `amount`), not a pair of `bank_adjustments`. Two rows can be half-written, half-deleted or edited apart, and the moment they disagree the business appears to have gained or lost money it never had. Held as one row, "a transfer never changes what the business holds in total" is a property of the schema rather than a rule somebody maintains — and deleting one unwinds both sides at once.

It is not an adjustment for a second reason: an adjustment is money trade cannot explain, and a transfer is explained by the other side of itself.

`BankBalanceQuery` reads the same row twice — off `from_bank_id`, on to `to_bank_id`. **Nothing in reporting counts a transfer**: no money entered or left the business, and an arch test keeps `CashFlowQuery`/`ActivityQuery` away from `BankTransfer`, `BankAdjustment` and `BankBalanceQuery` so shifting money between accounts can never read as income or outcome.

The two accounts must differ (`BankTransferRequest`, `different:from_bank_id`), the amount is always positive and always runs from → to, and there is no update path — a wrong transfer is deleted and recorded again. `reason` is optional here, unlike an adjustment's required one: the two accounts and the date already identify it.
