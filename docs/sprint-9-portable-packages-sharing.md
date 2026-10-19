# Sprint 9: Portable Packages & Sharing (.psmk / .psme / .psmm)

**Status:** Core (V1 author package) **done** — [sprint-9-report](sprint-9-portable-packages-sharing-report.md). Phase C upload/preview/import + duplicate modes are **shipped**; media/signatures, source adapters, and ecosystem steps remain phased below.
**Spec:** [passimark-file-format-rfc.md](passimark-file-format-rfc.md) (Decision adopted)
**Backlog:** Epic 4, "add `.psmk` import/export and import audit history"

## Why

A user who downloads a past-question single-exam file (PDF, VCE, QTI, CSV…) wants to open and practice it in Passimark, and to share Passimark-native content with other Passimark users on any device. Passimark therefore needs a portable, application-owned assessment package. One format family, one compatibility contract — extension is a presentation hint only.

## Format contract (format_version 1.0)

- `.psmk` = ZIP archive (manifest.json + content.json). Plain root files; v1 carries no assets or signatures.
- `.psme` (single exam) and `.psmm` (module) are convenience aliases of the same package — accepted by the same importer; `manifest.content_type` is authoritative.
- `manifest.json`: `format=passimark`, `format_version` (semver, major exchanged), `package_id` (UUID), `content_type ∈ module|exam|course|item-bank`, `title`, RFC3339 timestamps, `producer`.
- `content.json`:
  - module = one `PassimarkSession` + its exams + questions (single_choice only, exactly one correct choice, IRT 3PL bounds, taxonomy).
  - exam = one exam + its session context + questions.
  - course = ordered `modules[]` for a whole certification track.
- External UUIDs (`external_id`) on sessions/exams/questions; independent of DB ids. Learner histories and progress are never included or overwritten.

## Delivery phases

### Phase A — Contract and fixtures — done (Sprint 9)
- Validator: archive limits (file count / total / per-file), safe root paths only, manifest rules, per-type content rules, IRT bounds, UUID uniqueness, slug rules.
- Fixtures + tamper tests in `tests/Feature/PassimarkPackageTest.php` (8 tests / 73 assertions).

### Phase B — V1 author package — done (Sprint 9)
- Migration `2026_01_01_000007`: `external_id` on sessions/exams/questions + `passimark_package_imports` audit.
- `app/Services/PassimarkPackage/`: `PackageValidator`, `PackageExporter` (module/exam/course), `PackageImporter` (transactional, create-only, never opens locks or touches progress).
- `app/Support/PsmkZip.php`: dependency-free ZIP codec (zlib), standard bytes verified with system `tar` — packages open in any ZIP tool.
- CLI: `passimark:package:export` (—course/—module/—exam, —dest) and `passimark:package:import` (file, optional —cert_slug override).
- Aliases `.psme`/`.psmm` accepted (format-based, not extension-based).

### Phase C — Controlled updates and media (in progress)
- **Upload/validate/import HTTP endpoints — done.** `PassimarkPackageController` under the `role:instructor,admin` group: `GET /admin/packages` (screen), `POST /admin/packages/preview` (dry-run), `POST /admin/packages/import`. The `Passimark/PackageImport` screen walks upload → preview → import and is linked from the admin dashboard ("Packages").
- **Import preview (dry-run summary) — done.** `PackageImporter::preview()` returns validity, errors/warnings, checksum, target cert, item counts, and a duplicate flag without writing anything.
- **Duplicate conflict modes + checksum idempotency — done.** `PackageImporter::import(..., ['on_duplicate' => 'create'|'skip'|'fail'])` (default `create` preserves V1 behaviour; UI/CLI default `skip`; `fail` refuses and audits). CLI gained `--on-duplicate`. Covered by `tests/Feature/PackageImportHttpTest.php` (8 tests).
- **Still open in Phase C:** true update-by-`external_id` (overwrite semantics, not just duplicate handling); media asset references (`assets/`), private storage, malware scanning, quotas; signatures (`signatures/manifest.sha256`).

### Phase D — Structured-source conversion (after C)
- `passimark_import_batches` / `passimark_import_items` staging model + `SourceAdapter` contract.
- CSV/XLSX first (lossless); then IMS QTI / Moodle XML/GIFT; text-PDF drafting after the review workflow; VCE only through an authorized/partner export path.
- Mandatory source-license attestation + import audit for every non-.psmk conversion.

### Phase E — Ecosystem
- Publish schema + conformance fixtures + CLI validator; `.psme`/`.psmm` aliases finalized; QTI interop bridge evaluated.

## Exit criteria (Phase A+B)
- Module, exam, and course packages export/import with zero content drift — met (round-trip tests).
- Tampered packages are rejected with actionable messages (metadata, content, IRT, path traversal, duplicates) — met.
- Import is create-only inside a transaction, leaves progression untouched, and records an audit row — met.
- Packages are standard ZIPs verified outside the app — met (system `tar` reads them).
- Full PHPUnit suite stays green — met (36 tests / 6,733 assertions incl. this sprint).

## Out of scope for V1
Executable/HTML content; learner data; overwrite semantics (create-only); claim of an external standard.