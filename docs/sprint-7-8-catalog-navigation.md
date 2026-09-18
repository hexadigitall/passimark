# Sprint 7.8 — Catalog navigation: category tiles → cert bundles → track

**Status: Complete** | Branch: `feature/sprint-7-5-content-coherence`

## Problem
The learner dashboard (`GET /`) is the only learner route. `PassimarkController::dashboard()`
renders **one giant card per track, each expanding its full session ladder, θ sparkline and
domain heatmap**, and ships every session of every track. With the worldwide catalog (17
certs) it already scrolls forever; at the 205-cert target it is unreadable and the payload
is an N+1 blowup (`thetaHistory()` + `domainAccuracy()` run per track). There is no
drill-down: no certification page, no bundle/variant chooser, no shareable URLs.

Sprint 7.6 established that a **certification is a category** (`cert_key`) and each
`passimark_certification_tracks` row is one **bundle/variant** under it. The dashboard never
surfaced that hierarchy.

## Design — three-level information architecture
```
Level 0  GET /                          Dashboard   → certification CATEGORY tiles (region sections)
Level 1  GET /certs/{certKey}           Cert screen → BUNDLE cards + cert overview
Level 2  GET /certs/{certKey}/{slug}    Track screen→ session ladder, ring, θ, domain mastery
```

- **Tiles are `cert_key` categories**, not bundles — bounded by the number of certifications,
  never duplicated by variants.
- **The cert screen is the bundle chooser.** It always renders the cert overview plus one card
  per bundle (`variant_label` / `source` badge), so a single-bundle cert is still a meaningful
  screen rather than a dead end.
- **The track screen is the current heavy card**, now scoped to one bundle.
- Region filter (`?region=`) applies at level 0 and is carried through links via the shared
  `regions` prop; level links keep it as a query string.

### Payload discipline (the scalability fix)
| Level | Sends | Does not send |
|---|---|---|
| 0 | per-category aggregates: `bundle_count`, `sessions_total/done`, `percent`, `theta`, `has_progress` | session arrays |
| 1 | bundle summaries + aggregate domains | session arrays |
| 2 | one bundle's full session ladder, θ history, domain accuracy | other bundles' sessions |

Aggregates come from bulk queries (`withCount('sessions')`, one grouped progress query, one
grouped θ query) so there is no per-track query. `thetaHistory()` / `domainAccuracy()` move to
`App\Services\TrackProgressService` and are reused only at level 2.

### Routing
```php
Route::get('/certs/{certKey}', [PassimarkCatalogController::class, 'cert'])->name('passimark.cert');
Route::get('/certs/{certKey}/{track:slug}', [PassimarkCatalogController::class, 'bundle'])->name('passimark.bundle');
```
Bundle binding uses the explicit `{track:slug}` field binding so the model's default key stays
`id` (admin CRUD binds `{certificationTrack}` by id and must not change).

## UX
- Responsive tile grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4`; compact
  tiles with provider monogram, cert title + code, region chip, thin progress bar, θ, and a
  "Bundles ×N" badge only when `N > 1`.
- "Continue where you left off" hero above the grid (most recent open/in-progress session).
- Client-side search + region sections so the 205-cert catalog stays navigable.
- Whole tile is one Inertia `<Link>` (keyboard focus ring, ≥44px tap target, `aria-label`).
- Breadcrumbs on levels 1–2 (`Dashboard › {region} › {cert} › {bundle}`) via a shared component;
  Inertia links preserve scroll/state.
- Per-level empty states.

## Verification
- **Full suite green: 100 tests, 27,529 assertions** (`php vendor/bin/phpunit`); up from
  93 / 27,492 in 7.7 (net +7 tests).
- **New `tests/Feature/CatalogNavigationTest.php`** (7 tests): tile aggregates grouped by region,
  region filter narrowing, cert screen bundles + URLs, 404 for unknown certs, bundle ladder +
  siblings, 404 on cert/bundle mismatch, and the continue hero payload.
- **Re-pointed `DashboardV4PayloadTest`** from the removed `tracks` prop to `sections` /
  `stats`; moved θ-history + domain-accuracy assertions to the bundle route
  (`/certs/cissp/cissp-v2`) where they now belong.
- **Re-pointed `MultiBundleCatalogTest`** (`test_cert_screen_groups_bundles_under_one_category`)
  to assert the shared `cissp` category via `/certs/cissp` and its dashboard tile.
- `php -l` clean on the 5 changed backend files; `npm run build` clean (1998 modules,
  `app-B4uIUxGr.js` 429.83 kB / 123.98 kB gzip).

## Out of scope
- Admin CRUD (already lives in the Control Center / `PassimarkAdminController`).
- A discipline/family taxonomy for tile grouping (region sections used instead).
