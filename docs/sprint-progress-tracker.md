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
**Status:** Sprint 5 **done** (6-8 planned)
**Evidence:** [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md), [worldwide-catalog-sprint-5-report.md](worldwide-catalog-sprint-5-report.md), [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md)
- **Sprint 5 — worldwide catalog schema + seeder — done**: migration 000006 (sessions `phase_type`/`cert_slug`/`theta_required`/`questions_target`/`time_minutes`, exams `time_minutes`/`is_final`/`irt_enabled`, questions `correct_key`, tracks `region`); `WorldwidePassimarkCatalogSeeder` (17 flagship certs, 346 sessions / 1,038 exams / 7 regions) + `Uniform205CatalogSeeder` (205 certs, uniform 14-session ladder: 2,870 sessions / 8,610 exams / 9 regions — spec §4b drift zero); `SEED_CATALOG`/`SEED_REGIONS`/`SEED_CERTS`/`SEED_LIMIT` switches; v4 pass-gated auto-unlock (`PassimarkController::autoUnlockNext`), phase-1 first lesson open per cert, finals approval-gated; full suite **28 tests / 6,660 assertions** green
- **Sprint 6 — CAT engine v4 (IRT 3PL)**: Newton-Raphson MLE theta + Fisher-information item selection, `passTheta`/`passScoreScaled` — planned
- **Sprint 7 — Pearson VUE chrome + mastery dashboard**: question palette, flag/review, strike-through, calculator, break dialogs; dotted→solid progress rings, theta trendline, design tokens — planned
- **Sprint 8 — certificates + PWA + deployment**: `Certificate.jsx`, credential IDs `PMK-…`, QR verification, manifest background fix (`#0F172A`), deployment guide — planned

## Sprint 9: Portable packages & sharing (.psmk / .psme / .psmm)
**Status:** Phase A+B **done** (C-E planned)
**Evidence:** [sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md), [sprint-9-portable-packages-sharing-report.md](sprint-9-portable-packages-sharing-report.md), [passimark-file-format-rfc.md](passimark-file-format-rfc.md)
- RFC **adopted** — one canonical `.psmk` ZIP package (manifest + content), `.psme`/`.psmm` as accepted aliases (extension is a hint; `manifest.content_type` authoritative)
- migration 000007: `external_id` UUIDs on sessions/exams/questions + `passimark_package_imports` audit — done
- `App\Support\PsmkZip` dependency-free ZIP codec + `PassimarkPackage\{Validator,Exporter,Importer}` + `PackagingSpec` — done; module/exam/course export + transactional **create-only** import that never unlocks progression and always audits
- CLI `passimark:package:export` / `passimark:package:import` — done; standard-ZIP output verified with system `tar`; full suite **36 tests / 6,733 assertions** green
- Remaining (Phase C-E): admin upload/preview UI, update-by-external_id & conflict modes, media assets/signatures, CSV→QTI→PDF source adapters via a review staging model, schema publication

## Known open risks (not yet scheduled)
- No frontend/browser automated tests — only PHPUnit backend coverage exists
- No design tokens / typography scale — Tailwind defaults are used everywhere ([tailwind.config.js](../tailwind.config.js)); scoped in Sprint 7.4
- CI workflow exists ([.github/workflows/ci.yml](../.github/workflows/ci.yml)) but has not yet been exercised on a pushed branch/PR
- `.psmk` source conversion (PDF/VCE/CSV/QTI adapters), upload UI, and update modes remain Phase C-E of Sprint 9 ([sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md)); the V1 author package (export/import/audit) is shipped
- v4.0 brand assets still use legacy `passimark_*` filenames and `manifest.json` background is `#ffffff` instead of `#0F172A` (tracked in Sprint 8.3)
- Sprint 9 feature work is sitting uncommitted on `feature/sprint-9-psmk-packages` (Sprint 5 landed as `55268cf` on `feature/sprint-5-worldwide-catalog`; next commit should land Sprint 9 before Sprint 6 starts)
