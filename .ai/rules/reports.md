---
paths:
  - 'resources/js/components/reports/**'
---

# Reports

## One ActivityTable serves every activity tab on both screens
`ActivityTable` renders all four tabs on both the dashboard and the report screen, because `ActivityQuery` normalises sales, purchases and expenses into one row shape. Pass `tab="all"` for the combined view (adds a Kind column); pass a kind for its own table, where `ACTIVITY_KINDS` supplies that kind's column headers and empty text.

The footer total is a `total` prop taken from `CashFlowQuery`, never summed from the rows on screen — that is what keeps it agreeing with the tiles above it. Omit it wherever the rows are a limited selection, as on the dashboard.

`combineActivity()` merges the three lists newest-first for the combined view. Tab state: the dashboard's "View all" carries the open tab to `/reports?tab=…`, which the report screen reads for its initial tab (`all` maps to `totals`, since the combined list lives under the totals rather than in a tab of its own).
