---
paths:
  - 'app/Http/Requests/Purchasing/**'
---

# Requests Purchasing

## A purchase names how it was paid, and out of which account
**This reverses "purchases carry no payment details".** `purchases` now has `payment_method` + `bank_id`, matching `sales` and `expenses` exactly, because money out of an account is the other half of money into one: a balance derived from sales, expenses and repayments alone only ever climbs, and stock is usually the largest thing a shop pays for.

`PurchaseRequest` reads the rule through `NamesPayingBank` like every other payment form — card/transfer require a bank, cash forbids one — so `PaymentMethod::usesBank()` stays the single place that decides. `payment_method` is `required` on the request (a missing method would let a bank ride along with cash); the column defaults to `cash` so invoices recorded before this migration read as "no account named".

A purchase has no part-payment field: an invoice naming an account was paid out of it **in full**, which is the same figure `CashFlowQuery` counts as outcome.
