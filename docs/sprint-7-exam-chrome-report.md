# Sprint 7 report: Pearson VUE exam chrome + Worldwide mastery dashboard

Branch: `feature/sprint-7-exam-chrome`
Shipped: full PHPUnit **52 tests / 6,865 assertions** green (was 48/6,835) + `npm run build` green

## 1. What shipped

### 1.1 Exam experience (Pearson VUE chrome) — `resources/js/Pages/Passimark/Exam.jsx`
| Delivery | Implementation |
|---|---|
| Question palette | Tabbed aside (Palette/Details). Number grid with four states — answered (emerald), flagged (amber), current (white ring), unanswered (slate) — with legend; flagged items survive navigation |
| Flagged review | Header flag toggle + "Review flagged" modal listing flagged question positions; flags are position-keyed |
| Strike-through | Per-option `Strikethrough` button toggling a Set keyed by option; rendered as crossed-out text, styled as a visual aid (selection still authoritative) |
| Calculator | On-screen keypad (`7 8 9 / 4 5 6 * 1 2 3 - 0 . % +`, `( )`, C, =). Home-grown **shunting-yard** evaluator — `tokenise` → RPN `simplify` → `evaluate` — no `eval`, tolerant of empty/bad input (null result) |
| Breaks | Non-practice runs pause the timer at `⌊q/2⌋` and `q−5` (one break at `q−5` for small pools) via a full-screen "Take a breath" dialog; resume continues the countdown |
| Finals no-hint | `attempt.exam.is_final` ⇒ per-question feedback becomes "Answer recorded." — no correct/incorrect reveal; timer auto-finish preserved |
| Countdown source | `attempt.exam.time_minutes ?? attempt.session.time_limit ?? 180` so mocks/finals map to their real durations |

### 1.2 Worldwide mastery dashboard — `resources/js/Pages/Passimark/Dashboard.jsx`
- Renders from the new server `tracks` payload; certs grouped under region sections.
- **Progress rings** — `ProgressRing` SVG: dotted (`strokeDasharray "4 6"`, slate) until the cert's done-sessions equal total, then solid emerald; hub shows `%` + latest θ.
- **θ trendline** — `ThetaSparkline`: polyline over `theta_history` (last ≤12 finished attempts), dashed zero-baseline, sign-coloured dots, needs ≥2 points.
- **Weak-zone heatmap** — `domains` rows: name, correct/total, accuracy bar tinted emerald/amber/orange.
- Session chips + CTAs reuse `statusPresentation` (locked/open/in_progress/completed/pending_approval/approved) so start/resume/request-approval flows are unchanged.

### 1.3 Shell — `resources/js/Layouts/DashboardLayout.jsx`
- θ ability badge (emerald pill) driven by shared `ability.theta`.
- Region nav `<select>` (shared `regions`, "All regions" + per-region cert counts) that re-requests `/` with `?region=` (preserveState, replace).
- Dark `#0F172A` shell + emerald accents already in place; aligned with PWA manifest (background fix is Sprint 8.3).

### 1.4 Design tokens — `tailwind.config.js`
- `pm.deep #0F172A`, `pm.brand #1A9E2D`, `pm.accent #7CFC8F`, display font stack, ring shadow. **Closes the tracker's "no design tokens" risk.**

### 1.5 Backend payload — `PassimarkController` + `HandleInertiaRequests`
- `dashboard()` → `tracks`: per certification track `region`, ordered `sessions` (each with its `progress` slice), `theta_history`, `domains`; `?region=` filters.
- `thetaHistory(int $trackId)` joins attempts→sessions, finished+θ-not-null, latest 12.
- `domainAccuracy(int $trackId)` joins answer→question→session, groups non-null domains, returns name/total/correct/accuracy.
- Shared props: `regions` (distinct region + cert_count) and `ability.theta` (max learner `ability_theta`), both lazy.

## 2. Verification
- `vendor\bin\phpunit` — OK 52 tests, 6,865 assertions (baseline 48/6,835). Includes new `DashboardV4PayloadTest` (4 tests) and the untouched Sprint 1-6 + Sprint 9 suites.
- `npm run build` — vite 5.4.21: `public/build/assets/app-*.js` 361.00 kB, css 26.47 kB.
- `php -l` clean on controller, middleware, new test.
- Full suite run twice (once after the final Exam.jsx `time_minutes` tweak) — both green.

## 3. Design decisions worth knowing
- **Flag model is position-keyed** (question slot number), not question-id — matches Pearson palette semantics and survives navigation without a lookup table.
- **Calculator avoids eval** via a tiny shunting-yard; a null result just shows no `=` line. Evaluates `+−*/%()` only.
- **Breaks only on mocks/finals**: practice sessions have no timer pauses; natural-break thresholds follow finals conventions (half-way + near-end) with a single near-end break for short sets.
- **Per-track stats are server-aggregated** (thetaHistory/domainAccuracy SQL) rather than client-side over all attempts, so the dashboard stays cheap as progress grows.
- **Dashboard metrics derive from `tracks`**, not the flat `sessions`/`progress` props, so the region filter flows through the numbers (dashboards tests verify filter → subset).

## 4. Follow-ups (Sprint 8+)
- Certificates + PWA (`Certificate.jsx`, `PMK-…` credential IDs, QR verification, `manifest.json` background `#0F172A`) — Sprint 8.
- Frontend browser tests (Playwright) still outstanding on the roadmap.
- Real NCLEX 75–145 final bands + admin θ-calibration UI (from Sprint 6 remaining scope).
- Optional: extend typography scale beyond the new tokens.