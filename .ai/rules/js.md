---
paths:
  - 'resources/js/**'
---

# Js

## Reuse the shared frontend primitives
Build screens from these before writing new markup:

- `<AppLayout breadcrumbs={...}>` — the authenticated shell. Attach as a persistent layout: `Page.layout = (page) => <AppLayout ...>{page}</AppLayout>`. Arrow-function layout components must be wrapped in an array in Inertia v3.
- `<DataTable rows columns state getRowKey>` — server-driven sort/search/filter/paginate. Column `key` must match a key whitelisted by `TableQuery::sortable()`.
- `<PageHeader title description actions>`, `<EmptyState>`, `<FormField>`, `<MoneyDisplay amount>`, `<DateRangePicker>`.
- Money arrives as integer minor units. Render with `<MoneyDisplay>` or `useFormatMoney()`; never divide by 100 inline.

`resources/js/components/ui/*` and `hooks/use-mobile.ts` are shadcn registry code — regenerable, and excluded from ESLint. Do not hand-edit them; wrap them in a component under `components/` instead.

This project uses Base UI, not Radix: compose with `render={<Component />}`, not `asChild`. Forms use `FieldGroup`/`Field`, and toasts come from `@/components/ui/toast`, not sonner.
