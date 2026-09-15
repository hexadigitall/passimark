# Sprint 7.5d — Admin analytics: richer KPI tiles + drill-down reports

Follow-up of the Sprint 7.5c dashboard. The landing tiles were bare counts with no context and nowhere to go. This sprint turns them into contextual KPIs, each of which opens a purpose-built drill-down report.

## What changed

### Single source of truth
- New `App\Services\AdminAnalytics` produces every number on the landing and in the reports. All aggregates are:
  - **Zero-data safe** — the `pct()` helper returns `0` on empty totals; null scores render `—`, never `NaN`.
  - **Deterministic** — explicit `orderBy`/`limit` on every row set (recent-activity learners, latest-200 attempts, latest-200 decisions).
  - **Bounded** — score stats are computed over the 2,000 most-recent finished attempts so distribution/median stays fast at catalogue scale (millions of attempts).

### KPI tiles (landing `Passimark/AdminDashboard`)
Every tile is now a clickable card with a value, a context sub-line, a weekly delta pill, and a link to its report:
- **Learners** — active · pending; delta: active this week (+/▼ vs prior week).
- **Attempts** — passed · failed; delta this week.
- **Completed** — completion rate %; delta this week.
- **Average score** — median · best.
- **Approvals (7d)** — all-time · rejected; delta vs prior week.
- **Completions** — pending · across N tracks; delta this week.
- **Sessions** — questions · contentless.
- **Questions** — untagged · no-explanation.
- **Tracks** — regions · sessions.
- **Pending** — oldest waiting N days; delta vs week ago.

### Drill-down reports (`/admin/reports/*`, admin/instructor only)
| Tile link | Report | Data |
|---|---|---|
| `/admin/reports/learners` | **Learners** | roster table (attempts, passes, avg, pass rate, pending/completed, current session + status, last activity) + summary (total/active/weekly active/awaiting). |
| `/admin/reports/attempts` | **Attempts** | status + mode filter chips; ledger of latest 200; score distribution bars; per-mode table; 7-day cadence; summary (finished/passed/failed/pass rate/avg/median/best/lowest). |
| `/admin/reports/attempts?status=completed` | (segmented) | same report, pre-filtered to finished attempts. |
| `/admin/reports/questions` | **Questions** | domain × accuracy, bloom × accuracy, difficulty bands, unused/tagged/explanation/discrimination flags, top-10 weakest items (≥5 uses), never-used samples. |
| `/admin/reports/sessions` | **Sessions** | per-session engagement + content-health flags (contentless / never used) + summary. |
| `/admin/reports/tracks` | **Tracks** | per-cert completion matrix: content size, enrolled, attempts, avg, pass rate, completions, pending. |
| `/admin/reports/approvals` | **Approvals** | full decision ledger with review lag, 8-week trend, pending queue with aging; view filter chips (all/approved/rejected/pending). |

### Quality details
- Six report pages under `resources/js/Pages/Passimark/Reports/` share a `ReportHeader` (back link + framing description) and `StatStrip` (KPI strip) and render inside the role-aware `DashboardLayout`.
- Every table has an empty state; lists that are capped show a note (100 learners / 200 attempts / 200 decisions).
- Added `PassimarkAttempt::user()` relation (needed by the ledger eager load).
- Difficulty bands use dot-free keys (`easy`/`mid`/`hard`) — Laravel's dot-path assertion/serialization splits on `.`, which breaks keys like `"<0.33"`.

## Verification
- New `tests/Feature/AdminReportsTest.php` (9 tests): all six routes render for admin; students forbidden; learners roster counts; attempts scores/distribution/modes/trend + filters; sessions contentless flags + counts; questions usage/accuracy/weakest; tracks sessions/completions/pending; approvals ledger/lag/trend/pending; landing tiles carry rich metrics and correct links.
- Landing `Passimark/AdminDashboard` keeps the flat `report.*` keys (back-compat with `AdminDashboardTest`) while the UI drives off the new `tiles` prop.
- Full suite **77 tests / 7,365 assertions** green; `npm run build` + `php -l` clean.

## Files touched
- `app/Services/AdminAnalytics.php` (new — analytics single source of truth)
- `app/Http/Controllers/PassimarkAdminController.php` (tiles/report wiring + 6 report endpoints)
- `app/Models/PassimarkAttempt.php` (`user()` relation)
- `routes/web.php` (6 `/admin/reports/*` routes)
- `resources/js/Pages/Passimark/AdminDashboard.jsx` (tile cards)
- `resources/js/Pages/Passimark/Reports/*` (6 pages + ReportHeader + StatStrip)
- `tests/Feature/AdminReportsTest.php` (new)