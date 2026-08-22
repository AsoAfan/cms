---
paths:
  - 'app/Actions/Customers/**'
---

# Customers

## A repayment is applied to named invoices, in full
`RecordCustomerPaymentAction` enforces three things no column can, all in one transaction: allocations sum to EXACTLY the payment amount, no invoice takes more than it still owes, and only delivered (`proceed`) invoices can be paid. Money against nothing in particular would make the account balance and the invoice balances disagree.

There is deliberately no update action. A payment is either what came in or it is not, so a wrong one is deleted — the cascade on `customer_payment_allocations` unwinds what it settled — and recorded again.

Nothing here touches stock: the goods left when the sale was delivered. An arch test enforces it, as it does for expenses.

TRAP: reverting a delivered sale back to `ordered` with payments allocated to it leaves money against an invoice that owes nothing, and the customer's balance silently drops. A revert path must refuse a sale with payments against it.
