# Sprint 9 — Portable Packages & Sharing (.psmk) — Report

**Status:** Phase A+B done
**Branch:** `feature/sprint-9-psmk-packages`
**Spec:** [passimark-file-format-rfc.md](passimark-file-format-rfc.md) — Decision adopted
**Plan:** [sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md)
**Verification:** `vendor/bin/phpunit` → **OK (36 tests, 6,733 assertions)**; packages verified as standard ZIPs with the system `tar`; CLI export/import round-trip exercised on scratch SQLite.

---

## 1. What shipped

**The portable, app-owned assessment package Passimark needed:** one format family (`.psmk`) with `.psme` (single exam) and `.psmm` (module) as accepted conveniences of the *same* format — never three wire formats. This is what users open, copy, paste, and share across Passimark apps on any device, and the target format for converting PDF/VCE/QTI/CSV past-paper files.

### Format contract (format_version 1.0)
- `.psmk` = ZIP (manifest.json + content.json), plain root files, no assets/signatures yet.
- module = one session + its exams + single-choice questions (+ IRT 3PL + taxonomy + answer key).
- exam = one exam + its session context + questions.
- course = ordered modules for a whole certification track (e.g. the 17-cert flagship or uniform 205 ladder).
- External UUIDs on sessions/exams/questions; imports never include or touch learner history/progress.

### Code
| Piece | File |
|---|---|
| Dependency-free ZIP codec (standard ZIP bytes, zlib, no ext-zip) | `app/Support/PsmkZip.php` |
| Normative constants + helpers | `app/Services/PassimarkPackage/PackagingSpec.php` |
| Archive/manifest/content validator | `app/Services/PassimarkPackage/PackageValidator.php` |
| module/exam/course exporter | `app/Services/PassimarkPackage/PackageExporter.php` |
| Transactional create-only importer + audit | `app/Services/PassimarkPackage/PackageImporter.php` |
| Audit model | `app/Models/PassimarkPackageImport.php` |
| Migration: `external_id` (3 tables) + `passimark_package_imports` | `2026_01_01_000007_add_package_format_columns.php` |
| CLI export / import | `app/Console/Commands/PassimarkPackage{Export,Import}.php` |

### Commands
```
php artisan passimark:package:export --course=cissp --dest=out.psmk
php artisan passimark:package:export --module=42 --dest=out.psme      # same package, hint ext
php artisan passimark:package:export --exam=17 --dest=out.psmm
php artisan passimark:package:import out.psmk [--cert_slug=override]
```

## 2. Security / integrity posture (V1)
- Create-only import inside a DB transaction; imported sessions are always `is_open=false`; progression untouched; original rows never overwritten.
- Archive limits (5 files / 10 MB total / 4 MB per file), root-path whitelist, traversal rejection, CRC32 + size verification on every entry.
- Content rules: `single_choice` only, exactly one correct choice, 2-6 choices, IRT bounds (b [-3,3], a (0,3], c [0,0.99]), slug pattern, UUID uniqueness across the package, semver major gate on `format_version`.
- Every import (success or refusal-after-validation) leaves a `passimark_package_imports` audit row with package_id, sha256, content_type, result counts, and error report.

## 3. Verification evidence
- **Round-trips (module, exam, course)**: export → import → byte-for-byte content equality of prompt/choices/correct key/IRT/taxonomy + exam mode counts (tests).
- **Tamper rejection**: bad format name/version, missing files, unexpected entries, traversal paths, two correct choices, empty choices, IRT out of bounds, duplicate UUIDs, bad cert slug, bad package_id — each yields a targeted error (tests).
- **Aliases**: same package imported under `.psme` and `.psmm` names (tests).
- **Standard-ZIP proof**: `tar -tf interop.psmk` lists `manifest.json`/`content.json`; `tar -xOf` extracts valid JSON — any ZIP tool can open these files.
- **Outside-app CLI round-trip** on scratch SQLite: export module (1,067 B) → fresh-fresh import → track 1 / session 1 / exams 3 / audit 1 (command session was an empty-question structural session, so questions = 0 as expected).
- Full suite: 36 tests / 6,733 assertions green.

## 4. Remaining (Phase C-E)
- Instructor/admin upload UI + dry-run preview; policy-protected HTTP endpoints.
- Update-by-external_id & duplicate conflict modes; checksum idempotency.
- `assets/` media, signatures, storage quotas, malware scanning.
- Source adapters (CSV → QTI → PDF drafting) via a staging/review model; VCE only via authorized partner exports.
- Schema publication + conformance fixtures.

See [sprint-9-portable-packages-sharing.md](sprint-9-portable-packages-sharing.md) for the full phased plan.