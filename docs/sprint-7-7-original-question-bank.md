# Sprint 7.7 — Original Question Bank (Worldwide certs)

**Status: Complete** | Branch: `feature/sprint-7-5-content-coherence`

## Problem
Sprint 7.6 shipped the Worldwide-17 certification catalog (17 tracks, 346 sessions, 1,038
exams across 7 regions) but every session had **zero questions**. `CatEngine` is
session-scoped and most session dashboards, practice modes and "content needs attention"
reports were effectively empty for 16 of the 17 worldwide certs. The source generator
(`D:\Downloads\forPassimark\Passimark-Original-Question-Bank.html`) is a client-side
"original question bank" builder: given a catalog of certs + objective lists it emits
original practice questions for each. The task was to bring that generator server-side,
make it **deterministic and testable**, and fill every Worldwide session pool to its
`questions_target`. Scope: Worldwide-17 **except CISSP** (which ships its own real
Hexadigitall textbook bundle via `CISSPBundleSeeder`).

## Design

### Deterministic PRNG (`SeededRandom`)
The generator's randomness is a **mulberry32** PRNG. Ported to PHP as
`App\Services\PracticeQuestionBank\SeededRandom`:

- `next()` — mulberry32; requires a 32-bit multiply (`imul32($a,$b)`) that splits the
  operands into 16-bit halves, because PHP's native `*` overflows 64-bit for full uint32
  products. PHP `>>` is logical (no `>>>` needed).
- `range($min,$max)`, `int($min,$max)`, `pick(array)`, `shuffle(array)` (Fisher–Yates).
- Verified byte-for-byte against Node's `mulberry32` (seed `12345` →
  `0.979728267761,…`).

### Generator (`QuestionBankGenerator`)
Deterministic port of the client generator's dispatch `S1()` + scenario templates:

| Cert / vendor | Template | Bank character |
|---|---|---|
| SAA-C03 / AWS | `y1` | Well-Architected scenarios (reliability/perf/security, RTO/RPO) |
| SAP-C02/C03 / AWS | `y1` | same (AWS vendor) |
| PMP / PMI | `m1` | PMBOK-7 ECO scenarios (change control, People domain) |
| CISSP / ISC2 | `v1` | domain scenarios (GDPR breach windows, zero trust) |
| CCNA / Cisco | `yu` | reference + domain forced to the catalog entry |
| SY0-701 / CompTIA | `yu` | generic official-objective items |
| NCLEX-RN | `yu` | generic official-objective items |
| JAMB | `g1` | comprehension passage + algebra (deterministic 3x+2y solving window) |
| SAT | `w1` | algebra / geometry / Information-and-Ideas |
| every other cert | `yu` | generic official-objective items (Azure, academic, finance, health, India) |

Each item is wrapped by `finish()` with the shared envelope: `objective_reference`,
`content`, `options[4]` (keys A–D), `difficulty`, `discrimination`, `guessing` (`0.25`),
`domain`, `bloom_level`, `explanation`, `reference`. `mapOptions()` shuffles and re-keys
A–D (mirrors the JS `String.fromCharCode(65+m)` re-keying).

**Catalog** (`database/seeders/data/original-bank/catalog.json`, 16 entries):
12 mapped from the generator's catalog (`SAA-C03`, `SAP-C02/C03`, `AZ-104`, `CCNA`,
`SY0-701`, `PMP`, `JAMB`, `WAEC`, `SAT`, `IELTS`, `GRE`, `NCLEX-RN`) + 4 synthesized
entries for certs with no generator counterpart that fall back to generic `yu` with
Worldwide-phase domains:
- **ACCA-F1-F4** (Business & Technology BT/F1 50, Management Accounting MA/F2 50)
- **CFA-L1** (Quant & Economics 35, Investment Valuation 35, Portfolio & Ethics 30)
- **ICAN-SKILLS** (Financial Reporting & Audit 55, Taxation & Business 45)
- **JEE-MAIN** (Physics 34, Chemistry 33, Mathematics 33)

Entry shape: `{ code, name, vendor, refDoc, domains: [{name, weight, objectives[]}] }`.
Selection is weighted by `domain.weight`; the objective is `pick(domain.objectives)`.

### Seeder (`WorldwideOriginalQuestionBankSeeder`)
- `SOURCE = 'seed:worldwide-17'` — matches `WorldwidePassimarkCatalogSeeder`.
- `CERT_MAP` cert_slug → catalog code (CISSP intentionally absent).
- `seedQuestionBanks()` (public, testable) walks each worldwide track's sessions, skips
  the CISSP cert, skips sessions that already hold questions (**idempotent**), then:
  - `buildPool()` generates exactly `questions_target` items. **Per-question seed** is
    `crc32($code.'|'.$session->order.'|'.$i)`, so each item in a pool is distinct even
    when two sessions share cert/domain (raw `withSeed` re-use would have duplicated).
  - `insertPool()` bulk-inserts rows (mirrors `CISSPBundleSeeder`), sets `exam_id = null`,
    `external_id = ob-<slug>-s<NN>-q<NNNN>`, derives `correct_key` from the option marked
    `is_correct`, and creates `domain` + `bloom` tags with `passimark_question_tag`
    pivots so the admin `untagged_questions` report stays honest.
- `run()` wraps the fill in a transaction and logs stats.
- `DatabaseSeeder`: the worldwide path now runs `WorldwidePassimarkCatalogSeeder` **then**
  `WorldwideOriginalQuestionBankSeeder`.

## Verification
- `php vendor/bin/phpunit tests/Feature/OriginalQuestionBankSeederTest.php`:
  **8 tests, 20,064 assertions — green**
- Full suite `php vendor/bin/phpunit`: **93 tests, 27,492 assertions — green**
- `php -l` on all changed/added PHP: clean; `npm run build`: green
- Fresh end-to-end on a scratch SQLite DB (`migrate:fresh --seed`, `SEED_CATALOG=worldwide`):
  **16 certs, 319 sessions filled, 16,725 questions in ~12.7 s**. CISSP's 27 sessions are
  skipped as designed.
- Data-integrity probe on the seeded DB: `16725/16725` distinct `external_id`; `0` untagged
  questions; `0` CISSP-generated questions; **319/319 non-CISSP sessions match their
  `questions_target` exactly**; every sampled `correct_key` is a real option key.

### Bugs caught and fixed during verification
- **`yu` emitted 5 options** — the template shuffled all 4 distractors + the correct
  answer. The JS is `Gn([u, ...Gn(o).slice(0,3)])`, i.e. only 3 distractors. Fixed to
  `array_slice(shuffle($distractors), 0, 3)` and re-key after the final shuffle (the
  first fix still returned keys `B,C,D,A` because the shuffle happened after mapping).
- **Idempotency test asserted the wrong thing** — it compared the first run's question
  count to the second run's (which is necessarily `0`). Corrected to assert the second run
  inserts **nothing new** and the DB total is unchanged.
- **Variety test pinned two indices to one seed** — a generic `yu`/`y1` candidate may not
  embed the index, so two indices can legitimately collide under the *same* seed. Corrected
  to compare across distinct seeds (the real determinism/variety contract).

## Files changed
- `app/Services/PracticeQuestionBank/SeededRandom.php` (new) — mulberry32 PRNG.
- `app/Services/PracticeQuestionBank/QuestionBankGenerator.php` (new) — catalog loader,
  dispatch + `y1/m1/v1/g1/w1/yu` templates, `finish`/`mapOptions`.
- `database/seeders/data/original-bank/catalog.json` (new) — 16-cert generator catalog.
- `database/seeders/WorldwideOriginalQuestionBankSeeder.php` (new) — fills the pools,
  idempotent, tags domain/bloom.
- `database/seeders/DatabaseSeeder.php` — worldwide path runs both seeders.
- `tests/Feature/OriginalQuestionBankSeederTest.php` (new, 8 tests).

## Known risks / notes
- These are **original (generated) practice items**, not licensed exam content — the
  explanations/references point at each cert's `refDoc` for the objective mapping.
- Generation is deterministic per `(cert, session.order, index)`. Changing a template or
  the catalog changes the whole bank, so re-seeding an already-populated install will not
  overwrite existing pools (sessions with questions are skipped); run `migrate:fresh` or
  clear a session's questions to regenerate.
- The generator is a *bank filler*, not a psychometric instrument: `difficulty` /
  `discrimination` are bounded random draws (matching the source), so CAT behaviour is
  driven by the existing IRT cutoffs over plausible item parameters.
