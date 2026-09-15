# Sprint 7.5b — Approval-gated progression & gated answer review

**Branch:** `feature/sprint-7-5-content-coherence` (follow-up commit on 7.5)
**Baseline:** `4a23a6b` (Sprint 7.5)
**Status:** **done** — merged into the tracker as Sprint 7.5b

## Goal

Fix the two flow defects a learner hit after Sprint 7.5's real-content launch:
1. On the approval-gated CISSP bundle, the *next* session turned green ("Start Session") immediately on a pass — the v4 pass-gated auto-unlock had not been retired for the real content.
2. A concluded session shipped a meaningless "Continue"/"Request approval" CTA, and the answer review hid nothing meaningful — there was no reason to review, and reattempting exposed nothing about the correct answers.

## Decisions (user-confirmed)

- **Advancement is a per-track mode, not a global switch.** `certification_tracks.advancement` is `approval` for the real-content CISSP bundle and `auto` for the Worldwide/Uniform catalogs. This keeps the v4 catalog's pass-gated ladder (Sprint 5's tested behavior) intact while making the bundle instructor-gated.
- **Approval-gate all sessions on the bundle.** Finish a session → status `completed` → the next session stays locked until the learner requests approval and an instructor approves. Admin approval remains the only unlocker (`Curriculum::unlockNext`, idempotent, track-scoped).
- **Concluded-session CTA = Reattempt + Review answers** (plus Request approval when approval-gated and not yet pending/approved). No "Continue", no "Done".
- **Answer review is gated.** An item's correct answer + explanation is exposed only when:
  1. the learner answered that item correctly, or
  2. every item in the attempt was answered correctly, or
  3. an instructor approved the session.
  Locked items show the question plus the learner's own selection — a "no-expo" reattempt.
- **Reattempt never leaks answers.** Exam render and the live `answer` payload strip `is_correct`/`correct_key` except in practice mode, which is the explicit self-graded feedback mode.

## Work slices

- Migration `2026_01_01_000010_add_advancement_to_certification_tracks_table` (`advancement` string, default `approval`); `PassimarkCertificationTrack` fillable.
- Seeders: `CISSPBundleSeeder` → `advancement = approval`; `WorldwidePassimarkCatalogSeeder` + `Uniform205CatalogSeeder` → `advancement = auto`.
- `PassimarkController::finish()` auto-unlocks only when the session's track is `advancement === 'auto'` (approve never loses its unlock).
- Dashboard payload ships each track's `advancement`; the dashboard shows the three-button CTAs.
- `App\Services\ReviewService::payload()` implements the exposure rules; `PassimarkController::result()` delegates to it.
- New route `GET /passimark/session/{session}/review` renders the Result page for the latest finished attempt.
- `presentQuestion()` strips answer keys from exam + answer `next` payloads outside practice mode.
- `Result.jsx` renders per-item locked/unlocked review, a partial-lock banner, and "Request approval to unlock full review".
- Tests: `ContentCoherenceTest` gained the bundle no-auto-unlock→approval-unlocks case; new `ReviewGateTest` (5 cases).

## Open notes

- Explanation text density on real bundle items may be thin (many questions predate explanation authoring). Exposure gating works on `question.explanation`; blank explanations render as correct-answer-only.
- Practice mode is intentionally self-graded (immediate explanation feedback); the no-expo rules govern CAT/timed reattempts and the review screen.