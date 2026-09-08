---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Frontend-written cookies must be exempt from encryption
`EncryptCookies` is in the default web group, so any cookie JS writes in plain text (`document.cookie = ...`) fails to decrypt and is silently dropped — `$request->cookie('x')` returns null, with no error anywhere.

Add every such cookie to `$middleware->encryptCookies(except: [...])` in bootstrap/app.php. Currently `appearance` (the theme toggle) and `sidebar_state` (shadcn's sidebar). `sidebar_state` was broken this way until 2026-08-08: `sidebarOpen` always came back true.

`tests/Feature/AppearanceTest.php` pins this; it uses `withUnencryptedCookie()`, which is what actually reproduces a browser-set cookie. `withCookie()` does not.
