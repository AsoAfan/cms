---
paths:
  - resources/js/components/money-input.tsx
---

# Components

## A field's currency dropdown converts what is in the box
Every money input is `<MoneyInput value currency onChange onCurrencyChange>`, never a bare `<Input inputMode="decimal">`.

Picking a currency in the dropdown CONVERTS the value in place — $18.50 set to dinars becomes 24,420, the same money said differently. Swapping the label and leaving the digits would silently turn eighteen dollars into eighteen dinars. `useRestate()` does it; `MoneyInput` calls `onChange` with the restated value BEFORE `onCurrencyChange`, so the field is never readable as the old number under the new currency.

The dropdown belongs to that one field and changes nothing else on the form. A document's header currency select re-defaults (and converts) only the fields still on the previous header currency, leaving anything switched by hand alone.

Forms post `{field}` plus `{field}_currency` and let the server convert. Never post a figure the client converted. Running totals sum in base currency via `useToBase()`.
