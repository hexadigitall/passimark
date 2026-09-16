# Sprint Progress Tracker

This is the live index of sprint status. Detailed task lists and evidence live in each sprint's own document; this file only tracks status and links.

## Status legend
- `done` — implemented and verified by a passing automated test and a production build
- `in progress` — partially implemented, tracked in the linked doc's "Next slices" section
- `planned` — scoped but not started

## Sprint 0: Environment and foundation
**Status:** done
**Evidence:** [sprint-0-final-report.md](sprint-0-final-report.md), [sprint-0-closure-verification.md](sprint-0-closure-verification.md)
- App boots, migrates, and seeds cleanly
- Auth flow and roles verified
- Git repository initialized and pushed to `origin`

## Sprint 1: Student dashboard and auth UI
**Status:** done
**Evidence:** [sprint-1-detailed-tasks.md](sprint-1-detailed-tasks.md)
- Login/registration with validation error handling
- Dashboard, profile, and settings pages
- Session status cards wired to real backend state
- Sessions/progress API with phase and status filtering

## Sprint 2: Adaptive exam flow and result scoring
**Status:** done
**Evidence:** [sprint-2-progress.md](sprint-2-progress.md)
- Real CAT/timed/practice exam UI with timer, instructions gate, and progress indicator
- Answer submission, theta updates, and automatic completion
- Dedicated result screen with answer review and attempt history
- Attempt/question ownership and option validation

## Sprint 3: Admin approval and educator workflow
**Status:** in progress
**Evidence:** [sprint-3-progress.md](sprint-3-progress.md)
- Role middleware, approval queue, and approve/reject with required notes — done
- Session/exam/question CRUD with validation — done
- Admin content workspace and reporting cards — done
- Bulk question import with nested validation — done
- Remaining: richer bulk-operation history, deeper learner-level reporting

## Sprint 4: Certification track and tag taxonomy
**Status:** complete
**Evidence:** [sprint-4-taxonomy-plan.md](sprint-4-taxonomy-plan.md)
- Web favicon, apple-touch-icon, and manifest icon set wired into the app shell — done
- Coherence pass: real logo wired into the app shell, flash messages now render as a dismissible banner, admin control center has a return path to the dashboard, and Register visually matches Login — done
- Certification track model, CRUD, and admin UI — done; a second certification track and independently-scoped session can now be created without touching code
- Tag taxonomy for `domain`/`bloom_level` — done: `passimark_tags` + question/session pivots, `PassimarkTag` model, `passimark:backfill-tags` console command (idempotent, non-destructive), admin Tags screen + tag selectors in session/question forms, `tag_ids` validated against `passimark_tags`, in-use tag deletes blocked, legacy `domain`/`bloom_level` columns retained as read-compat and deprecated
- Full PHPUnit suite (19 tests / 160 assertions) and `npm run build` green
- **Sprint 4.5 — CISSP textbook bundle (content extraction) — done:** Hexadigitall 45-day textbook audited and extracted into a committed bundle ([cissp-prep-bundle.md](cissp-prep-bundle.md)); 46 sessions, 980 MC items (daily CAT drills, Phase 1 diagnostic, simulated CAT, 2 full mocks), 105 flashcards; `passimark:extract-cissp` + `CISSPBundleSeeder` prove the bundle-per-cert design (track + ordered sessions + approval gating + tags); PHPUnit **24 tests / 6084 assertions** green

## Sprint 5-8: v4.0 Worldwide catalog (NEW — approved spec JAN 2026)
**Status:** Sprint 5 **done**, Sprint 6 **done**, Sprint 7 **done** (Sprint 8 planned)
**Evidence:** [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md), [worldwide-catalog-sprint-5-report.md](worldwide-catalog-sprint-5-report.md), [sprint-6-irt-cat-engine.md](sprint-6-irt-cat-engine.md), [sprint-6-irt-cat-engine-report.md](sprint-6-irt-cat-engine-report.md), [sprint-7-exam-chrome.md](sprint-7-exam-chrome.md), [sprint-7-exam-chrome-report.md](sprint-7-exam-chrome-report.md), [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md)
- **Sprint 5 — worldwide catalog schema + seeder — done**: migration 000006 (sessions `phase_type`/`cert_slug`/`theta_required`/`questions_target`/`time_minutes`, exams `time_minutes`/`is_final`/`irt_enabled`, questions `correct_key`, tracks `region`); `WorldwidePassimarkCatalogSeeder` (17 flagship certs, 346 sessions / 1,038 exams / 7 regions) + `Uniform205CatalogSeeder` (205 certs, uniform 14-session ladder: 2,870 sessions / 8,610 exams / 9 regions — spec §4b drift zero); `SEED_CATALOG`/`SEED_REGIONS`/`SEED_CERTS`/`SEED_LIMIT` switches; v4 pass-gated auto-unlock (`PassimarkController::autoUnlockNext`), phase-1 first lesson open per cert, finals approval-gated; full suite **28 tests / 6,660 assertions** green
- **Sprint 6 — CAT engine v4 (IRT 3PL) — done** (`App\Services\Irt\Irt3PL` + `CatEngine` rewrite): 3PL `P(θ)=c+(1−c)/(1+exp(−a(θ−b)))`; Newton-Raphson MLE θ (≤10 iterations, clamp [−3,3], tolerance 0.001, restart-from-zero fallback when a warm start at the bound diverges); Fisher-information item selection preferring `|b−θ|≤0.5` then max information, high-a tie-break, answered excluded; scoring = θ→0-100 scaled (`scaledScore`, 50 at θ=0); passing = θ ≥ `passTheta` (`session.theta_required`, default 0); per-exam CAT cutoffs via migration 000008 (`min_questions`/`max_questions`, real bands like NCLEX 75–145 with confident early stop `SE<0.5 ∧ |θ−θpass|>1.5·SE` when the band is open, else fixed-length = question count); legacy CAT (irt_enabled false) and timed/practice unchanged. Full suite **48 tests / 6,835 assertions** green
- **Sprint 7 — Pearson VUE exam chrome + mastery dashboard — done** (`feature/sprint-7-exam-chrome`): Exam.jsx gains a question palette (answered/current/flagged cells + flagged-review modal), one-click strike-through, on-screen calculator (dependency-free shunting-yard, no eval), and natural-break dialogs that pause the timer on mocks/finals; finals suppress per-question feedback (answer recorded only). Dashboard.jsx rewrites as the region-grouped Worldwide mastery view: certified tracks grouped by region, dotted→solid progress rings (`ProgressRing`, dasharray `4 6`, θ + % in the hub), compact SVG θ trendlines (per-track `theta_history` from finished attempts), and weak-zone/domain-mastery heatmaps sourced from per-domain answer accuracy; shell (DashboardLayout) gains the θ ability badge + region nav fed by shared props. Backend: `PassimarkController::dashboard()` now ships `tracks` (region param filter, per-track sessions + theta_history + domains) via `thetaHistory()`/`domainAccuracy()`; `HandleInertiaRequests` shares `regions` + `ability.theta`. Design tokens landed in `tailwind.config.js` (`pm.deep #0F172A`, `pm.brand #1A9E2D`, `pm.accent #7CFC8F`) closing the design-tokens risk. Full suite **52 tests / 6,865 assertions** green; `npm run build` green.
- **Sprint 8 — certificates + PWA + deployment**: `Certificate.jsx`, credential IDs `PMK-…`, QR verification, manifest background fix (`#0F172A`), deployment guide — planned

## Sprint 7.5: Content coherence, learner onboarding & flow hardening
**Status:** **done** (interim sprint between 7 and 8; audit follow-up)
**Evidence:** [sprint-7-5-content-coherence.md](sprint-7-5-content-coherence.md), [sprint-7-5-content-coherence-report.md](sprint-7-5-content-coherence-report.md)
- **Curriculum service** (`App\Services\Curriculum`): shared enrollment (`enrollFirstSteps`/`enrollInTrack`) + track-scoped ladder (`unlockNext`, skips contentless sessions, finals never auto-unlock)
- **Real-content v4 metadata** (`CISSPBundleSeeder`): region `USA-IT-SECURITY`, `phase_type` lesson/mock/final ladder, `theta_required` gating, CAT exams `irt_enabled=1`, final `is_final`, 1.5× adaptive time — the v4 IRT engine + Sprint 7 regions/θ now run on real questions
- **Onboarding**: registration auto-enrolls a learner at each track's first lesson; the dashboard's hardcoded `session_id=1` guard replaced with the shared enrollment path
- **Profile data-driven**: `profile()` ships a real `summary` (phase/θ/completion/next milestone); Profile.jsx renders it with a curriculum progress bar (hardcoded "Phase 1"/"Session 2" removed)
- **Settings persistence**: migration 000009 `users.preferences`; `PATCH /settings`; Settings.jsx rebuilt on `useForm`
- **Admin approval hardening**: idempotent approve/reject (no 422 "not awaiting approval"), track-scoped unlock, queue refresh + friendly alert (`fetch` + XSRF + `router.reload`)
- Full suite **58 tests / 6,916 assertions** green; `npm run build` green; live learner smoke passes (login → dashboard → IRT CAT → result → profile/settings)

## Sprint 7.5b: Approval-gated progression & gated answer review
**Status:** **done** (follow-up hardening of Sprint 7.5; item-first advancement UX)
**Evidence:** [sprint-7-5b-approval-gating-review.md](sprint-7-5b-approval-gating-review.md), [sprint-7-5b-approval-gating-review-report.md](sprint-7-5b-approval-gating-review-report.md)
- **Per-track advancement mode** — migration 000010 `certification_tracks.advancement` (`auto` | `approval`): the real-content CISSP bundle is `approval` (a pass never auto-opens the next session), while the Worldwide/Uniform catalogs stay `auto` (v4 pass-gated ladder unchanged). `PassimarkController::finish()` only auto-unlocks on `advancement === 'auto'`; admin approval still unlocks next via `Curriculum::unlockNext`. CatEngineV4Test fixture explicitly `auto`.
- **Concluded-session CTA redefined** — completed/pending/approved rows now offer **Reattempt** + **Review answers** (plus **Request approval** on approval-gated completed sessions). No more "Continue"/"Done" on a concluded session; the dashboard ships each track's `advancement` so the UI stays honest about what unlocks what.
- **Gated answer review** (`App\Services\ReviewService` + `GET /passimark/session/{session}/review`): an item's correct answer + explanation is exposed only when the learner answered it correctly, every item was answered correctly, or the session is instructor-approved; locked items show the question + the learner's own selection only ("no expo on reattempt"). The Result screen shows a partial-lock banner with a **Request approval to unlock full review** path.
- **No-expo reattempt payloads**: the exam render and answer `next` payload strip `is_correct`/`correct_key` outside practice mode (`presentQuestion`), so a CAT/timed reattempt's client payload never leaks answer keys ahead of time.
- Full suite **64 tests / 7,060 assertions** green; `npm run build` + `php -l` clean; live dashboards ship `cissp` as `advancement=approval`.

## Sprint 7.5c: Role-branched admin dashboard
**Status:** **done** (follow-up of the Sprint 7.5b dashboard audit: admins/instructors previously landed on the exact learner path)
**Evidence:** [sprint-7-5c-admin-dashboard.md](sprint-7-5c-admin-dashboard.md)
- **Role-branched landing**: `PassimarkController::dashboard()` dispatches on `user->role`; admin/instructor get a new `Passimark/AdminDashboard` (operations) page, students keep the mastery path at `/`. Learners, admins, and instructors are each covered by `tests/Feature/AdminDashboardTest.php`.
- **Admin operations dashboard** (`PassimarkAdminController::dashboard()` sets the new home payload; rendered inside the shared `DashboardLayout`): pending-approval queue with inline approve/reject, report cards (learners, attempts, completed, average score, 7-day approvals, completions, sessions, questions, tracks, pending), recent decisions feed, and a "content needs attention" strip (contentless sessions, empty tracks, untagged questions). Light payload on the landing; the full Control Center tabs and Import keep their own screens.
- **Learner chrome gated**: the shell's region filter and θ ability badge render only for students; staff see "Passimark Operations" chrome and a sidebar with Overview / Control Center / Import Questions / Profile / Settings.
- Full suite **68 tests / 7,112 assertions** green; `npm run build` + `php -l` clean.

## Sprint 7.5d: Admin analytics — richer KPI tiles + drill-down reports
**Status:** **done** (follow-up of the 7.5c dashboard audit: tiles were bare counts with no context and no destination)
**Evidence:** [sprint-7-5d-admin-analytics-reports.md](sprint-7-5d-admin-analytics-reports.md)
- **`App\Services\AdminAnalytics`** is the single source of truth for every landing KPI and report — zero-data safe (no NaN), deterministic ordering, bounded score stats (latest 2,000 finished attempts) so distribution/median stay fast at catalogue scale.
- **Landing tiles** (`Passimark/AdminDashboard`) are now clickable KPI cards: value + context sub-line + weekly delta pill, each linking to its report (Learners, Attempts, Completed, Average score, Approvals 7d, Completions, Sessions, Questions, Tracks, Pending).
- **Six drill-down screens** under `/admin/reports/*`: Learners (roster + engagement), Attempts (ledger + score distribution + per-mode + 7-day cadence + status/mode filters), Questions (domain/bloom × accuracy, difficulty bands, quality flags, top-10 weakest items, never-used samples), Sessions (per-session engagement + contentless/never-used flags), Tracks (per-cert completion matrix), Approvals (decision ledger + review lag + 8-week trend + pending queue with aging). Shared `ReportHeader` + `StatStrip`; every table has an empty state and capped-list note.
- `PassimarkAttempt::user()` relation added; difficulty band keys made dot-free for Laravel dot-path safety.
- New `tests/Feature/AdminReportsTest.php` (9 tests); landing keeps flat `report.*` keys for back-compat. Full suite **77 tests / 7,365 assertions** green; `npm run build` + `php -l` clean.
- **Refinement:** "Content needs attention" alert sharpened — `AdminAnalytics::brokenSessionCount()` flags only *broken attemptable* sessions (have exams, zero questions). Intentional reference/remediation lessons (no exam, no questions, skipped by the curriculum ladder) are no longer flagged; banner copy updated; sessions-report flag + summary in sync; test proves both cases. Suite now **77 tests / 7,367 assertions**.

## Sprint 9: Portable packages & sharing (.psmk / .psme / .psmm)
**Status:** Phase A+B **done** (C-E planned)
**Evidence:** [sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md), [sprint-9-portable-packages-sharing-report.md](sprint-9-portable-packages-sharing-report.md), [passimark-file-format-rfc.md](passimark-file-format-rfc.md)
- Landed as `720c1d2` on `feature/sprint-9-psmk-packages` (branched from `55268cf`, Sprint 5)
- RFC **adopted** — one canonical `.psmk` ZIP package (manifest + content), `.psme`/`.psmm` as accepted aliases (extension is a hint; `manifest.content_type` authoritative)
- migration 000007: `external_id` UUIDs on sessions/exams/questions + `passimark_package_imports` audit — done
- `App\Support\PsmkZip` dependency-free ZIP codec + `PassimarkPackage\{Validator,Exporter,Importer}` + `PackagingSpec` — done; module/exam/course export + transactional **create-only** import that never unlocks progression and always audits
- CLI `passimark:package:export` / `passimark:package:import` — done; standard-ZIP output verified with system `tar`; full suite **36 tests / 6,733 assertions** green
- Remaining (Phase C-E): admin upload/preview UI, update-by-external_id & conflict modes, media assets/signatures, CSV→QTI→PDF source adapters via a review staging model, schema publication

## Known open risks (not yet scheduled)
- No frontend/browser automated tests — only PHPUnit backend coverage exists
- No design tokens / typography scale — **closed in Sprint 7**: `tailwind.config.js` now extends `pm.deep`/`pm.brand`/`pm.accent` + display font + ring shadow (typography scale remains optional polish)
- CI workflow exists ([.github/workflows/ci.yml](../.github/workflows/ci.yml)) but has not yet been exercised on a pushed branch/PR
- `.psmk` source conversion (PDF/VCE/CSV/QTI adapters), upload UI, and update modes remain Phase C-E of Sprint 9 ([sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md)); the V1 author package (export/import/audit) is shipped
- v4.0 brand assets still use legacy `passimark_*` filenames and `manifest.json` background is `#ffffff` instead of `#0F172A` (tracked in Sprint 8.3)
