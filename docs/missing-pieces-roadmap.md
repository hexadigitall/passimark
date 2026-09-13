# Passimark: Missing Pieces and Completion Roadmap

## 1. Executive summary
Passimark already has a strong concept, a sensible data model, and a real adaptive testing foundation. The main gap is not in the core idea; it is in the completeness of the product layer: full UI delivery, stronger operational quality, and broader product tooling.

An approved **v4.0 Worldwide specification (JAN 2026)** has been audited and distilled into [v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md). It expands the single-CISSP prototype into a 200-cert, 5-stage CAT platform and is now the primary forward scope (Sprints 5-8 in [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md)).

## 2. What is already working well
The project already includes:
- a structured session model
- a multi-mode exam system
- a domain-based curriculum architecture
- an adaptive CAT logic layer
- role-aware progression and approval flows
- seeded sample content and a credible assessment format

That means the application has a solid conceptual foundation and a real product engine behind it.

## 3. Highest-priority gaps
### 3.1 Full frontend completion — mostly done
Student dashboard, exam flow (timer, instructions gate, progress indicator, exit control), result/review screen, and admin decision screens are implemented and covered by feature tests (see `sprint-1-detailed-tasks.md`, `sprint-2-progress.md`, `sprint-3-progress.md`).

Still missing:
- Practice Lab, Study Recommendations, and a dedicated flagged-questions review screen from `app-sitemap.md`
- public marketing pages (Home/Features/Pricing)
- design tokens and a typography scale (Tailwind config is currently unmodified defaults)

### 3.2 State integrity and business logic enforcement
The database tracks states such as locked, open, in progress, pending approval, and approved, but those states must be enforced more rigorously in the app logic.

Missing work:
- hard validation against locked sessions
- consistent state transitions
- explicit approval workflow rules
- deterministic unlock logic for next-stage access

### 3.3 Real content authoring and management — partially done
Session/exam/question CRUD and validated bulk import exist in the admin workspace (see `sprint-3-progress.md`).

Still missing:
- certification track model — shipped in Sprint 4.1; the v4 catalog builds on it.
- tag taxonomy / domain management as a real data model — shipped in Sprint 4.2 (`passimark_tags` + pivots, `passimark:backfill-tags` command, admin Tags screen; legacy `domain`/`bloom_level` columns retained read-compat and deprecated). The v4 catalog models `domain` through these tags.
- real multi-cert content — shipped (proven) in Sprint 4.5: the Hexadigitall CISSP 45-day textbook is fully extracted into a committed bundle and seeded through the existing track/session/exam/question model ([cissp-prep-bundle.md](cissp-prep-bundle.md)). This validates the **bundle-per-cert** flow that v4 generalizes to 205 certs.
- the v4.0 Worldwide catalog — **shipped in Sprint 5**: `phase_type` session ladder (`cert|lesson|phase|domain|mock|final`), exam `time_minutes`/`is_final`/`irt_enabled`, question `correct_key`, track `region`; `WorldwidePassimarkCatalogSeeder` (17 flagship certs) + `Uniform205CatalogSeeder` (205-cert JSON → 2,870 sessions / 8,610 exams, spec §4b drift zero); phase-1-first-lesson gating + pass-gated auto-unlock. Remaining in `phase_type` scope: IRT **engine** enforcement (Sprint 6). See [worldwide-catalog-sprint-5-report.md](worldwide-catalog-sprint-5-report.md).
- content export and a content author review/approval workflow distinct from the learner approval workflow

### 3.3a CAT engine v4 (IRT 3PL)
The current `CatEngine` selects the nearest-difficulty question and terminates on a fixed rule. The v4 spec requires true adaptive intelligence:
- 3PL item model, MLE theta via Newton-Raphson (clamp [−3,3]), Fisher-information next-question selection
- per-cert `passTheta`/`passScoreScaled`, real adaptive cutoffs (e.g. NCLEX 75-145)
Planned in Sprint 6 (`sprint-5-v4-worldwide-catalog.md`).

### 3.4 Product analytics and reporting
The app tracks attempts and scores, but higher-level reporting is still limited.

Missing work:
- mastery trends by domain
- learner readiness reports
- item-level analytics
- cohort comparisons
- pass-rate and completion dashboards

### 3.5 Deployment and production readiness
The codebase is structured like an app, but it is not yet fully hardened for production deployment.

Missing work:
- deployment config review
- staging/production settings
- migration verification
- CI/CD pipeline
- environment-based secrets handling
- production-level security review

## 4. Product-level missing features
### Learning features
- learner feedback after completion
- recommendations based on weak domains (v4: dotted→solid mastery rings + weak-zone heatmaps, Sprint 7)
- retake policy handling
- mastery progression milestones
- completion certificates and recognition (v4: verifiable certificate with QR/credential ID, Sprint 8)

### Admin features
- cohort and organization management
- advanced learner filtering
- approval audit trails
- rules-based unlock logic
- report exports

### Enterprise features
- multi-tenant support
- vendor or organization-level management
- multiple assessment tracks
- team benchmarking dashboards

## 5. Operational quality gaps
### Testing
Needed:
- unit tests for CAT logic
- tests for permission enforcement
- route and controller validation tests
- UI smoke tests for key flows

### Monitoring and support
Needed:
- application logging
- error tracking
- performance metrics
- session health checks
- regression checks for scoring logic

## 6. Frontend completeness gap
The product concept is strong, but the actual exam UI and dashboard experience still need to be fully implemented. The current codebase includes design intent and scaffolded pages, but not yet the complete product interface described by the mockups and the product vision.

## 7. Data and platform expansion gaps
### Data management
The schema is strong, but real-world usage requires:
- better bulk import flows
- scheduled exam generation
- result export functionality
- detailed audit trails

### Platform expansion
The product is conceptually ready for multi-platform deployment, but the actual packaging path still needs to be defined for:
- Windows desktop (.exe / MSIX)
- macOS
- Linux
- Android APK / AAB
- iOS app packaging

## 8. Branding and asset standardization
The project already has logo and image assets, which is a strength. Favicon, apple-touch-icon, and web manifest icons are now wired into `resources/views/app.blade.php` and `public/manifest.json` (Sprint 4). Still needed:
- one final approved logo set (v4.0 approved source `photo6321858088250300469.jpeg`; target filenames `logo-final-*.png`, `icon-*.png`)
- `public/manifest.json` background must change `#ffffff` → `#0F172A` (brand `#1A9E2D` theme already correct) — tracked in Sprint 8.3
- standard usage across screens and packaging
- platform-specific icon sizes and variants for native packaging (Android mipmaps, iOS icon sets — deferred until native packaging work begins, see Epic 7 in `mvp-backlog.md`)

## 9. Recommended order of implementation
### Phase 1: Product core (Sprints 0-4 — done)
- complete learner dashboard
- complete exam flow
- complete admin approval workflow
- fix scoring and progress transitions
- tag taxonomy (4.2) — shipped; prerequisite for catalog scale now met
- real CISSP bundle content (4.5) — shipped; proves the extraction + bundle-per-cert pipeline the 205-cert catalog will reuse

### Phase 2: v4.0 Worldwide build (Sprints 5-8 — approved spec)
- worldwide catalog schema + seeder (5)
- CAT engine v4 IRT 3PL (6)
- Pearson VUE chrome + mastery dashboard (7)
- certificates + PWA + deployment (8)

### Phase 3: Product depth (Sprints 9-10)
- richer reporting and analytics
- stronger question management tools
- improved UX responsiveness

### Phase 4: Product expansion (Sprints 11-12)
- cross-platform packaging and distribution
- enterprise features
- multi-domain catalog support

## 10. Final conclusion
The project is conceptually strong and already contains a working adaptive exam framework. The remaining gap is not the idea itself; it is the completion of the product layer: polished experiences, better content tooling, stronger validation, analytics, and platform packaging.

This means the app is at a promising stage, but it still needs implementation work before being considered a finished, production-grade platform.

---

This document reflects the project’s current state, including the adaptive engine, content model, and the visible gaps in the user experience and deployment readiness.
