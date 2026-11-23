# Sprint 7.9c — Authored bundle catalog (synthetic Uniform‑205 displaced)

**Status:** **done** (content sprint; supersedes the generated pools from Sprint 7.9b)
**Report date:** post‑Sprint 9 (bundles landed in waves after the funnel/package work)
**Depends on:** Sprint 7.6 (multi‑bundle placement), 7.7 (generated bank), 7.9b (Uniform‑205 coverage)

## Why

Sprint 7.9b closed the *coverage* gap: every Uniform‑205 session had *a* question. But the
questions were generated from a template, so the catalog was uniform in shape and generic in
content — every cert read the same. This sprint replaces every `seed:uniform-205` row with a
real, exam‑shaped **authored bundle** written from each issuer's published content outline,
using the format in [content-format.md](content-format.md).

The deliverable is no longer "a question in every session"; it is **one authored bank per
certification**, seeded through the same `bundle-per-cert` path the CISSP bundle proved.

## What shipped

- **203 authored bundles** in `database/seeders/data/bundles/<cert_key>.json`, each seeded as
  a `seed:authored:<cert_key>` track. The generated `seed:uniform-205` source is now **0 rows**.
- Catalog reconciled end to end: **203** authored tracks + CISSP (`seed:cissp-bundle`) + 5
  `enterprise-*` role bundles (`seed:enterprise-roles`) = **209** tracks.
- Each bank: 4 domains × 15 items (60 items) unless the real blueprint dictates otherwise,
  `content_provenance` + `source_label` set, `reference` naming a real checkable source,
  `explanation` on every item, no psychometric keys (difficulty/discrimination/guessing/theta).
  Answer keys are rotated deterministically by the seeder, so A–D balance is automatic.
- Guard extended per wave in `tests/Feature/AuthoredBundleSeederTest::realLengthBundles()`
  (total + per‑domain split). The guard **caught a real corruption**: `gmat-focus.json` had
  been truncated to 48 items and duplicated `gmat-focus-043`; restored from HEAD (60 items),
  re‑validated, re‑seeded.
- Security wave authored: CEH, CHFI, GCFA, GCIH, GPEN, GSEC, OSCP (420 items, `USA-SECURITY`).
- **GoTranscript bundle added** on request (public GoTranscript transcription guidelines):
  `cert_key: gotranscript`, region `GLOBAL-ACADEMIC`, 60 items across 4 domains — "Verbatim
  Formats and Editing", "Timestamping and Speaker Labels", "Sound Events and Non-Speech
  Markings", "Numbers, Punctuation, and Grammar" — with `outlines/gotranscript.json`
  (4 domains × weight 25). Track id 215, 4 sessions, 60 questions, else nothing gained over the
  other banks.

## Evidence

```
integrity probe (dev DB)
  tracks=209  authored=203  uniform205=0
  sessions=890  questions=14768
  orphan_sessions=0  orphan_questions=0
  nonnull_psychometrics=0  nonnull_theta_required=0
  question_count mismatches=0  bad keys=0
  empty explanations=0  empty references=0
  answer-key spread  A4084  B3737  C3487  D3460
```

- `php vendor\bin\phpunit` → **437 tests / 122,969 assertions** green.
- `validateall.php` → **203/203 bundles** validate clean (0 failed, 0 warnings).
- `php artisan passimark:validate-bundle <key>` → clean for every key (including `gotranscript`).
- Live `php artisan serve` smoke (seeded learner): `/`, `/catalog/search`, `/certs/gotranscript`,
  `/certs/gotranscript/gotranscript`, `/api/sessions`, `/api/progress`, `/passimark/focus` all
  **200**; search resolves "GoTranscript Transcription Test".

## Bug fixed while verifying (shipped as its own commit)

`/catalog/search` returned **500** on the dev server (`no such table: cache`). Laravel 11
defaults `CACHE_STORE` to `database`, but the project has no `cache` table and no cache
migration; the test suite never surfaced it because `phpunit.xml` forces `CACHE_STORE=array`.
Fixed by setting `CACHE_STORE=file` in `.env` and `.env.example` (matches the file session
driver). See the deployment guide's `.env` matrix.

## Commits

- `c8a73bf` — `feat(bundles): author security-certification wave (CEH, CHFI, GCFA, GCIH, GPEN, GSEC, OSCP; 420 items)`
- `116bb71` — `feat(bundles): author GoTranscript transcription test bundle (60 items)`
- `885fbc4` — `fix(config): default CACHE_STORE to file so catalog search stops 500ing`

## Notes / follow-ups

- 21 outline files are recorded under `database/seeders/data/outlines/`; `outline_file` is
  optional, so not every bank carries one. Adding an outline for a bank upgrades its session
  weighting to the real blueprint.
- The 5 `enterprise-*` role bundles and the CISSP textbook bundle are separate sources and are
  not counted among the 203 authored `seed:authored:*` tracks.

## GoTranscript bank re-authored against the official guidelines

The first GoTranscript bank was written from memory of the transcriber test and did not match
GoTranscript's own guidance. It was rewritten from the primary sources:

- **Sources.** https://gotranscript.com/transcription-guidelines (the 28-rule guide GoTranscript
  tells applicants to read before the test) and
  https://gotranscript.com/public/step-by-step-guide-to-signing-up-and-taking-the-gotranscript-transcription-test
  (how the test is actually run). Both are recorded in the bundle's `content_provenance` and in
  the new `guide` block (`name`, `url`, `test_job`, `structure`).
- **Shape.** 114 items (`gotranscript-001`..`gotranscript-114`), 6 domains × 19 — Full and Clean
  Verbatim; Timestamps, Speaker Labels and Layout; Sound Events and Non-Speech Markings; Numbers,
  Times and Measurements; Dashes, Quotation Marks and Italics; The Test Job, Ratings and House
  Style. `outlines/gotranscript.json` rewritten to the same 6 domains (weight 16.67 each,
  sections A–O with objectives, `outline_source` = the guidelines URL, `outline_retrieved`
  2026-10-22). The domain split is ours — GoTranscript publishes no blueprint.
- **Citations.** Every item carries a `reference` that names the rule it comes from
  ("GoTranscript transcriber guidelines - …") or the walkthrough page, and an explanation that
  quotes the rule. 35 answer keys were wrong in the first pass and were corrected after review.
- **Structure facts encoded.** Part 1 is a guidelines quiz (100% required, one or more correct
  answers per item), Part 2 is the test job itself (clean verbatim, no timestamping), results are
  reviewable for 60 days, payments run every Friday, a >4.5 average rating opens the editor path,
  and 3 transcripts averaging ≤3.6 leads to removal.
- **Exam framing.** The `exam` block states the real test is 1 hour with 1–2 items, so the bank
  is practice material, not a mock of the real scoring. `source_label` is "GoTranscript practice
  bank"; the description says the bank is authored from the public guide, is not a capture of
  past test questions, and is not endorsed by GoTranscript.
- **Verification.** `php artisan passimark:validate-bundle gotranscript` → clean (0 errors,
  0 warnings); `passimark:seed-bundle --bundle=gotranscript` → 1 track, 6 sessions, 114 questions
  with rotated options; a live learner run through session 1 answered 19/19 correct (score 100,
  passed) and the result page showed every explanation and reference.
- **Platform fix found while verifying.** A session can only serve the items attached to it, so
  the lazily created CAT exam advertised the 150-question default for a 19-item session
  ("Question 19 of 150", 12% progress). `PassimarkController::start()` now caps the exam's
  `question_count` at the real pool size.
