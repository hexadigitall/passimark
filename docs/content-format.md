# Passimark Authored Bundle Format

One JSON file per certification. Drop it in `database/seeders/data/bundles/`, validate it,
seed it. We do the rest (sessions, tags, key balancing).

## Why we need this shape

- We do **not** take difficulty/discrimination/guessing/theta in the file. Anything that
  claims a psychometric value is the exact fabricated calibration this project strips out.
  We store `null` and `Irt3PL` uses its own documented defaults.
- We do **not** take an answer key position story. Every item's correct answer is rotated
  deterministically on load so keys stay balanced across A-D. You just say *which* option is
  right; we decide *where* it lands.
- The `reference` is the honesty gate. It must name a real, checkable source. If a reference
  only says "official exam guide", the validator rejects the file.

## File layout

```
database/seeders/data/bundles/<cert_key>.json
```

```json
{
  "cert_key": "cisa",
  "title": "Certified Information Systems Auditor",
  "issuer": "ISACA",
  "outline_file": "outlines/cisa.json",
  "content_provenance": "Original practice items authored from the issuer's published content outline. Not a capture of past exam questions.",
  "source_label": "CISA practice bank",
  "items": [
    {
      "id": "cisa-d1-001",
      "domain": "Information System Auditing Process",
      "objective": "1.A.3",
      "stem": "An IS auditor is planning the audit of a new ERP deployment. What is the most appropriate first step in building the audit programme?",
      "options": [
        "Perform a business risk assessment to prioritise material areas",
        "Begin substantive testing of transactions immediately",
        "Select the audit team based on seniority rather than skills",
        "Issue the final report to management before fieldwork begins"
      ],
      "correct_index": 0,
      "explanation": "Audit standards require planning to start from a risk assessment so effort is directed at material, high-risk areas before any testing is designed.",
      "reference": "ISACA IT Audit Framework (ITAF), 4th edition - audit planning"
    }
  ]
}
```

## Field rules

| Field | Required | Rule |
|---|---|---|
| `cert_key` | yes | must match the file name |
| `title`, `issuer` | yes | display text; `issuer` is your own factual claim, not the awarding body's endorsement |
| `outline_file` | no | path to `data/outlines/*.json` (weights/objectives) |
| `content_provenance` | yes | one sentence: what these are and what they are not |
| `source_label` | yes | short bank name, written into every `reference` column |
| `items[].id` | yes | unique, stable, URL-safe |
| `items[].stem` | yes | non-empty; must be a complete question |
| `items[].options` | yes | **exactly 4** non-empty strings |
| `items[].correct_index` | yes | integer 0-3. We rotate it; you only mark the right option |
| `items[].explanation` | yes | tells a learner *why*; at least ~20 chars |
| `items[].reference` | yes | a real, checkable source (vendor doc, RFC, statute, textbook). Fake or missing => rejected |
| `items[].domain` | no | grouping for sessions/tags; the seeder makes one session per domain |
| `items[].objective` | no | map to an outline objective id (`1.A.3`) for blueprint weighting |

## What the loader enforces for you

- `correct_index` within 0-3 and a unique, non-empty `id`.
- Exactly four options. No psychometric keys anywhere in the file.
- `pass_score = 70` on every session and `theta_required = null` on every session.
- Session `question_count` equals the number of items that actually attach to it — nothing
  is inflated to look denser than it is.
- Answer keys balanced across A-D (rotated by a stable hash of the item id).
- One track per file, re-created idempotently; seeding never runs a migration.

## Putting a new bundle through

```bash
php artisan passimark:validate-bundle cisa
php artisan passimark:seed-bundle --bundle=cisa
```

Omit `--bundle` to seed every validated bundle in `data/bundles/`.
`validate` prints the session plan and the projected answer-key distribution so you can
see balance before anything touches the database.

## Authoring ground rules (so your content is worth shipping)

1. **Original, not copied.** Author scenario items from the public syllabus/docs. Do not
   transcribe branded/paid exam content — that fails licensing and our provenance rule.
2. **Every answer is defensible.** If the explanation can't say *why* the other three are
   wrong, rewrite the distractors.
3. **Reference something specific**: "Microsoft Learn: FSLogix profile containers",
   "RFC 4861 - IPv6 Neighbor Discovery", not "general knowledge".
4. **The stem must pin a fact.** A stem is only useful if a knowledgeable person can answer
   it and someone who cannot answer will learn from the explanation.

## Status of outlines

Already recorded:
- `data/outlines/cisa.json` — domains/weights verified from ISACA (18/18/12/26/26);
  subtopics present for domains 1-2, domains 3-5 marked incomplete rather than guessed.

Any cert you author needs its own outline recorded the same way before we can claim
blueprint-weighted sessions.