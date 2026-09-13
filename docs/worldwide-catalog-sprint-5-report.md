# Sprint 5 — Worldwide Catalog Schema + Seeder — Report

**Status:** done
**Branch:** `feature/sprint-5-worldwide-catalog`
**Verification:** `vendor/bin/phpunit` → **OK (28 tests, 6,660 assertions)**; live seeds verified on scratch SQLite.
**Parent plan:** [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md), [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md)

---

## 1. What shipped

The single-curriculum prototype can now express the full v4 5-stage Worldwide CAT ladder, and both canonical catalog load paths are wired and verified.

### Schema (migration `2026_01_01_000006_add_v4_catalog_columns`)
| Table | Column | Purpose |
|---|---|---|
| `passimark_sessions` | `phase_type` (enum `cert\|lesson\|phase\|domain\|mock\|final`) | 5-stage ladder stage (null = legacy prototype) |
| `passimark_sessions` | `cert_slug` | cert scoping per session |
| `passimark_sessions` | `theta_required` (float, nullable) | pass-theta gate slot (Sprint 6 populates) |
| `passimark_sessions` | `questions_target`, `time_minutes` | v4 canonical names; legacy `question_count`/`time_limit` kept read-compat |
| `passimark_exams` | `time_minutes`, `is_final`, `irt_enabled` | per-mode time, final flag, IRT switch (CAT) |
| `passimark_questions` | `correct_key` | explicit answer key (Sprint 6 uses it) |
| `passimark_certification_tracks` | `region` | dashboard grouping per region |

### Models
- `PassimarkSession`: stage scopes `lessons()/phases()/domains()/mocks()/finals()/cert()/byCert($slug)`.
- `PassimarkQuestion`: IRT canonical aliases `a_discrimination`/`b_difficulty`/`c_guessing` backed by the existing columns.
- `PassimarkExam`/`PassimarkCertificationTrack`: new fillable + `region`.

### Seeders
- **`WorldwidePassimarkCatalogSeeder`** — the 17 flagship certs (content faithful to the approved v4.0 FINAL source). Per cert: cert container → lessons (25Q @UP) → phase CATs → domain assessments (75Q @UP pressure) → mocks (70/100/120%) → final real-spec. Seed result: **17 tracks, 346 sessions, 1,038 exams, 7 regions (~2.5 s)**. Filters `SEED_REGIONS`/`SEED_CERTS`.
- **`Uniform205CatalogSeeder`** — 205-cert generator JSON expanded to the uniform 14-session ladder: **205 tracks, 2,870 sessions, 8,610 exams, 9 regions (~7 s)**. Filters `SEED_REGIONS`/`SEED_CERTS`/`SEED_LIMIT`.
- Every session ships 3 exam modes: `cat` (adaptive, 1.5× session time, IRT), `timed` (session time), `practice` (0). Finals flag `is_final`.
- `DatabaseSeeder` selects `worldwide` / `uniform` via `SEED_CATALOG`; otherwise the CISSP bundle; prototype otherwise. CI unaffected (seeds `PassimarkSeeder` explicitly).

### Gating
- `PassimarkController::autoUnlockNext()` — v4 rule: passing a lesson/phase/domain/mock opens the next session in the track (theta/pass gate); `final` and legacy prototype sessions keep the instructor-approval flow.
- `finish()` uses each session's `pass_score` (not a hard-coded 70).
- Demo learner is enrolled (`progress.status = open`) into every cert's first open lesson only; everything else locked (404 on unopened sessions).

## 2. Seed-count verification (scratch SQLite)

| Load path | Tracks | Sessions | Exams | Regions | Time |
|---|---|---|---|---|---|
| Worldwide (17 flagship) | 17 | 346 | 1,038 | 7 | ~2.5 s |
| Uniform 205 | 205 | 2,870 | 8,610 | 9 | ~7 s |
| Spec targets (v4 §4b) | 205 | 2,870 | 8,610 | 9 | — |

Drift vs spec §4b: **zero** on the uniform path.

## 3. Test coverage

`tests/Feature/WorldwideCatalogSeederTest.php` (4 tests):
- custom catalog builds independent cert ladders with per-region tracks (SEC+ 22 / CISSP 23 sessions in test fixture; stage mix; only first lesson + container open; demo enrolled in first lesson only)
- every session ships 3 well-formed exam modes with correct mode/timer/IRT/final flags; mocks scale 70/100/120%
- locked sessions are blocked (404) and passing a lesson auto-unlocks the next (25-question CAT drill end-to-end)
- uniform 205 expands the static 14-session ladder exactly (stage sequence, open/final flags, 1.5× adaptive time)

Full suite: **28 tests / 6,660 assertions OK**.

## 4. Notes / follow-ups for Sprint 6
- `theta_required` is populated but not yet enforced by the engine (Sprint 6 IRT 3PL makes it real).
- `correct_key` is written but the engine still derives correctness from `options[].is_correct` (Sprint 6 switches reads to `correct_key`).
- `is_final` exams are gated but certificate issuance is Sprint 8 scope.
- PWA `manifest.json` `background_color` still needs `#0F172A` (Sprint 8.3).