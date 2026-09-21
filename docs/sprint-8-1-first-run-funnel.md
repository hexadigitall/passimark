# Sprint 8.1 — One-student first-run funnel + catalog chrome

**Status:** planned
**Branch:** `feature/sprint-8-1-first-run-funnel` (cut from `62ae3c0`)
**File health rails:** `App\Models\PassimarkCatalogController` (232 lines) — the controller this sprint
wires into lives at `D:\projects\passimark\app\Http\Controllers\PassimarkCatalogController.php`
(the true git root; anything under `D:\projects\passimark` is a path-mirror artifact and must
never be edited).

## Why this sprint exists
Sprint 7.9b (62ae3c0) proved the complete Uniform-205 catalog is real in the dev DB — 205 certs /
2,870 sessions / 8,610 exams / 167,133 questions. The one true problem left is **surfacing that
catalog to a single real student without a wall of 205 tiles.** This sprint is the "one student"
ladder: a tight first-run funnel that puts every cert one keystroke (omnibox) or two clicks
(browse rails) away, and lands the learner on a dashboard that lights up around *their* track.

## Contract (north star, unchanged)
User-centric, easy to access, easy to find and start certs/exams/assessments/prep — and *this
sprint's rule*: **gating is never removed.** The pass-gated auto-unlock, final-approval gating,
and enrollment auto-unlock all stay exactly as shipped. The funnel only adds *discoverability
rails* (omnibox, browse menu, focus) around them. No gate logic changes.

## Screens (funnel, in order)
```
[Lock Screen]  →  [Splash]  →  [Intro walkthrough]  →  [Login / Sign Up]  →  [Preference Setup]  →  [Permission Prime]  →  [Dashboard]
```
1. **Lock Screen** `GET /` (guest → lock) — brand gate, full-bleed passimark wordmark,
   "Enter" CTA. No nav, no chrome. Every non-route resolves here for guests.
2. **Splash Screen** `GET /splash` — animated brand moment (wordmark + θ tagline), 1800 ms,
   auto-advance or tap-through, → `/intro`.
3. **Intro walkthrough** `GET /intro` — 4 screens (Browse all 205 / One track at a time /
   Adaptive θ / Get certified), pips + dots, **Skip** top-right, prev/next, final CTA → `/login`
   (or a side "I already have an account" link).
4. **Login / Sign Up** — existing auth screens stay; after successful register first-run the
   redirect target is `/setup` instead of `/`.
5. **Preference Setup** `GET /setup` (auth, first-run only) — **focus picker**: certification
   region → chips, select ≥1 (validation: at least one focus), Save → `/permissions` (pickup)
   or skip if already set. Re-editable later from Settings.
6. **Permission Prime** `GET /permissions` (auth, first-run, skippable) — ask for notification +
   deadlines access with clearly labeled allow/deny, skipping upheld. → `/`.
7. **Dashboard** `GET /` (auth) — the refactored one-student surface below.

## Chrome (every authenticated page)
- **Omnibox** `resources/js/Components/Passimark/CertificationOmnibox.jsx` — global typeahead in
  the topbar; search **all 205 certs flat (no region filter)**, arrow-key navigation, Enter →
  the matched track. Backed by the catalog `search()` endpoint (lean, cached 5 min).
- **Browse rails** — topbar "Browse" menu: region → certification arrow dropdowns (region → cert
  arrow rails), so any cert is ≤2 clicks.
- Both wired into `DashboardLayout` topbar chrome so they're present on Dashboard, Track, Profile,
  Settings, Reports.

## Dashboard refactor (one student, not 205 walls)
- **Your-track hero** above the catalog: resume card (continueSession), θ badge, next-required
  session CTA → non-catalog pages stay lean; the catalog moves **below** as "Browse all".
- **Favorites first**: the learner's focus chips filter/order the catalog so the track they care
  about is always at top; everything else is secondary, one omnibox keystroke away.

## Files
- `app/Http/Controllers/PassimarkCatalogController.php` — add lean flat `search()` returning
  cert_key/title/region (no region filter), cached 5 min; powers omnibox + browse.
- `routes/web.php` — guest funnel routes (lock/splash/intro), auth setup/permissions routes.
- `resources/js/Components/Passimark/CertificationOmnibox.jsx` (new), browse menu component (new).
- `resources/js/Layouts/DashboardLayout.jsx` — omnibox + browse rails in topbar.
- `resources/js/Pages/Passimark/Dashboard.jsx`, `resources/js/Pages/Passimark/Setup.jsx` (new —
  focus picker), `resources/js/Pages/Passimark/Permissions.jsx` (new).
- `app/Models/PassimarkLearnerFocus.php` (new) + migration — persists selected cert keys per user.
- Tests: `tests/Feature/PassimarkFirstRunFunnelTest.php` (funnel redirects + skips + focus persist),
  `tests/Feature/PassimarkCatalogSearchTest.php` (flat search, no region filter, cached).

## Verification
- Full suite green (110 tests / 44,586 assertions baseline → grows).
- `npm run build` clean; `php -l` clean.
- Manual: fresh user walks Lock→Splash→Intro→SignUp→Setup(≥1)→Permissions(skip ok)→Dashboard
  with omnibox returning all 205 certs regardless of region.
