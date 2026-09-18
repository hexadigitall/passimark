# Sprint 7.9 — Optional remediation sessions + catalog navigation polish

**Status: Complete** | Branch: `feature/sprint-7-5-content-coherence`

## Problem
The CISSP textbook bundle ships four sessions (41, 43, 44, 45) whose source pages are
narrative/tables/flashcards with **no multiple-choice items**. They rendered as ordinary
lessons with zero questions: no Start action, no content, and — because they sit inside the
ladder — they risked gating progression to the final mock at session 42 (a `final` session).

Separately, the Sprint 7.8 drill-down breadcrumb showed the region as plain text, so there was
no one-click way back to a region-filtered dashboard.

## Design — optional sessions, not new phase types
`PackagingSpec::PHASE_TYPES` is a closed set (`cert|lesson|phase|domain|mock|final`) validated
by the package importer, so adding `review`/`remediation` phase types would ripple through the
portable-package format. Instead, sessions gain a general **`is_optional`** flag
(migration `2026_01_01_000012`): startable when unlocked, but never required for ladder
progression. Sessions 45/46 stay question-less and simply render as **Reference** milestones.

```
required ladder:  ... 39 → 40 → [41 optional] → 42 final → [43 optional] → [44 optional]
pass 40  → opens 41 (optional) AND 42 (required)
pass 42  → opens 43 + 44 (optional); no required step follows
```

- `Curriculum::firstAssessableSession()` / `unlockNext()` filter `is_optional = false` when
  choosing the next required step.
- New `Curriculum::unlockOptionalBetween()` opens optional sessions sitting between the
  just-passed session and the next required step (or all remaining optional sessions after the
  final). It is invoked at the top of `unlockNext()`, so both admin approval and (where enabled)
  auto-advancement cover it.
- Contentless 45/46 are already skipped by the `question_count > 0` gate.

## Remediation pool generation
`CISSPBundleSeeder::fillRemediationPools()` draws 15 questions per session from the real
textbook bank, filtered to the domains each session reviews:

| Session | Domains | Pool |
|---|---|---|
| 41 | all 8 | 15 |
| 43 | all 8 | 15 |
| 44 | 1–4 | 15 |

- Deterministic without-replacement LCG sample (seed `crc32("cissp-remediation|{track}|{number}")`)
  so pools are reproducible across runs/environments and never touch PHP's global RNG state.
- Clones questions, tags them `domain`/`bloom`, and stages the three exam modes (CAT timed at
  1.5×) like every other pool session.
- **Idempotent and safe on a live DB**: sessions that already carry questions are skipped, so
  `fillRemediationPools($track)` can backfill an existing database without a destructive reseed.
- CISSP bank grows **980 → 1025 questions**; pool sessions **41 → 44**; exams **123 → 132**.

## Navigation polish
- Region crumb is now a link on both the cert screen and the track screen:
  `/?region={region}` (`Cert.jsx`, `Track.jsx`).
- The bundle payload ships `assessable` + `optional`; `Track.jsx` renders optional rows with an
  amber **Remediation** chip and question-less rows as **Reference** (no Start button).
- Ladder progress counts **required** sessions only, so optional practice never makes the ring
  appear incomplete.

## Files
- `database/migrations/2026_01_01_000012_add_is_optional_to_sessions.php`
- `database/seeders/CISSPBundleSeeder.php` (`REMEDIATION_POOLS`, `fillRemediationPools()`,
  `pickDeterministic()`, `createExams()`)
- `app/Models/PassimarkSession.php`, `app/Services/Curriculum.php`
- `app/Http/Controllers/PassimarkCatalogController.php`
- `resources/js/Pages/Passimark/{Track,Cert}.jsx`
- `tests/Feature/{CISSPBundleSeederTest,ContentCoherenceTest,CatalogNavigationTest}.php`
- `docs/cissp-prep-bundle.md`

## Verification
- Full suite green: **100 tests / 27,808 assertions** (`php vendor/bin/phpunit`).
- `CISSPBundleSeederTest` proves the 15-item pools, `is_optional` flagging, 44 pool sessions
  and idempotency (`1025` questions on re-seed).
- `ContentCoherenceTest` proves approve-40 opens optional 41 **and** required 42, and approve-42
  opens 43/44 after "Track completed."
- Dev DB backfilled in place: sessions 41/43/44 now 15 questions + 3 exams each, bank at 1025.
- `npm run build` clean (1998 modules, `app-Bj7KZE0v.js` 430.42 kB / 124.13 kB gzip).

## Out of scope
- Browser/E2E coverage (no frontend test harness in the repo).
- Authoring bespoke remediation items (pools are drawn from the existing verified bank).
