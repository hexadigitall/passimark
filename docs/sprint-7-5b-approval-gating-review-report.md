# Sprint 7.5b — Approval-gated progression & gated answer review: report

**Branch:** `feature/sprint-7-5-content-coherence`
**Baseline:** `4a23a6b` (Sprint 7.5)
**Result:** **64 tests / 7,060 assertions green** (was 58 / 6,916) · `npm run build` green (app `CiyfyedB.js` 369.12 kB, css 27.28 kB) · `php -l` clean · live server confirms the bundle track ships `advancement=approval` with session 1 `open`.

## What changed

### 1. Advancement mode (migration 000010)
- `passimark_certification_tracks.advancement` (`auto` | `approval`, default `approval`), added to the model fillable.
- Seeders: `CISSPBundleSeeder` → `approval`; `WorldwidePassimarkCatalogSeeder` + `Uniform205CatalogSeeder` → `auto`.
- `PassimarkController::finish()` now gates the auto-unlock: a passing attempt only calls `autoUnlockNext()` when the session's track is `advancement === 'auto'`. On the bundle a pass lands `completed` and the next session stays locked. Admin `approve()` still unlocks the next session (`Curriculum::unlockNext`).

### 2. Concluded-session actions (Dashboard.jsx)
- Removed the "Request approval"/"Done"/"Continue" single-CTA on finished rows.
- Completed / pending / approved sessions now render **Reattempt**, **Review answers**, and (approval-gated + not yet pending) **Request approval**.
- The dashboard payload exposes each track's `advancement` so the UI hinges on the real backend mode.

### 3. Gated answer review (`App\Services\ReviewService`)
- `payload(attempt, userId)` computes `review_unlocked = approved || all correct`; each answer item is `exposed = answered-correctly || unlocked`.
- Locked items ship `question` (content + neutral options) with `correct_key = null` and `explanation = null`.
- `PassimarkController::result()` delegates to it; new `GET /passimark/session/{session}/review` renders the latest finished attempt.
- `Result.jsx`: per-item correct/incorrect with explanation block when exposed, lock note when hidden; partial-lock banner; "Request approval to unlock full review" when the session is `completed`; Reattempt + Return actions.

### 4. No-expo reattempt payloads (`presentQuestion`)
- The exam render's `question` and the live `answer` response's `next` strip `is_correct`/`correct_key` for every non-practice mode. Practice mode keeps per-question feedback.

## Verification

- **New tests:** `ContentCoherenceTest::test_passing_a_bundle_session_is_approval_gated_until_instructor_approves` (15-question real bundle CAT → `completed`, no auto-unlock of session 2 → request → pending → approve → session 2 `open`). New `tests/Feature/ReviewGateTest.php` (5 cases): mixed attempt locks wrong items / explains correct ones; all-correct attempt unlocks full review with no approval; instructor approval unlocks the locked review; `/session/{id}/review` serves the latest attempt and 404s unstarted sessions; exam + answer payloads hide answer keys outside practice mode.
- **Adjusted:** `CatEngineV4Test` fixture track is explicitly `advancement=auto` (it asserts the v4 pass→unlock ladder); `WorldwideCatalogSeederTest` auto-unlock assertion untouched and still green.
- **Suite:** 64 tests / 7,060 assertions **OK**. Build + lint green.
- **Live:** dev DB reseeded; `http://127.0.0.1:8010` serves the bundle track as `advancement=approval`, session 1 `open`.
- **UI tuning follow-up:** the region card grid was a fixed `lg:grid-cols-2 xl:grid-cols-3`, which squeezed the single CISSP bundle card to one third of the row and leaked session-row content (long titles + Reattempt/Review/Request-approval buttons). Region sections now use a wrapping flex layout (`flex flex-wrap gap-6`, cards `flex-1 min-w-[320px]`) so a lone track card expands to full content width and multiple certs share rows only as wide as their content allows; session rows also wrap (`flex-wrap`) instead of overflowing. `npm run build` green (app `C44y7PWU.js`).

## Open notes

- Bundle item explanation coverage is variable (legacy extraction); gating is implemented and renders correct-answer-only when `explanation` is null.
- The `start()` entry policy still permits `completed`/`approved` sessions to be retaken, which is the intended "reattempt without expo" path.