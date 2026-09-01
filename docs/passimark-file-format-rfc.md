# Passimark Portable Assessment Format (Draft)

**Status:** Proposed

## Decision

Passimark should support a portable, application-owned assessment package. The canonical extension should be **`.psmk`** (Passimark package), with MIME type `application/vnd.passimark.package`.

Use one format family and one compatibility contract initially. A package declares whether it contains an `item-bank`, `module`, `exam`, or `course` in its manifest. This is preferable to immediately maintaining different `.psme` and `.psmm` specifications, which would duplicate validation, versioning, signing, and import behavior.

Later, Passimark may offer convenience exports named `.psme` for a single exam and `.psmm` for a module. They must contain the same versioned package format and be accepted by the `.psmk` importer. The extension is only a presentation hint; `manifest.content_type` is authoritative.

## Why This Is Worth Doing

The format makes authored questions portable between Passimark workspaces and supports offline authoring, reviews, version control, curated catalogs, and partner distribution. It also preserves the data that makes Passimark distinct: question metadata and IRT parameters used by the CAT engine.

It should not be described as an industry standard until it has a public specification, a stable compatibility policy, validation tools, published example packages, and at least one independent producer or consumer. Build it as an open, documented format from the start; earn standard status through adoption.

## Package Shape

A `.psmk` file is a ZIP archive. It is inspectable, can hold media safely, and keeps the top-level file easy to exchange.

```
assessment.psmk
  manifest.json
  content.json
  assets/
    network-diagram.png
  signatures/
    manifest.sha256
```

Version 1 does not require assets or signatures. Importers must reject archives that exceed configured file-count, uncompressed-size, or per-file-size limits.

### `manifest.json`

```json
{
  "format": "passimark",
  "format_version": "1.0",
  "package_id": "a4da22ab-9a4d-46ec-abcf-07ebbe3dd39c",
  "content_type": "module",
  "title": "Security Governance",
  "created_at": "2026-09-01T12:00:00Z",
  "updated_at": "2026-09-01T12:00:00Z",
  "producer": { "name": "Passimark", "version": "1.0" }
}
```

`format_version` follows semantic versioning. A reader may accept a new minor version only when it ignores unknown optional fields; it must reject a newer major version with a clear upgrade message. Every exported object has a UUID that is independent of local database IDs.

### `content.json`

The first release should use JSON Schema as the normative data contract. An abbreviated module example follows:

```json
{
  "module": {
    "external_id": "c59c801c-7d8d-4eb4-a43d-7c0dbe764554",
    "number": 1,
    "phase": 1,
    "title": "Security Governance & Frameworks",
    "description": "Core governance principles.",
    "domain": "Security and Risk Management",
    "pass_score": 70,
    "time_limit_minutes": 90,
    "questions": [
      {
        "external_id": "e9b0a420-72d1-4e16-9e0c-7473fb5c6e69",
        "type": "single_choice",
        "prompt": "What is the primary difference between ISO 27001 and ISO 27002?",
        "choices": [
          { "id": "A", "text": "27001 is certifiable; 27002 is guidance." },
          { "id": "B", "text": "They are identical." }
        ],
        "correct_choice_ids": ["A"],
        "explanation": "ISO 27001 defines ISMS requirements.",
        "reference": "ISO/IEC 27001",
        "taxonomy": { "domain": "Security and Risk Management", "bloom_level": "Apply" },
        "irt_3pl": { "difficulty": -0.5, "discrimination": 1.2, "guessing": 0.25 }
      }
    ]
  }
}
```

Version 1 supports `single_choice` only. Multiple choice, multiple response, matching, scenario, and performance items are future format versions, not fields to improvise during import.

An `exam` package includes `exam` metadata (`mode`, `question_count`, timing, and optional explicit item references) and either embeds its questions or references items in an item bank. A `course` can contain an ordered set of modules and exams. A module is the direct mapping to the current `PassimarkSession` model.

## Current-System Mapping

| Package field | Existing storage |
| --- | --- |
| `module.number`, `phase`, title, domain, timing, score | `passimark_sessions` |
| `exam.mode`, title, question count | `passimark_exams` |
| prompt, taxonomy, explanation, reference | `passimark_questions` |
| choices and answer keys | `passimark_questions.options` JSON |
| `irt_3pl` values | `difficulty`, `discrimination`, `guessing` |

The exporter must remove the answer key from a learner-safe delivery representation. Author packages may include `correct_choice_ids`; learner packages must not. This is a security boundary, not merely a UI concern.

Attempt, answer, user, and progress records are intentionally excluded from V1. Importing content must never overwrite learner history or unlock session progression.

## Import and Export Behavior

Exports originate from a specific module, exam, or curated item bank and stream a ZIP response. Imports are instructor/admin-only, upload to private storage, then run as a staged workflow:

1. Confirm extension, MIME type, archive limits, and required files.
2. Decode JSON and validate it against the V1 schema and business rules.
3. Present a dry-run summary: created, updated, skipped, unsupported, and validation errors.
4. Require the author to choose `create`, `update by external_id`, or `duplicate` behavior.
5. Import within a database transaction and retain an audit record.

Validation includes UUID uniqueness, supported item type, nonempty prompt, unique choice IDs, exactly one correct choice for `single_choice`, IRT bounds, valid module/exam references, and asset-path containment. The importer must never extract arbitrary archive paths, trust client filenames, or merge records by title.

## Converting Other Assessment Formats to `.psmk`

Source import is a separate pipeline from `.psmk` import. A `.psmk` package is trusted only after it conforms to the Passimark schema; source files are untrusted input that must be extracted, normalized, reviewed, and then exported as a new package.

```
Source upload -> source adapter -> normalized draft items -> validation -> author review -> .psmk export/import
```

Each draft item retains source filename, page or item location, extraction method, import batch, and a confidence level. It must be possible to reject an item or correct its text, choices, and answer key before it enters `passimark_questions`.

### Source support priority

| Source | Strategy | Fidelity | V1 priority |
| --- | --- | --- | --- |
| `.psmk` | Schema validation and direct import | Exact | First |
| CSV/XLSX | Column-mapped importer and preview | High | First |
| IMS QTI 2.x ZIP | XML adapter to normalized draft items | High for supported item types | Next |
| Moodle XML and GIFT | Dedicated adapters | High for multiple-choice items | Next |
| Canvas/Blackboard QTI exports | QTI adapter, with export-version detection | Medium to high | Next |
| JSON from an approved authoring tool | Versioned adapter | High | Partner-led |
| DOCX/RTF/plain text | Structural extraction plus review | Medium | Later |
| Text PDF | PDF text extraction plus review | Low to medium | Later |
| Scanned PDF/image | OCR plus mandatory review | Low | Later |
| VCE | Only a documented, authorized export path or licensed adapter | Variable | Do not promise for V1 |

Other formats users may encounter include ExamView banks, Respondus/Blackboard packages, Word documents, spreadsheets, and vendor-specific test-engine files. Prefer their documented export to QTI, CSV, Moodle XML, or GIFT rather than reverse engineering a proprietary file.

### PDF conversion

PDF is a presentation format, not an assessment interchange format. It often loses the facts needed to produce a valid question: where a prompt ends, which lines are choices, whether a page footer belongs to the content, and which choice is correct. Scanned PDFs add OCR errors.

The PDF workflow should therefore create a **reviewable draft**, never silently publish an assessment:

1. Store the original file privately and verify upload limits and malware scanning where available.
2. Extract text from born-digital PDFs; run OCR only for page images or scanned PDFs.
3. Identify candidate prompts and choices with configurable patterns, keeping page citations.
4. Show a split review interface with source context beside editable normalized questions.
5. Require the author to confirm every answer key, correct malformed choices, supply or approve metadata, and explicitly choose IRT defaults.
6. Run the normal package validator, then create or download the `.psmk` package.

The first PDF release should support clean, text-based, single-choice question sheets with a recognizable answer key. Tables, diagrams, question stems split across pages, and scanned books belong in a later assisted-authoring release. PDF conversion quality must be reported as review coverage, not advertised as lossless conversion.

### VCE conversion

`.vce` is associated with commercial Visual CertExam ecosystems and is not an open interchange standard. Its structure, encryption, and licensing may vary by producer and version. Do not build or advertise a generic VCE decoder, do not bypass technical protections, and do not accept content unless the uploader has the rights to import it.

The safe product path is:

1. Accept VCE-derived content only through a documented export supplied by the authorized publisher or tool, preferably QTI, CSV, JSON, or a text document.
2. For a licensed partner integration, build an adapter against that partner's documented API or export contract, with written authorization and test fixtures.
3. Route all converted content through the same draft-review and provenance process as PDF imports.

This protects Passimark from turning content ingestion into a mechanism for redistributing certification dumps or proprietary question banks. An uploader attestation, source-license field, and import audit record should be mandatory for every non-`.psmk` conversion.

### Normalized Draft Model

Do not map source files directly into production sessions/questions. Introduce a staging model such as `passimark_import_batches` and `passimark_import_items` before implementing source adapters. A batch records its source type, checksum, uploader, license attestation, processing status, and conversion settings. Each staged item records the normalized prompt, choices, proposed answer key, source location, confidence, validation errors, and reviewer decision.

Only approved staged items can be converted into `.psmk` or persisted as Passimark content. New source adapters implement a small `SourceAdapter` contract that returns these normalized drafts, keeping QTI, CSV, PDF, and partner integrations isolated from the package validator.

## Digitwol Conversion Service

Passimark should refer authors to a separate Digitwol-hosted conversion application for non-`.psmk` source formats. This is the right product split: Passimark owns assessment authoring, delivery, CAT, learner records, and `.psmk` consumption; Digitwol owns file upload, extraction, conversion jobs, source provenance, and downloadable results.

Digitwol is currently a local-first media archiving tool. The assessment converter should be a distinct, rights-respecting product module with its own naming, routes, storage namespace, policies, data model, and test suite. It may reuse generic implementation ideas such as queued jobs, private per-job storage, duplicate checksums, progress reporting, and export downloads, but it must not share social-media download workflows or records.

### Product Flow

1. An author selects **Convert source file** in Passimark.
2. Passimark links to Digitwol with a short-lived, signed return context containing no answer content and no learner data.
3. Digitwol requires account authentication, source-license attestation, and format-specific upload validation.
4. Digitwol creates a conversion job and provides a review screen for any non-deterministic extraction.
5. After review, Digitwol makes a validated `.psmk` download available and optionally records a package checksum and conversion receipt.
6. The author downloads the package or returns to Passimark, which runs its own normal `.psmk` validation before import.

Passimark must validate the returned package as though it was uploaded manually. A Digitwol origin does not bypass schema checks, package-size limits, authorization, answer-key rules, or the import audit trail.

### Integration Choices

Start with **download-and-upload**. It has the smallest trust boundary and is sufficient to prove whether source conversion has demand and acceptable review quality.

After that is proven, add a signed handoff: Digitwol gives the authenticated author a short-lived, single-use URL or one-time exchange code; Passimark downloads the completed package server-to-server, validates it, and places it in the standard import preview. Do not pass package bytes, raw source documents, long-lived tokens, or learner identities in browser redirects.

Only introduce a shared account or direct API when both products have stable identity, consent, retention, tenant-isolation, rate-limiting, and incident-response policies. The services should remain independently deployable.

### Scope for Digitwol

The first Digitwol assessment-conversion release should include job lifecycle, CSV/XLSX conversion, source-license attestation, item-preview/review, `.psmk` generation, and downloadable conversion receipts. QTI and Moodle XML/GIFT are the next structured adapters. PDF/OCR comes after the review workflow has a measured quality baseline. VCE remains limited to authorized, documented exports or licensed partner integrations.

## Proposed Laravel Design

Add the following only after the V1 schema and test fixture are approved:

```
app/Services/PassimarkPackage/PackageValidator.php
app/Services/PassimarkPackage/PackageImporter.php
app/Services/PassimarkPackage/PackageExporter.php
app/Services/PassimarkImport/SourceAdapter.php
app/Services/PassimarkImport/CsvSourceAdapter.php
app/Services/PassimarkImport/QtiSourceAdapter.php
app/Services/PassimarkImport/PdfDraftAdapter.php
app/Http/Controllers/PassimarkPackageController.php
app/Http/Requests/ImportPassimarkPackageRequest.php
app/Policies/PassimarkPackagePolicy.php
resources/js/Pages/Passimark/Admin/ContentImport.jsx
```

Add `external_id` UUID columns to sessions, exams, and questions. Add a `passimark_package_imports` audit table with uploader, original filename, package ID, checksum, result counts, status, error report, and timestamps. Do not overload the existing `importQuestions` endpoint: it accepts an in-memory array and has no archive validation, preview, authorization policy, or audit trail.

Routes should be separate, policy-protected endpoints:

```
POST /admin/passimark/packages/validate
POST /admin/passimark/packages/import
GET  /admin/passimark/modules/{session}/export
GET  /admin/passimark/exams/{exam}/export
POST /admin/passimark/source-imports
GET  /admin/passimark/source-imports/{batch}
POST /admin/passimark/source-imports/{batch}/publish
```

The CAT engine already consumes the three IRT values required by the format. It does not need to read packages at runtime. Packages are converted into normal relational records at import time, so exam delivery, progress gating, and attempts retain their existing behavior.

## Delivery Plan

### Phase A: Contract and fixtures

- Publish JSON Schema for `manifest.json` and `content.json`.
- Create valid and invalid sample `.psmk` fixtures in the test suite.
- Define IRT bounds and the compatibility/deprecation policy.

### Phase B: V1 author package

- Add external UUIDs and import-audit migration.
- Implement module export and validation-only upload.
- Implement transactional create-only import with a preview screen.
- Add feature tests for authorization, validation, round-trip fidelity, and archive attacks.

### Phase C: Controlled updates and media

- Add explicit update/duplicate conflict modes and checksum-based idempotency.
- Add media asset references, private storage, malware scanning where available, and quotas.
- Add examiner-facing import history and downloadable error reports.

### Phase D: Structured-source conversion

- Implement CSV/XLSX mapping, preview, and batch review.
- Implement the supported subset of IMS QTI, then Moodle XML/GIFT if demand justifies it.
- Add source provenance, uploader rights attestation, and reviewer decisions to every conversion batch.
- Add text-PDF drafting only after the review workflow and test corpus exist.

### Phase E: Ecosystem

- Publish the schema, examples, conformance fixtures, and CLI validator.
- Support `.psme` and `.psmm` aliases only if they improve user understanding without creating a second wire format.
- Evaluate IMS QTI import/export as an interoperability bridge; retain `.psmk` for Passimark-specific IRT/CAT and progression metadata.

## Out of Scope for V1

- Executable code, arbitrary HTML, external scripts, and untrusted template rendering.
- Student import/export of answer-key material.
- Attempt histories, user accounts, progress, grades, or approvals.
- Collaborative merge resolution.
- Claims of an external standard or compatibility with third-party platforms without adapters.

## Recommendation

Proceed, but treat `.psmk` as a product platform feature after the core exam delivery flow is stable. The correct first milestone is a schema plus fixture package and a module-only round-trip test, not a broad UI uploader. That proves the contract while keeping the work isolated from the active Sprint 1 dashboard and later Sprint 2 exam workflow.