# Sprint 7.5 — Content coherence & flow hardening: report

**Branch:** `feature/sprint-7-5-content-coherence`
**Baseline:** `68c4d10` (Sprint 7)
**Result:** **58 tests / 6,916 assertions green** (was 52 / 6,865) · `npm run build` green (app `BhFHh5Rw.js` 364.68 kB, css 26.66 kB) · full learner smoke passes live.

## What changed

### 1. `App\Services\Curriculum` (new)
Single source of truth for enrollment + ladder:
- `enrollFirstSteps(User)` / `enrollInTrack(User, Track)` — open the first assessable (question-bearing) step of each active track.
- `unlockNext(PassimarkSession, userId)` — next assessable session **in the same track** by `order`; returns null for finals/cert, and skips contentless remediation sessions so the ladder flows to the next real assessment.

### 2. Real-content v4 metadata (`CISSPBundleSeeder`)
Reseed on the dev DB (CISSP textbook bundle, 46 sessions / 980 questions / 123 exams):
- track `region = USA-IT-SECURITY` → region nav now shows a real region; dashboard groups the CISSP track under it.
- session `phase_type` staged: `lesson` (42) / `mock` (3: nos. 15, 30, 40) / `final` (1: no. 42) with `theta_required` −0.5 / 0.0 / 0.5 and `time_minutes`.
- **all 41 CAT exams `irt_enabled = 1`** (timed/practice stay non-IRT); the final's exams are `is_final = true`; CAT time = 1.5× session time (135 / 270 min).
- verified: `/` still 200, `regions=1`, CAT start → IRT path (`theta` estimated per response), scaled θ score on finish.

### 3. Onboarding provisioning
- `AuthController::register()` now calls `Curriculum::enrollFirstSteps($user)` before redirecting to the dashboard.
- `PassimarkController::dashboard()`'s empty-progress guard uses the same enrollment path instead of the hardcoded `session_id=1` insert.
- A newly registered user lands with session 1 `open` and can start it immediately (no 404 dead end).

### 4. Profile data-driven
- `PassimarkController::profile()` ships a real `summary` (current phase/session/track/title, θ, sessions completed/enrolled/total, average score, next milestone, completed-track count).
- `Profile.jsx` renders values + a curriculum completion bar; empty-state copy when not enrolled. Hardcoded "Phase 1"/"Session 2" removed.

### 5. Settings persistence
- Migration `2026_01_01_000009_add_preferences_to_users_table` (`users.preferences` json).
- `PATCH /settings` (`PassimarkController::updateSettings`) validates and persists `notifications.*`, `theme`, `language`; defaults merged for legacy rows.
- `Settings.jsx` rebuilt on Inertia `useForm`, seeded from the persisted payload, with save feedback.

### 6. Admin approval hardening
- `approve`/`reject` are now **idempotent**: an already-approved submission returns `200 {status:'skipped'}` instead of `422`.
- The unlock step is track-scoped and content-aware via `Curriculum::unlockNext` (was a brand-agnostic `order>` query).
- `Admin.jsx` review actions moved to `fetch` + `X-XSRF-TOKEN` (decoded from cookie) + `router.reload({only:['pending','events','report']})`, with a friendly alert instead of the "Unprocessable Content" modal.

## Verification

- **New tests** (`tests/Feature/ContentCoherenceTest.php`, 6 cases): v4 metadata on the bundle (irt/phase/region/time/final), registration enrollment + immediate start, approval idempotency + contentless-session skip + final "Track completed", profile summary payload, settings persist/re-render, IRT θ on the real-content ladder. Existing `CISSPBundleSeederTest` seed asserts preserved (46/980/123; session 1 open).
- **Suite:** 58 tests / 6,916 assertions **OK**.
- **Live smoke** (`php -S` via `artisan serve`): login → dashboard (`tracks=1 regions=1`) → start CAT → exam (IRT, θ per answer) → finish (scaled score) → result → profile/settings, all 200/302 as expected.
- `php -l` clean on all touched PHP files.

## Not changed (deferred, tracked)

- Admin track form still has **no region field** (v4 region manageability deferred).
- Admin has no **learner-enrollment UI** (programmatic enrollment only).
- Sprint 8 certificates/PWA and Sprint 9 Phases C–E unchanged.