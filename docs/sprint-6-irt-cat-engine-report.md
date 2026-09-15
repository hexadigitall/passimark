# Sprint 6 — CAT Engine v4 (IRT 3PL) — Report

**Status:** done
**Branch:** `feature/sprint-6-irt-cat-engine` (branched from `720c1d2`, Sprint 9)
**Verification:** `vendor/bin/phpunit` → **OK (48 tests, 6,835 assertions)**; `php -l` clean on all touched files.
**Parent plan:** [sprint-6-irt-cat-engine.md](sprint-6-irt-cat-engine.md), [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md) §6

---

## 1. What shipped

The CAT engine is now a true IRT 3PL system. `App\Services\Irt\Irt3PL` holds the pure mathematics; `CatEngine` selects the v4 path only when the attempt is `mode=cat` **and** `exam.irt_enabled` — legacy CAT, timed, and practice flows are byte-for-byte behaviour preserved.

### Migration `2026_01_01_000008_add_irt_cutoffs_to_exams`
- `passimark_exams.min_questions` / `max_questions` (nullable unsigned ints) — real per-exam CAT cutoffs. Seeded exams default to fixed-length (`min == max == question_count`), so every existing ladder is unchanged; future real-spec finals (NCLEX 75–145, etc.) supply an open band.

### `App\Services\Irt\Irt3PL` (43 unit assertions)
- 3PL `P(θ)`, Fisher information, score (first derivative), total information, `standardError()`.
- `mle()` — Newton-Raphson (Fisher scoring) with ≤ 10 iterations, clamp [−3, 3], break on `|Δ| < 0.001`. **Restart-from-zero fallback**: a warm start pinned at the θ bound can diverge (Fisher info collapses at the clamp → Newton oscillates ±3); if the primary pass doesn't converge, a restart from θ=0 that converges — or lands closer to the root — wins.
- `scaledScore(θ)` maps [−3, 3] → [0, 100] (50 at θ=0).
- `params()` normalises item parameters from Eloquent models, arrays, or `ArrayAccess` across the v4 aliases (`a_discrimination`/`b_difficulty`/`c_guessing`) and the legacy columns (`discrimination`/`difficulty`/`guessing`).

### `CatEngine` rewrite
- `usesIrt($attempt)` = `mode==='cat' && exam.irt_enabled`.
- `nextQuestion()`: v4 branch fetches the unanswered pool and scores every item by `(preferNear?1:0)·1e12 + info·1e4 + a`, taking the max — i.e. prefer `|b−θ|≤0.5`, then max Fisher information, then high discrimination.
- `shouldTerminate()`: v4 = `count ≥ max_questions`, or (`count ≥ min_questions` ∧ band open ∧ `SE < 0.5` ∧ `|θ − θpass| > 1.5·SE`); legacy CAT keeps the 150 / (75 ∧ |θ|>2.5) rule; timed/practice keep `count ≥ question_count`.
- `calculateScore()`: θ→scaled for v4; raw percentage otherwise.

### Controller wiring (`PassimarkController`)
- `answer()`: the response row is persisted first, then the v4 path **fully recomputes** θ via `Irt3PL::mle` over every answer so far (warm start = current attempt θ); legacy CAT still uses `updateTheta()`.
- `finish()`: v4 paths the theta through `Irt3PL::mle`, passes iff `θ ≥ session.theta_required` (default 0), stores scaled `score`, final `theta`, `is_passed`, and writes `progress.ability_theta`. Legacy/timed/practice keep `score ≥ pass_score` percentage gating. `autoUnlockNext()` ladder and `attemptResult()` unchanged.

## 2. Behaviour notes
- **θ saturates at the clamp for all-correct/all-wrong runs** (spec mandates [−3, 3]); SE is correspondingly large at the boundary, so the early-stop rule won't quit on an ambiguous extreme profile — it fires for strong-but-moderate theta inside an open band.
- The Worldwide lesson drill (25-Q CAT) still runs the full pool (fixed-length min==max), so the Sprint 5 ladder end-to-end test is unchanged and green.

## 3. Test coverage (Sprint 6 delta: 12 tests / 102 assertions)
`tests/Unit/Irt3PLTest.php` (7 tests):
- 3PL P at known points; guessing floor; information peaks at the item difficulty and vanishes far away
- MLE recovers a synthetic generating θ (seeded RNG) within tolerance; converges; bounded SE
- all-correct → +3 clamp, all-wrong → −3 clamp; score monotone in θ; scaled-score mapping; clamp bounds

`tests/Feature/CatEngineV4Test.php` (5 tests):
- v4 selection prefers the near-θ item, then maximises Fisher information among the rest, excludes answered
- fixed-length v4 CAT runs the full pool (5/5), passes by θ ≥ `theta_required`, scaled score, auto-unlocks the next lesson (controller end-to-end)
- low-θ v4 CAT fails (status stays open, scaled < 50)
- open-band v4 CAT early-stops at `min_questions` when theta is confident (15 answered of 30)
- legacy CAT keeps nearest-difficulty + percentage scoring (regression guard)

Full suite: **48 tests / 6,835 assertions OK** (was 36 / 6,733).

## 4. Notes / follow-ups for Sprint 7
- Certificate display now has the pieces (`theta`, scaled score, ability) but `Certificate.jsx` + credential issuance is Sprint 8.
- Real-spec adaptive bands: finals can set explicit `min_questions/max_questions` (e.g. NCLEX 75–145) — add to catalog JSON / admin UI when the source spec is loaded.
- θ is recomputed full-MLE per answer — fine for exam-sized pools; if pools reach tens of thousands, pre-trim to top-N by information.