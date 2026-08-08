---
paths:
  - 'resources/js/components/catalog/**'
---

# Catalog

## The catalogue is one screen; everything else is a drawer over it
There is no product create page, edit page or show page — `/products` is the whole product UI, and the routes are `index`, `store`, `update`, `destroy` only.

- **Right sheet** (`ProductDrawer`) edits a product; the list keeps its scroll and its place in the day's work behind it.
- **Bottom sheet** (`ProductCreateDrawer`) adds one, so the list stays visible and it is obvious whether the thing being typed already exists.
- **Dialogs** (`QuickPurchaseDialog`, `QuickSaleDialog`) buy and sell one product from the row.

Each inner form is keyed on the product and only mounted while open, so opening a second row starts from that row's values rather than the last one's, and a cancelled entry is never waiting there next time. All four post back with `preserveScroll` and close on success.

`ProductFields` holds the four fields both drawers share — change a field once, not twice. Row click opens the drawer, so the action cell stops propagation on its own clicks.
