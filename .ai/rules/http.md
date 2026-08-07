---
paths:
  - 'app/Http/**'
---

# Http

## Auth has no registration; flash messages use Inertia flash data
Authentication is hand-rolled (no Fortify) in `App\Http\Controllers\Auth\*` with Form Requests. There is deliberately NO registration route — this is an internal system. Accounts come from `DatabaseSeeder` until the admin screen in P9.T1. `AuthenticationTest` asserts `/register` 404s; keep it that way.

For one-off user feedback use `App\Support\Flash`:

    Flash::success('Purchase posted.');

    return to_route('purchases.index');

It writes Inertia flash data (`Inertia::flash()`), NOT a shared session prop, so a toast fires once and never resurfaces when the user navigates back. The frontend `FlashToaster` listens on the router's `flash` event. Assert it in tests with `assertInertiaFlash(Flash::KEY, [...])`.
