# Sprint 6 — CAT Engine v4 (IRT 3PL) — Plan & Status

**Spec source:** [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md) §6, seeded by [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md).
**Report:** [sprint-6-irt-cat-engine-report.md](sprint-6-irt-cat-engine-report.md)

## Goal
Replace nearest-difficulty CAT selection with a true IRT 3PL engine: Newton-Raphson MLE theta, Fisher-information item selection, per-exam cutoffs, theta-gated passing with scaled scores — without regressing legacy CAT, timed, or practice modes.

## Tasks
- **6.1 Migration** (`2026_01_01_000008`) — `passimark_exams.min_questions` / `max_questions` (nullable) for real per-exam CAT cutoffs (e.g. NCLEX 75–145).
- **6.2 `App\Services\Irt\Irt3PL`** — pure-math IRT service:
  - 3PL `P(θ) = c + (1 − c) / (1 + exp(−a(θ − b)))`
  - Fisher information `I(θ) = a²(P − c)²(1 − P) / (P(1 − c)²)`
  - Newton-Raphson (Fisher scoring) MLE, ≤ 10 iterations, clamp [−3, 3], tolerance 0.001, restart-from-zero fallback on divergent warm starts
  - standard error `1/√I`, θ→0–100 scaled score (50 at θ = 0), 3PL parameter normalisation across v4 aliases and legacy columns
- **6.3 `CatEngine` rewrite** — `usesIrt()` (mode=cat ∧ exam.irt_enabled) selects the v4 path:
  - item selection: prefer `|b − θ| ≤ 0.5`, then max Fisher information, high-a tie-break, answered excluded
  - termination: per-exam `[min_questions, max_questions]` (fixed-length when min == max = question count); confident early stop inside an open band when `SE < 0.5 ∧ |θ − θpass| > 1.5·SE`
  - scoring: θ→scaled score for v4; percentage unchanged for legacy/timed/practice
- **6.4 Controller wiring** (`PassimarkController`) — `answer()` computes the MLE theta from every response (row created, then full recompute); `finish()` passes by `θ ≥ session.theta_required` (default 0), persists final θ + scaled score + `ability_theta`; legacy path untouched.
- **6.5 Verify** — unit tests for P/information/MLE/bounds/scaling + feature tests for selection, exclusion, fixed-length and adaptive termination, pass/fail by theta, and legacy back-compat.

## Exit criteria
- θ converges on synthetic data with known generating theta (unit-tested)
- CAT uses Fisher selection; legacy CAT/timed/practice behaviour unchanged
- Full PHPUnit suite + `php -l` green

## Status
**done** — see [sprint-6-irt-cat-engine-report.md](sprint-6-irt-cat-engine-report.md).