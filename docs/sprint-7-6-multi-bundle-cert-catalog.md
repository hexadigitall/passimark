# Sprint 7.6 — Multi-Bundle Certification Catalog

**Status: Complete** | Branch: `feature/sprint-7-5-content-coherence`

## Problem
Passimark's original "CISSP bundle" was the v1 `PassimarkSeeder` generic track (46-session
ladder, ~5 sessions with questions). The current CISSP bundle (from
`D:\Downloads\CISSP\CISSP\index.html`) is a second, independent bundle. The old schema
(`passimark_certification_tracks` with a globally-unique `slug`) could only express ONE row
per certification: a new bundle imported for the same cert clobbered the existing row
(`firstOrCreate`/`updateOrCreate` by slug). Users adding new bundle files had no way to make
a second variant coexist, and no category concept existed to group them.

## Design
A **certification is a category**; each `passimark_certification_tracks` row is one
**bundle/variant** under that category.

- `cert_key` string — the certification category key (e.g. `cissp`). Indexed; multiple rows
  may share it. Falls back to `slug` for legacy rows (`PassimarkCertificationTrack::certKey()`).
- `variant_label` string — human-readable variant name (e.g. "Legacy v1", "Worldwide").
- `source` string — provenance, server-managed only: `seed:<class>` or `import:<package_id>`.

### Single placement path: `PassimarkCertificationTrack::placeBundle($attrs, $source)`
Creates, reuses or adopts exactly one row per bundle and returns
`[track, created(bool), slug_changed(bool)]`:

1. **Same source + same cert category** already present → reuse that row (idempotent
   re-seed / re-import of the same bundle).
2. **Leftover placeholder** → adopt an existing row whose `slug` matches the preferred slug
   and whose `source` is null/`legacy` (pre-multi-bundle rows, incl. the default `cissp`
   track created by migration `000003`). Enrollment, URLs (`/passimark/cissp`) and the
   familiar slug are preserved.
3. **Anything else** → create a new row; `resolveUniqueSlug()` de-conflicts the slug
   deterministically (`cissp` → `cissp-v2` → `cissp-v3`, …). A second CISSP bundle never
   overwrites the first.

Key bug caught in verification: the first version re-used any row sharing `source`,
which collapsed all same-source bundles into one row (CISSP absorbed `sec`). Fixed to also
require the cert category to match.

### Callers converted
| Path | Source stamp |
|---|---|
| `CISSPBundleSeeder` (the current CISSP bundle) | `seed:cissp-bundle` |
| `PassimarkSeeder` (v1 legacy ladder) | `seed:passimark-v1`, cert_key `cissp`, variant "Legacy v1" |
| `WorldwidePassimarkCatalogSeeder` | `seed:worldwide-17`, cert_key = slug, additive |
| `Uniform205CatalogSeeder` | `seed:uniform-205`, cert_key = slug |
| `PackageImporter` (.psmk uploads) | `import:{package_id}`, cert_key = `cert_key` opt or slug |

The Worldwide catalog is now **additive by design**: against an existing bundle it forks
(`cissp-v2`) instead of overwriting; against a legacy/default placeholder it adopts so
single-catalog installs keep `cissp`.

### Admin
`PassimarkAdminController::certificationTrackData()` accepts `cert_key` and `variant_label`
(`source` stays server-managed). Admin.jsx track form gained both fields; the Tracks panel
shows category chips with bundle counts + per-row source/variant/cert.

### Analytics & UI
- New `Certifications` KPI tile (distinct `cert_key` count across the catalog).
- `AdminAnalytics::tracks()`: rows carry `cert_key`/`variant_label`; new `categories`
  group by cert_key with variant listings; `summary.certs` added while `summary.tracks`
  keeps meaning "bundle rows" (backwards compatible).
- Tracks report: "Certifications" stat + category chips + variant sub-line per row.
- Learner Dashboard: track cards show `cert_key · variant_label` under the title;
  `PassimarkController::dashboard` track map includes both fields.

## Verification
- `vendor/bin/phpunit`: **85 tests, 7,428 assertions — green**
- `npm run build`: green
- `php -l` on all changed PHP: clean
- New `MultiBundleCatalogTest` (9 tests) covers: two bundles share a category; same-source
  reseed reuse; legacy placeholder adoption; third-bundle slug sequence; admin CRUD of
  cert fields; importer source stamping + forking; analytics categories; dashboard payload.
- `DashboardV4PayloadTest` updated for the new coexistence (`cissp` + `cissp-v2` same
  category); `WorldwideCatalogSeederTest` and `PassimarkPackageTest` kept green (worldwide
  adoption preserves `cissp`; importer adopts a hand-made row so `created.tracks` stays 0
  on round-trips).

## Files changed
- Migration `2026_01_01_000011_add_cert_category_columns.php` (+backfill `cert_key=slug`,
  `source='legacy'`)
- `app/Models/PassimarkCertificationTrack.php` (`certKey`, `scopeForCert`,
  `resolveUniqueSlug`, `placeBundle`; fillable/casts)
- `app/Http/Controllers/PassimarkAdminController.php`, `PassimarkController.php`
- `app/Services/AdminAnalytics.php`
- `app/Services/PassimarkPackage/PackageImporter.php`
- `database/seeders/{CISSPBundleSeeder,PassimarkSeeder,WorldwidePassimarkCatalogSeeder,Uniform205CatalogSeeder}.php`
- `resources/js/Pages/Passimark/{Dashboard.jsx,Admin.jsx}`, `resources/js/Pages/Passimark/Reports/Tracks.jsx`
- `tests/Feature/{MultiBundleCatalogTest.php (new),DashboardV4PayloadTest.php,AdminReportsTest.php}`

## Known risks / notes
- Ecosystem additions are additive, so existing installs keep their slugs; a fresh
  `migrate:fresh` starts with the legacy `cissp` placeholder that the first real bundle
  adopts.
- Admin-created tracks without a `source` are treated as placeholders (a later seed/import
  for the same slug will adopt them) — consistent with legacy rows.