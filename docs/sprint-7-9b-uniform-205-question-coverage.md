# Sprint 7.9b — Uniform 205 question coverage

**Status: done**
**Branch:** `feature/sprint-7-5-content-coherence`
**Source switch:** `SEED_CATALOG=uniform`

## Problem
Sprint 7.6 shipped the `uniform` catalog — **205 certification tracks / 2,870 sessions /
8,610 exams** — but every session pool was empty. Only the Worldwide-17 certs (their own
original question bank) and the CISSP textbook bundle carried any questions. Reviewing the
Uniform-205 content was therefore a spreadsheet exercise: a learner could start a session,
fall straight out of the decision ladder, and record a half-credit "exam" with zero items
(tracker "Sprint 7.6 DONE" note, "205 certs still zero questions").

## Design
A **deterministic uniform question bank** that fills every Uniform-205 session pool to its
exact `questions_target`, mirroring the Worldwide original-bank pattern (Sprint 7.7):

- **`App\Services\PracticeQuestionBank\UniformQuestionBankGenerator`** — deterministic content
  builder shared by the Uniform-205 ladder. For a given (cert, session, index) it derives a
  seed via `crc32('uniform|'.code.'|'.order.'|'.index)`, then produces a 4-option, 1-correct
  item with IRT bounds (difficulty −1.5 … 2, discrimination 0.8 … 1.8, guessing 0.25), domain +
  Bloom tags from the session's own context, an explanation, and a catalog reference. The
  `external_id` is `ub-{code}-s{order}-q{index}`, so output is byte-for-byte reproducible
  across runs and environments.
- **`Uniform205QuestionBankSeeder`** — idempotent: a session pool is skipped whenever that
  session already holds any question (`PassimarkQuestion::where('session_id', …)->exists()`),
  so re-runs never double-insert. Fills each session to its `questions_target`, tags every
  question (`passimark_question_tag` domain + Bloom pivots), and reports
  `{certs, sessions, questions}`. `SEED_LIMIT`/`SEED_CERTS` still narrow the run for CI/dev.
- **Migration `2026_01_01_000015_index_passimark_questions_session`** — index on
  `passimark_questions.session_id`. The seeder's idempotency `exists()` and per-session bulk
  inserts run against this index, keeping them O(log n) at catalog scale (~167k questions)
  instead of a table scan. *(This is the critical perf fix.)*
- **`DatabaseSeeder`** uniform path: `Uniform205CatalogSeeder` → `Uniform205QuestionBankSeeder`.

## Verification
- `SEED_CATALOG=uniform` fresh seed on scratch SQLite: **205 certs / 2,870 sessions /
  8,610 exams / 167,133 questions** in ~73 s (the pre-index run was 9+ min; the session index
  collapsed the scans).
- New `Uniform205QuestionBankSeederTest`: fills every session to its exact `questions_target`,
  deterministic + idempotent re-runs, every question has 4 options with a single correct key
  matching `correct_key`, tags `domain`/`bloom`, IRT bounds, and region grouping intact.
- Full suite **110 tests / 44,586 assertions** green (`00:44.8s`); `npm run build` clean;
  `php -l` clean.
- Tracking: dev DB reseeded to the complete Uniform-205 catalog with full question coverage
  (`count` per session == `questions_target`).
