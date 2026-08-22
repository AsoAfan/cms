---
paths:
  - resources/js/components/money-input.tsx
  - resources/js/components/money-display.tsx
---

# Components

## A field's currency dropdown converts what is in the box
Every money input is `<MoneyInput value currency onChange onCurrencyChange>`, never a bare `<Input inputMode="decimal">`.

Picking a currency in the dropdown CONVERTS the value in place — $18.50 set to dinars becomes 24,420, the same money said differently. Swapping the label and leaving the digits would silently turn eighteen dollars into eighteen dinars. `useRestate()` does it; `MoneyInput` calls `onChange` with the restated value BEFORE `onCurrencyChange`, so the field is never readable as the old number under the new currency.

The dropdown belongs to that one field and changes nothing else on the form. A document's header currency select re-defaults (and converts) only the fields still on the previous header currency, leaving anything switched by hand alone.

Forms post `{field}` plus `{field}_currency` and let the server convert. Never post a figure the client converted. Running totals sum in base currency via `useToBase()`.

## Reviewing a figure in another currency
Two components, and they are not interchangeable:

- `<MoneyDisplay amount>` — a plain figure in the user's display currency. Use it in table cells and anywhere a dropdown per row would be noise.
- `<MoneyReview amount>` — a figure WITH its own currency dropdown, for standalone amounts (invoice total, cost of goods, gross profit, KPI tiles). Read-only: it converts from the base-currency minor units the server sent and never sends anything back. The choice is local state, so it lasts the screen and is not stored — a figure is never silently sitting in a currency somebody picked last week.

## A block of figures gets ONE dropdown, not one each
A summary card is a single statement about a single document — total, cost of goods, profit, paid, owed. A dropdown per row turns five figures into ten controls, and reading the total in dollars against the profit in dinars says nothing anybody wants.

Wrap the block in `<MoneyReviewGroup>` and put one `<MoneyReviewSwitch label="Read in">` in it. Every `<MoneyReview>` inside drops its own dropdown and follows the group; outside a group it keeps one, which is right for a lone figure. Both the switch and its label disappear when there is only one currency on record, so a single-currency shop never sees a control it cannot use.

TRAP worth remembering: the currency dropdown on `<MoneyInput>` was first styled `border-0 bg-transparent text-xs` and users reported the control "isn't in the UI" — it was rendering, but flush against a right-aligned number with no border, tint or pointer cursor, so it read as decoration. A control inside a field needs a divider and a background or it is invisible in practice. Do not restyle these back to borderless.
