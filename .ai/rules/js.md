---
paths:
  - 'resources/js/**'
---

# Js

## Reuse the shared frontend primitives
Build screens from these before writing new markup:

- `<AppLayout breadcrumbs={...}>` — the authenticated shell. Attach as a persistent layout: `Page.layout = (page) => <AppLayout ...>{page}</AppLayout>`. Arrow-function layout components must be wrapped in an array in Inertia v3.
- `<DataTable rows columns state getRowKey>` — server-driven sort/search/filter/paginate. Column `key` must match a key whitelisted by `TableQuery::sortable()`.
- `<PageHeader title description actions>`, `<EmptyState>`, `<FormField>`, `<MoneyDisplay amount>`, `<MoneyInput>`, `<DateRangePicker>`.
- Money arrives as integer minor units. Render with `<MoneyDisplay>` or `useFormatMoney()`; never divide by 100 inline.

`resources/js/components/ui/*` and `hooks/use-mobile.ts` are shadcn registry code — regenerable, and excluded from ESLint. Do not hand-edit them; wrap them in a component under `components/` instead.

## Every amount on the wire is base currency
The `currency` prop carries `base`, `display`, `locale` and the currencies on offer with their rates. Amounts in props are **always base-currency minor units** — nothing is ever converted before it is sent.

- `useFormatMoney()` converts to the user's chosen display currency and formats at that currency's `fraction_digits`. It is the only place a figure is converted for reading, so every screen switches together and nothing is converted twice. Never label a figure with a hardcoded currency code — `format` puts the currency on the number.
- **Every money input is `<MoneyInput value currency onChange onCurrencyChange>`**, never a bare `<Input inputMode="decimal">`. Its dropdown belongs to that one field, and picking a currency **converts what is in the box** ($18.50 → 24,420 dinars). Swapping the label and leaving the digits would turn eighteen dollars into eighteen dinars.
- Forms hold `{field}` plus `{field}_currency` and post both; the server converts. Never post a figure the client converted.
- Running totals must be summed in base currency via `useToBase(value, currency)` — a mixed-currency invoice has no total in either currency alone. `convertToBase`/`convertFromBase` in `lib/money.ts` use the same integer rounding as PHP's `Money::multipliedByFraction`, so a live preview and the stored figure agree to the last dinar.
- `useRestate(value, from, to)` is the "same money, said differently" conversion behind the dropdown.

This project uses Base UI, not Radix: compose with `render={<Component />}`, not `asChild`. Forms use `FieldGroup`/`Field`, and toasts come from `@/components/ui/toast`, not sonner.
