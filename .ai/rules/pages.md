---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Never read page props inside Page.layout — it silently breaks SSR
`Page.layout = (page) => <AppLayout breadcrumbs={f(page.props.x)}>{page}</AppLayout>` type-checks, renders fine in the browser, and passes feature tests — but SSR produces an empty `<div id="app"></div>` and silently falls back to client rendering. No error is logged anywhere.

For a layout value that depends on page props (a breadcrumb carrying a record's name, say), use Inertia v3 layout props instead:

    ProductsEdit.layout = [AppLayout];

    // inside the component, during render:
    setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({ breadcrumbs: [...] });

Static breadcrumbs can stay in the render-function form: `Page.layout = (page) => <AppLayout breadcrumbs={CRUMBS}>{page}</AppLayout>`.

To check: request the page and grep for `data-server-rendered="true"`. A Vite dev server must be running for SSR to be active in development.
