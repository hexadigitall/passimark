# Sprint 5-8: v4.0 Worldwide Catalog — Implementation Plan

**Status:** planned (approved v4.0 spec, JAN 2026)
**Depends on:** Sprint 4.1 certification tracks + 4.2 tag taxonomy (tracks done; tags are a prerequisite for tag-driven catalog scale — see [sprint-4-taxonomy-plan.md](sprint-4-taxonomy-plan.md))
**Source spec:** [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md)

These four sprints convert the single-curriculum CISSP prototype into the global 200-cert, 5-stage CAT platform defined by the approved v4.0 build. Each sprint is a vertical slice with its own exit criteria.

---

## Sprint 5: Worldwide catalog schema + seeder

### Goal
Make the data model express the 5-stage CAT ladder and populate it with the 17-cert custom seeder plus the 205-cert uniform catalog JSON (~2,870 sessions / 8,610 exams).

### Tasks
- **5.1 Schema migrations**
  - `passimark_sessions`: add `phase_type` enum (`cert|lesson|phase|domain|mock|final`), `cert_slug` (or derive from track), `theta_required` (nullable), rename/alias `time_limit`→`time_minutes`, `question_count`→`questions_target` (backfill-friendly migration, keep old columns until reads migrate)
  - `passimark_exams`: add `time_minutes`, `is_final` (bool), `irt_enabled` (bool, CAT only)
  - `passimark_questions`: add `correct_key`; alias/augment IRT fields (`a_discrimination`,`b_difficulty`,`c_guessing`) while old names remain readable
- **5.2 Models** — update `PassimarkSession`, `PassimarkExam`, `PassimarkQuestion` casts/fillable; add scope helpers (`lesson()`, `phase()`, `domain()`, `mock()`, `final()`; `byCert($slug)`)
- **5.3 Seeder** — two seed sources are available: the supplied **17-cert seeder** (custom per-cert phase/lesson content, `WorldwidePassimarkCatalogSeeder`) and the **205-cert uniform catalog** extracted from the SQL-Generator tool ([worldwide-205-cert-catalog.json](worldwide-205-cert-catalog.json), 14 sessions × 3 modes per cert = 2,870 sessions / 8,610 exams). Wire both in, gated behind env/SELECT-able flags so the demo CISSP seeder remains runnable; decide the canonical mass-load path (uniform vs custom) for Sprint 5 exit
- **5.4 Gating** — phase-1-first-lesson open logic; unlock rule = prev stage approved/open by theta/pass (see Sprint 4.3 rules)
- **5.5 Verify** — feature test asserts: cert container + first lesson open; phase gates; ≥3 exams per session (cat/timed/practice); session count for seeded certs; second cert fully independent (reuse Sprint 4.1 pattern)

### Exit criteria
- Fresh `migrate:fresh --seed --seeder=WorldwidePassimarkCatalogSeeder` produces the expected session/exam/question counts across ≥2 regions
- A learner can enroll in a non-CISSP cert (e.g. AWS-SAA) and see lesson 1 open, everything else locked
- Existing CISSP demo data still seeds and passes regression suite

---

## Sprint 6: CAT engine v4 — IRT 3PL

### Goal
Replace nearest-difficulty selection with true MLE theta + Fisher-information item selection.

### Tasks
- **6.1 Model updates** — IRT fields (`a`/`b`/`c`) canonical reads on `PassimarkQuestion`
- **6.2 `CatEngine` rewrite** (`app/Services/CatEngine.php`)
  - 3PL probability `P(θ)=c+(1−c)/(1+exp(−a(θ−b)))`
  - Newton-Raphson MLE update (≤10 iterations, clamp [−3,3], tolerance 0.001)
  - Fisher-info next-question selection, prefer `|b−θ|<0.5`, tie-break high `a`, exclude answered
  - Termination: per-exam `passTheta`/cutoff (cat) or exam `question_count`
  - `passScoreScaled` (θ + scaled score crosswalk, per-cert `pass_score`)
- **6.3 Exam flow** — `PassimarkController::answer` uses MLE path in CAT mode; store final θ + score on attempt and progress
- **6.4 Verify** — unit tests: MLE converges on synthetic responses, θ bounds respected, Fisher picks the most informative near-θ item, termination rules fire, no regression on timed/practice modes

### Exit criteria
- θ converges correctly on known synthetic data (unit-tested)
- CAT uses Fisher selection; timed/practice unchanged
- Full PHPUnit suite + production build green

---

## Sprint 7: Pearson VUE exam chrome + mastery dashboard

### Goal
Match the exam-day experience and the dotted→solid mastery UI.

### Tasks
- **7.1 `Exam.jsx` Pearson VUE chrome** — top countdown timer, question palette (answered/unanswered/flagged), flagged-questions review, strike-through on options, on-screen whiteboard, calculator, break dialogs (natural breaks on mocks/finals), no-hints rule on finals
- **7.2 `Dashboard.jsx` cert grid** — per-cert cards, progress ring SVG with `stroke-dasharray` dotted (`4 6`) → solid on mastery, theta trendline per cert, weak-zone heatmap per domain
- **7.3 Layout** — header θ badge (learner), nav per track region; keep premium dark theme (`#0F172A`), brand green `#1A9E2D`/accent `#7CFC8F`
- **7.4 Design tokens** — extract the v4 palette/type scale into `tailwind.config.js` (closes the long-standing "no design tokens" risk)
- **7.5 Verify** — manual QA script + existing feature tests stay green; `npm run build` clean

### Exit criteria
- Exam UI exposes palette/flag/strike/calculator for mocks + finals, hidden or disabled in practice lessons
- Dashboard shows certs grouped by region with dotted→solid rings and θ trendline
- No frontend regressions in existing flows

---

## Sprint 8: Certificates, PWA, deployment readiness

### Goal
Close the learner loop with verifiable credentials and harden distribution.

### Tasks
- **8.1 Certificate** — `Certificate.jsx` post-final: θ, pass probability (1PL-style derived from passTheta gap or 3PL likelihood), credential ID `PMK-{CERT}-2026-{HEX}`, QR → `verify.passimark.com/c/PMK-…` (verification endpoint can be a stub route initially), issuer metadata
- **8.2 Pass records** — `passimark_progress` gains `certified_at`, `credential_id`, `pass_probability`; only finals can certify; store hash placeholder for future Polygon/IPFS anchoring
- **8.3 PWA manifest** — set `background_color` `#0F172A`; verify standalone display + icon set; favicon ico chain
- **8.4 Deployment guide** — capture v4 flow (spec section 9) into `DEVELOPMENT.md` / `GETTING_STARTED.md`; seed toggle documented
- **8.5 Verify** — feature test: passing a final issues a credential + QR payload; PWA manifest assertions; builds green

### Exit criteria
- Passing a 100%-spec final issues a verifiable credential shown to the learner
- QR verification URL resolves and validates the credential ID
- PWA manifest matches brand; deployment doc step-through works on a clean env

---

## Sequencing notes
- **Sprint 4.2 tag taxonomy first** — the catalog renames `domain` to a tagged model; doing tags after session `phase_type` migration conflicts more.
- Re-run **Sprint 5 before 6** so the engine has real multi-cert IRT data to tune against.
- Sprints 7-8 are UI/concretization; they can overlap with 6 once the schema is stable.
- former Sprints 5-8 (UX polish, analytics, packaging, QA) shift to Sprints 9-12 in [implementation-sprint-plan.md](implementation-sprint-plan.md).

## Definition of done
- Complete v4 scope = Sprint 5-8 exit criteria all met + full PHPUnit suite green + production build clean + catalog drift report (seeded counts vs spec §4) zero.