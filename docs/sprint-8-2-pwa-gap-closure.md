# Sprint 8.2: PWA gap closure (brand-correct offline shell)

Sprint target (from audit): the docs claimed "PWA shipped" in Sprint 8 but the tree had **no service worker** — only a manifest link in `app.blade.php` and `public/manifest.json`. This slice closes that gap with the lean, dependency-free approach the project already established (no new npm packages — the manifest/SW live in `public/` and are served as static assets).

## Status
- **Shipped:** `public/sw.js` (network-first navigations, cache-first hashed build assets, install-time precache of `/`, `/manifest.json`, `/sw.js`; `skipWaiting` + `clients.claim`; skips non-GET; same-origin only) — 1.7 kB.
- **Shipped:** SW registration in `resources/views/app.blade.php` (load-time, silent catch; PWA stays progressive enhancement — no failure path).
- **Already present (audited, untouched):** `public/manifest.json` (1,104 B) — brand `#0F172A` background / `#1A9E2D` theme, `standalone`, icons **16→1024 px** (passimark_icon_*), `start_url /`. `app.blade.php` already declared the `<link rel="manifest">`.

## Ground-truth checks (this sprint)
- `node --check public\sw.js` → clean
- `php -l resources\views\app.blade.php` → clean
- suite green (110 tests / 44,586 assertions at Sprint 7.9b baseline; + any Sprint 8 work)
- `npm run build` green (Vite manifest copied — no SW interference with hashed assets)

## Committed here
- `public/sw.js` — new
- `resources/views/app.blade.php` — embedded load-time SW registration

## Not in this slice (tracked)
- OS-specific / native packaging (Windows .exe, macOS, Linux, Android, iOS) — formally not started, by design; see [full-implementation-plan.md](full-implementation-plan.md) §11 and Sprint 9/11 in [sprint-progress-tracker.md](sprint-progress-tracker.md).
- CI multi-platform build matrices — only the single `ci.yml` (composer+node+test+build) exists; no per-OS matrix yet (Sprint 9/11 scope).
- Full offline-first runtime caching of Inertia page JSON — SW handles the shell; deeper grounding is Sprint 8.1 funnel chrome work.
