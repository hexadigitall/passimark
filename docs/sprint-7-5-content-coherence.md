# Sprint 7.5 — Content coherence, learner onboarding & flow hardening

**Status:** shipped
**Branch:** `feature/sprint-7-5-content-coherence`
**Suite:** 58 tests / 6,916 assertions green; `npm run build` green
**Verified live:** full learner smoke (login → dashboard → v4 IRT CAT exam → result → profile/settings) passes on `php artisan serve`.

## Why this sprint

The Spr 5–9 audit (Sprint 7.5 kickoff) showed the platform's *spine* worked but the **only seed with real questions (the CISSP textbook bundle) carried no v4 metadata**, so the flagship demo never exercised the v4 engine:

- every bundle exam had `irt_enabled = 0`, `phase_type` was null on all 46 sessions, the track had no `region`, no `theta_required`, no `time_minutes`
- the **Worldwide catalog** (which *does* set v4 metadata) has **zero questions** — you cannot take an exam on it
- consequence: Sprint 6 IRT + Sprint 7 regions/θ trendlines were effectively dead-on-arrival for real content.

Beyond the seed data, the audit surfaced three flow/screen gaps:

1. **New-registration dead end** — `AuthController::register()` created a user with no track/progress provisioning; `start()` `firstOrFail()` 404'd for fresh registrants.
2. **Hardcoded/inert screens** — Profile showed literal "Phase 1 / Next milestone: Session 2"; Settings toggles were stateless (no persistence, no endpoint).
3. **Approval 422** — re-approving an already-reviewed submission aborted with `422 "Progress is not awaiting approval."` and admin unlock logic was order-global across all tracks.

## Plan

1. **Curriculum service** (`App\Services\Curriculum`): shared enrollment + track-scoped ladder rule
   - `enrollFirstSteps(user)` / `enrollInTrack(user, track)` → open the first *assessable* step of every active track
   - `unlockNext(session, userId)` → next assessable step in the same track; finals (`final`) never auto-unlock; contentless (0-question) remediation sessions are **skipped**
2. **Seeder coherence** (`CISSPBundleSeeder`): real-content track now carries v4 metadata
   - region `USA-IT-SECURITY` (joins the catalog region taxonomy)
   - `phase_type` ladder over the 46 sessions (lesson/mock/final; final exam = session 42)
   - `theta_required` gating (−0.5 lessons, 0.0 mocks, 0.5 final), `time_minutes`/`time_limit`, `questions_target`
   - every CAT exam `irt_enabled = true` (+ `is_final` on the final), non-CAT modes stay non-IRT
3. **Onboarding provisioning**: register() auto-enrolls; dashboard's hardcoded `session_id=1` guard replaced with the shared enrollment path
4. **Profile data-driven**: controller builds a real `summary` (current phase/session/track, θ, completion, next milestone); Profile.jsx renders it with a curriculum progress bar
5. **Settings persistence**: migration 000009 `users.preferences` (json); `PATCH /settings`; Settings.jsx uses Inertia `useForm` against real persisted prefereces
6. **Admin approval hardening**: idempotent approve/reject (no 422 on already-reviewed), track-scoped unlock via `Curriculum::unlockNext`, and the Admin queue refreshed after each decision with a friendly alert

## Out of scope

- Sprint 8 (certificates + PWA + deployment)
- Sprint 9 Phases C–E (portable package upload/preview UI, update-by-external_id, media/signatures, CSV→QTI→PDF adapters)
- Admin region UI (track form region field) — deferred; tracks are seeded with regions for now
- Admin learner-enrollment UI — programmatic (register + dashboard) only for now