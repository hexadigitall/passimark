# Sprint 7: Pearson VUE exam chrome + Worldwide mastery dashboard

Branch: `feature/sprint-7-exam-chrome` (from `feature/sprint-6-irt-cat-engine`)
Status: **done** — full suite 52 tests / 6,865 assertions green, `npm run build` green

## Scope (from sprint-5-v4-worldwide-catalog.md §7)

- **7.1 Exam.jsx chrome** — question palette, flag/review, strike-through, calculator, break dialogs, no-hints on finals
- **7.2 Dashboard.jsx** — cert grid grouped by region, dotted→solid progress rings, θ trendline, weak-zone heatmap
- **7.3 Layout** — θ ability badge + region nav, dark `#0F172A`, brand green `#1A9E2D` / accent `#7CFC8F`
- **7.4 Design tokens** — close the "no design tokens" risk in `tailwind.config.js`
- **7.5 Verification** — `npm run build` + PHPUnit

## Implementation

### Backend — dashboard payload (7.2/7.3 foundations)
`app/Http/Controllers/PassimarkController.php`
- `dashboard()` renders with the existing `sessions`/`progress` plus a new `tracks` prop: one item per certification track with `region`, ordered `sessions` (each carrying its `progress` slice), `theta_history`, and `domains`.
- `?region=` query param filters to a single region (`when($region, …)`).
- `thetaHistory(int $trackId)` — final θ of the learner's last ≤12 finished attempts per track, oldest first (joins `passimark_attempts` → `passimark_sessions`).
- `domainAccuracy(int $trackId)` — per-domain correct/total/accuracy across the track's answered items (joins attempt-answers → questions → sessions, `whereNotNull domain`, accuracy rounded to 4dp).

`app/Http/Middleware/HandleInertiaRequests.php`
- shares `regions` (distinct `passimark_certification_tracks.region` + cert count, for the shell nav) and `ability.theta` (the learner's highest `passimark_progress.ability_theta`, lazy-evaluated).

### Exam.jsx (7.1)
- Palette aside (tabbed Palette/Details): grid of answered / current / flagged / unanswered cells, per-state legend, and a "Review flagged" modal. Flags are position-keyed so they survive navigation and appear in the review list.
- Strike-through: per-option toggle (Set of option keys), rendered as crossed-out option text; flagged in the UI as a visual aid only (submitted selection is what counts).
- On-screen calculator: numeric keypad + `+ - * / % ( )`, with a small dependency-free shunting-yard evaluator (`tokenise` → RPN via `simplify` → `evaluate`) — no `eval`. Toggles from the header.
- Natural breaks: on mocks/finals (`!practice`) the timer pauses at `⌊q/2⌋` and `q−5` (single break at `q−5` for small sets) with a "Take a breath" dialog; practice gets none. Timer effect guards on `breakOpen`.
- Finals no-hint: when `attempt.exam.is_final`, per-question feedback is reduced to "Answer recorded." (no correct/incorrect reveal); the countdown still auto-finishes.
- Uses `attempt.exam.time_minutes ?? attempt.session.time_limit ?? 180` for the countdown so v4 finals/mocks map to their real scaled durations.

### Dashboard.jsx (7.2)
- Rewritten to render from `tracks`: regions as sections, one card per cert.
- `ProgressRing` — 80px SVG ring; `strokeDasharray "4 6"` (dotted, slate) until the cert's approved/completed sessions reach total, then a solid emerald ring; `%` + latest θ in the hub.
- `ThetaSparkline` — compact SVG polyline of `theta_history` with a dashed θ=0 baseline, dots colored by sign, "early/now" axis; needs ≥2 points.
- Weak-zone heatmap — per-domain rows with correct/total and an accuracy bar tinted emerald (≥0.8) / amber (≥0.5) / orange, driven by `domains`.
- Metrics row recomputed from the track payload (completion, avg score, ability θ, active certs) so it reflects the active region filter; existing `statusPresentation` map reused for session chips + CTAs (locked/open/in_progress/completed/pending/approved still start / resume / request-approval correctly).

### DashboardLayout (7.3)
- Top bar: θ ability badge (emerald pill, `θ x.xx`) when a number is shared, and a region `<select>` that re-requests `/` with `?region=` (preserveState + replace), listing `regions` with cert counts.
- Shell styling already dark (`#0F172A` scale); brand green used for accent states.

### Design tokens (7.4)
`tailwind.config.js` now extends `pm.deep: #0F172A`, `pm.brand: #1A9E2D`, `pm.accent: #7CFC8F`, a `display` font stack, and a `ring` shadow. Closes the tracker's "no design tokens" known risk.

### Tests (7.5)
`tests/Feature/DashboardV4PayloadTest.php` (4 tests, seeded via `PassimarkSeeder` + `WorldwidePassimarkCatalogSeeder` two-region fixture):
1. dashboard shares `tracks` grouped across `GLOBAL-CLOUD` + `USA-IT-SECURITY`, with all per-track fields and empty trend/heatmap for a fresh learner
2. `?region=GLOBAL-CLOUD` returns only the `aws-ccp` track (proper subset of the unfiltered set)
3. a finished attempt (θ 1.3) + one answered question produces `theta_history: [1.3]` and a `security & risk management` heatmap row (1/1, accuracy 1.0) under the cissp track
4. shell props expose non-empty `regions` and `ability.theta`

Note: the catalog seeder replaces the legacy ladder (no `passimark_sessions` row id=1 survives reseed), so the attempt/domain fixture creates its own question, mirroring `WorldwideCatalogSeederTest`.

## Verification
- `vendor\bin\phpunit` → **OK (52 tests, 6865 assertions)** (was 48/6835)
- `npm run build` → green (vite 5.4.21; app-*.js 361.00 kB, css 26.47 kB)
- `php -l` clean on touched controllers/middleware/tests
- Branch pushed; trackers/docs updated