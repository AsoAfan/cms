---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Index screens use the table query helpers
Every list screen goes through `InteractsWithTables` (already on the base `Controller`), which feeds the frontend `DataTable`:

    $table = $this->table(Product::query())
        ->searchable(['name', 'sku'])
        ->sortable(['name', 'created_at'], default: 'name')
        ->filterable(['category_id']);

    return Inertia::render('products/index', $this->tableProps($table));

`tableProps()` returns `rows` (paginator) and `table` (search/sort/filters/per_page state) — the exact props `DataTable` expects.

Sortable and filterable columns are whitelists: request input never reaches an ORDER BY or WHERE by name. A filter needing more than equality takes a closure, keyed by the name the request uses. Page size is clamped at 200.
