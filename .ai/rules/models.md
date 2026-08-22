---
paths:
  - app/Models/Sale.php
  - app/Models/Currency.php
  - app/Models/Bank.php
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

`bank_id` sits on the three tables that carry `payment_method`: `sales`, `expenses`, `customer_payments`. Purchases are deliberately excluded — they have no payment method at all, so giving one a bank would mean inventing the other first. `restrictOnDelete` throughout, with `Bank::isInUse()` letting the controller say so plainly rather than surfacing the FK as a 500.

A bank is NOT a currency. An account held in dollars still records base-currency minor units like everything else.
