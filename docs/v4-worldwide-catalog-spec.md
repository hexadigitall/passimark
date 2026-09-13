# Passimark v4.0 — Worldwide Catalog Specification

**Status:** approved spec (external source), not yet implemented in repo
**Source:** `Passimark-Implementation-Guide.html` (v4.0 • FINAL APPROVED • JAN 2026), `Passimark-Worldwide-Catalog.html`, `WorldwidePassimarkCatalogSeeder.php`, `Building-exam-simulator-app.{php,txt}`, `passimark-v4-WORLDWIDE-FINAL.zip` (all under `D:\Downloads\forPassimark`)
**Author:** TechPoet Dimeji — Passimark Labs

This document distills the v4.0 build target into a form the repo can plan against. It records what the approved v4.0 spec requires, so engineering docs and sprints can be compared against it directly.

---

## 1. Product vision

Passimark is a **global adaptive examination intelligence platform** covering 200+ certifications — AWS to JAMB, PMP to NCLEX, SAT to JEE. Every certification follows the **same 5-stage CAT ladder**, from atomic 25-question micro-lessons to a 100% real-spec final exam that issues a verifiable certificate.

Core identity: **P = Good Mark**. The logo is the product logic — unmastered knowledge is shown as dotted square pixels that turn solid as the learner's Theta rises through adaptive testing.

Scope numbers:
- 200+ certifications; the client-side generator (`Passimark-200-Sql-Generator.html`) ships the **exact 205-cert catalog** across **9 tracks** — saved at [worldwide-205-cert-catalog.json](worldwide-205-cert-catalog.json); the standalone seeder ships **17 certs** with custom phase/lesson content
- 9 track regions (see §4)
- Avg total questions per cert ≈ 993 (e.g. AWS SAA ≈ 1,093 before dedup / adaptive pruning)
- Theta range −3.0 → +3.0; default pass threshold θ ≥ 0.0; IRT model **3PL**

## 2. Brand kit

### 2.1 Logo
- Approved source: `photo6321858088250300469.jpeg` (glossy green oval, white P-checkmark)
- Dotted square pixels (4-5, white, 2px gap, ~22% of oval width) form the **top arch only**; pixels become solid as mastery climbs
- Checkmark solid white, stem 3.5px, long right arm 1.6× left, extends 8% beyond oval

### 2.2 Colors
| Token | Value | Use |
|---|---|---|
| Primary | `#1A9E2D` | brand green |
| Deep | `#0F5D2F` | secondary green |
| Accent | `#7CFC8F` | highlights |
| Dark | `#0F172A` | dark surface / theme |

### 2.3 Usage rules
- Clear space = ½ oval height all sides; minimum 24px digital / 12mm print
- On light: oval with shadow, wordmark black. On dark: oval with glow, wordmark white
- Icon alone allowed for favicon/app (1024 → 16 scale chain)
- Never: flat green, reversed gradient, dotted checkmark, circular dots, stretched/rotated/ recolored

### 2.4 Assets (target naming)
- `public/images/passimark/logo-final-1024.png`, `logo-final-512.png` (transparent)
- `public/images/passimark/icon-*.png` (16, 32, 64, 128, 180, 192, 256, 512, 1024) + `public/favicon.ico`
- `public/manifest.json` — `theme_color` `#1A9E2D`, `background_color` `#0F172A`, `display` `standalone`

> Repo delta: assets are wired but use `passimark_*` filenames; `manifest.json` background is `#ffffff` (must be `#0F172A`). Sprint 4.0 already mounted favicon + manifest icon set.

## 3. CAT ladder methodology (5 stages)

Every certification is a journey from atomic knowledge to exam-day realism. Stages gate via **Theta and pass-rate**.

| Stage | Q count | Timer | Content | Gate / behavior |
|---|---|---|---|---|
| **Lesson** | 25 CAT | None (User's Pace) | Single topic micro-lesson | First lesson of phase 1 open; others unlock as pixels solidify |
| **Phase** | 50-75 CAT | Soft timer (visible, non-blocking) | Cluster of 4-6 lessons | Must pass **70%** to unlock next phase; failed phase → dotted diagnostic + recommended lessons |
| **Domain** | 50-75 CAT | @UP + pressure | Cluster of phases | Pressure indicator, per-question time hint (e.g. 2.0 min/Q); theta becomes domain-specific; weak-zone heatmap of phases |
| **Mock** | 70% / 100% / 120% of final | Full CAT timer | Pearson VUE-identical chrome | 3 variants: speed confidence → exact rehearsal → overtraining stamina. `flag/review`, question palette, on-screen **whiteboard**, calculator, break dialogs |
| **Final** | 100% real spec | Real time | Real #Q, real breaks, no hints | Verifiable certificate with θ, pass probability, QR, credential ID |

- Certification container session acts as "Phase 0" metadata wrapper for the cert.
- Solidity metaphor in UI: SVG progress rings `stroke-dasharray` dotted (e.g. `4 6`) for weak → solid for strong; animates the transition on mastery.

## 4. Worldwide track catalog

Seeder regions and shipped certs (`WorldwidePassimarkCatalogSeeder.php`):

| Region | Certs (17) | Final Q | Final time | Phases |
|---|---|---|---|---|
| USA-IT-CLOUD | AWS-SAA, AWS-SAP, AZ-104, CCNA | 60-120 | 120-180m | 2-5 |
| USA-IT-SECURITY | SEC+, CISSP | 90-150 | 90-180m | 3-4 |
| USA-PMP | PMP | 180 | 230m | 3 |
| UK-EU-FINANCE | ACCA-F1-F4, CFA-L1 | 50-180 | 120-270m | 2-3 |
| AFRICA-NG | JAMB-SCI, WAEC-SCI, ICAN-SKILLS | 60-180 | 120-180m | 1-3 |
| GLOBAL-ACADEMIC | SAT, IELTS, GRE, NCLEX-RN | 40-145 | 134-300m | 2-4 |
| APAC-IN | JEE-MAIN | 90 | 180m | 3 |

Each cert in the catalog ships: 1 cert container + lessons + 1 phase assessment per phase + domain assessments (~1 per 2 phases) + 3 mocks (70/100/120%; ACCA/WAEC have 2) + 1 final real-spec. **Estimated ~450 sessions for the shipped set.**

Representative finals: AWS SAA 65Q/130m (pass 72), CISSP 150Q/180m (pass 70), PMP 180Q/230m (pass 65), NCLEX-RN 145Q/300m (pass 70), JAMB 180Q/120m (pass 70).

ITT config schema (`Building-exam-simulator-app.php`):

```php
'TRACK' => [
  'CODE' => ['name'=>'Full Name','desc'=>'Desc','pass_score'=>70,'final_q'=>65,'final_time'=>130,'mock_count'=>3,'phases'=>[
    ['name'=>'Phase Name','phase_q'=>60,'phase_time'=>75,'lessons'=>['Lesson1','Lesson2']],
  ]],
]
```

## 4b. 205-cert generator catalog (SQL Generator tool)

`Passimark-200-Sql-Generator.html` embeds the complete catalog as an array of `{code, name, track, final_q, final_time}` — extracted wholesale to [worldwide-205-cert-catalog.json](worldwide-205-cert-catalog.json). Track totals:

| Track | Certs | Avg final Q | Max final Q | Avg time (min) |
|---|---|---|---|---|
| USA-CLOUD | 30 | 69 | 120 | 117 |
| USA-SECURITY | 20 | 120 | 180 | 260 |
| USA-BUSINESS | 15 | 96 | 200 | 139 |
| UK-FINANCE | 25 | 71 | 180 | 168 |
| AFRICA | 20 | 90 | 180 | 150 |
| GLOBAL-ACADEMIC | 35 | 121 | 318 | 247 |
| APAC | 30 | 98 | 200 | 151 |
| MIDDLE-EAST | 15 | 109 | 180 | 142 |
| EU-LANG | 15 | 93 | 120 | 146 |

**205 certs → 14 sessions × 3 exam modes each = 2,870 sessions / 8,610 exams** (uniform, non-config-driven ladder — "METHODOLOGY v2 • UP ADAPTIVE").

### Uniform 14-session ladder (v2 generator) — per cert `{code}`
| Order | Phase | Session title pattern | domain | Q | Time | is_open |
|---|---|---|---|---|---|---|
| 0 | 0 | `{code} - Complete Certification Track` | CERT-OVERVIEW | final_q | final_time | 1 |
| 1-3 | 1 | `Lesson {1-3}: Foundations/Core Concepts/Advanced Basics` | `{code}-L{1-3}-FOUND/CORE/ADV` | 25 | 35 | 1 |
| 4-6 | 2 | `Lesson {4-6}: Implementation/Troubleshooting/Mastery & Edge Cases` | `{code}-L{4-6}-IMPL/TROUBLE/MASTER` | 25 | 35-40 | 1,1,0 |
| 7-8 | 3 | `Phase {1-2} CAT: Lessons 1-3 / 4-6 Cumulative 60Q @UP` | `{code}-PHASE1/2` | 60 | 70 | 0 |
| 9 | 4 | `Domain Mastery: Full Domain 75Q @UP Pressure` | `{code}-DOMAIN` | 75 | 90 (pass 75) | 0 |
| 10-12 | 5 | `Mock {1-3}: 70% Pressure / 100% Real Spec / 120% Overload` | `{code}-MOCK70/100/120` | 70%(min15)/100%/120%(ceil) | 70%/100%/120% of final_time | 0 |
| 13 | 6 | `Final: Real Exam Spec - {code} ({final_q}Q / {final_time}min)` | `{code}-FINAL` | final_q | final_time | 0 |

### Exam modes per session (generator naming)
- `cat` → mode `'adaptive'`, `time_limit = 1.5 × session time_limit`
- `timed` → mode `'timed'`, `time_limit = session time_limit`
- `practice` → mode `'practice'`, `time_limit = 0`

The generator outputs SQL in 4 chunks (50/50/50/55 certs), a full JSON catalog, a chunked PHP seeder, and an implementation guide MD — all generated client-side (Blob + object URL) to bypass server timeouts.

> Note: the 17-cert standalone seeder uses **per-cert phase/lesson content** (custom phase names, pass scores, mock counts 2-3); the 205-cert generator uses the **uniform 14-session pattern** above. Sprint 5 should decide which is the canonical mass-load path (recommendation: uniform pattern for 205, custom seeder for flagship certs).

## 5. Data model delta (current schema → v4)

### `passimark_sessions`
Current: `number, phase, title, description, domain, is_open, order, pass_score, time_limit, question_count`.
v4 (guide inline code) uses: `cert_slug`, `phase_number`, `phase_type` (`cert|lesson|phase|domain|mock|final`), `questions_target`, `time_minutes`, `theta_required`.
→ **Decision needed:** migrate/rename columns to `phase_type` model (recommended for 200-cert scale), mapping `is_open` gating → theta/pass gates, `time_limit` → `time_minutes`, `question_count` → `questions_target`. Existing `certification_track_id` FK (Sprint 4.1) should be the join to tracks; `cert_slug` can derive from the track.

### `passimark_exams`
Current: `session_id, title, mode(timed|practice|cat), question_count`.
v4 adds: `time_minutes`, `is_final`, `irt_enabled` (CAT only).
The 205-cert generator uses `exam_code` (`adaptive`/`timed`/`practice`, CAT = 1.5× time, practice = 0) keyed by `session_number`; reconcile `adaptive` ↔ `cat`, `1.5×` multiplier, and `is_open` on exams.

### `passimark_questions`
Current: `content, options(json), difficulty, discrimination, guessing, domain, bloom_level, explanation, reference`.
v4 naming: `stem`, `options_json`, `correct_key`, `a_discrimination`, `b_difficulty`, `c_guessing`, `domain_tag`. Keep existing columns while refactoring IRT field names and adding `correct_key`. The Spring 4.2 tag taxonomy (`passimark_tags`) remains the intended replacement for free-text `domain`/`bloom_level`.

## 6. CAT engine v4 — IRT 3PL

Current repo engine (`app/Services/CatEngine.php`) = nearest-difficulty selection, termination at 150 Q or |θ|>2.5 with 75+, percentage score. **v4 replaces this with true IRT MLE.**

- **Model:** 3PL. P(θ) = c + (1 − c) / (1 + exp(−a·(θ − b))). a 0.3–2.5, b −3..+3, c 0–0.35.
- **Theta MLE:** after each response, θ̂ maximizes Σ[u·log P + (1−u)·log(1−P)] via Newton-Raphson, 0..10 iterations, clamp [−3, 3], break when |Δ|<0.001.
- **Next question:** Fisher Information I(θ) = a²·(P−c)²·(1−P) / (P·(1−c)²); pick max-I from pool not yet seen, preference for b within ±0.5 of θ, break ties by high a.
- **Passing:** `passTheta` (per cert, default θ ≥ 0.0) + `passScoreScaled` (converted to the exam's scaled pass threshold).
- **Why 3PL:** 1PL assumes a=1,c=0 (too simple); 2PL ignores guessing — critical for JAMB/SAT/AWS. 3PL captures real exam noise and prevents θ inflation from lucky guesses.
- **Termination:** real CAT cutoffs per exam (e.g. NCLEX 75–145); otherwise exam question count.

## 7. Frontend suite (Pearson VUE parity)

Target components (names from guide; repo currently has `DashboardLayout.jsx` and removed `PassimarkLayout.jsx`):

| Component | v4 requirement | Repo today |
|---|---|---|
| `PassimarkLayout.jsx` | Header — correct logo, nav, θ badge, footer | Removed in 4.0.1 (single shell) |
| `Dashboard.jsx` | Grid of cert cards, progress rings dotted→solid, θ trendline | Exists, single-track CISSP curriculum |
| `Exam.jsx` | Pearson VUE chrome: top timer, question palette, flagged/review, strike-through, calculator, break dialogs | Exists; timer/instructions/results wired, no palette/flag/calculator/strike |
| `Admin.jsx` | Catalog CRUD, IRT calibration, question import CSV/JSON | Exists; CRUD + JSON import, no IRT calibration |
| `Certificate.jsx` | Verifiable QR, credential ID, θ, pass probability | **Missing** |
| PWA | `manifest.json` standalone, theme `#1A9E2D`, bg `#0F172A` | Manifest exists; bg must change |

## 8. Certificate & verification

- Output: **Passimark Certified** · Theta=1.84 · Pass Probability=94.2% · Credential ID `PMK-{CERT}-2026-{HEX}` · QR → `verify.passimark.com/c/PMK-…` · blockchain hash (Polygon + IPFS).

## 9. Deployment (v4 target)

12-step flow in guide: `composer install --no-dev` → `npm ci && npm run build` → `key:generate` + `storage:link` → `php artisan migrate:fresh --seed --seeder=WorldwidePassimarkCatalogSeeder` → optimize/caches → asset + manifest check → `queue:work` + cron `schedule:run` → permissions → PHP 8.2+ / opcache / Nginx or Apache docroot `public/` → verify ≈450 sessions for 15 certs → seeded admin `admin@passimark.com` / `Passimark2026!` (change immediately). Repo is **Laravel 11** (guide text says Laravel 10 — treat version as repo truth).

## 10. Gap summary (repo vs v4)

| Area | Status |
|---|---|
| Brand assets wired (favicon, icons, manifest) | Partial — filenames differ, manifest bg wrong |
| Worldwide catalog + seeder | **Missing** (17 cert seeder + 205-cert JSON provided, not yet loaded) |
| `phase_type` session model + theta gates | **Missing** |
| Exam `time_minutes`/`is_final`/`irt_enabled` | **Missing** |
| IRT 3PL MLE + Fisher engine | **Missing** (nearest-difficulty today) |
| Dotted→solid progress rings / θ trendline | **Missing** |
| Pearson VUE chrome (palette/flag/calculator/strikes) | **Missing** |
| Certificate + QR + credential verification | **Missing** |
| PWA manifest bg color | Fix required (`#ffffff` → `#0F172A`) |

See [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md) for the implementation breakdown.