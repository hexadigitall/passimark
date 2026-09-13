# Passimark MVP Backlog

## Objective
Build a working, polished adaptive assessment platform with a complete learner flow, admin approval flow, and a realistic multi-platform product foundation.

## Delivery order
This backlog is grouped into completion phases. The project should not attempt all work at once; it should move in this sequence to keep the system coherent and testable.

---

## Epic 1: Foundation and environment
### Priority: P0
### Tasks
- validate Laravel app startup and environment config
- configure database connection and cache/session settings
- run migrations and seeders successfully
- confirm default auth flow works
- ensure admin/student roles exist and behave correctly
- confirm core routes and page loading behavior
- validate static assets and logo integration

### Definition of done
- app boots cleanly locally
- seeded users can be authenticated
- initial database state is correct
- no fatal startup issues in the base app

---

## Epic 2: Student learner flow
### Priority: P0
### Tasks
- build the real student dashboard UI
- display user progress and session roadmap
- support session launch and exam start flow
- implement CAT exam mode
- implement timed exam mode
- implement practice mode
- persist answers and response data
- calculate result score and overall pass/fail
- update learner progress after each attempt
- support retake flow and session re-entry rules

### Definition of done
- a student can log in and see available sessions
- a student can start and complete at least one valid session
- scores and progress are stored and displayed
- the exam flow works end-to-end for the MVP

---

## Epic 3: Admin approval and management flow
### Priority: P0
### Status: done — see sprint-3-progress.md
### Tasks
- build admin dashboard UI
- list learner completion requests
- display session and learner metadata in approval queue
- approve and reject learner submissions
- unlock next session or stage after approval
- lock content appropriately after rejection
- secure admin routes and actions behind role checks

### Definition of done
- an instructor/admin can view pending learner progress
- approval action changes learner state correctly
- learners are only unlocked when conditions are met

---

## Epic 4: Content and question management
### Priority: P0 / P1
### Status: core done — CRUD + bulk import shipped (sprint-3-progress.md); certification tracks (4.1) and tag taxonomy (4.2, `passimark_tags` + pivots + backfill command + admin Tags screen) shipped in sprint-4-taxonomy-plan.md; CISSP textbook bundle (4.5) extracted and seeded — 46 sessions / 980 MC items / 105 flashcards from the Hexadigitall 45-day textbook, content pipeline documented in cissp-prep-bundle.md; remaining items below (exports, review workflow, broader import formats) stay P1/P2. Epic 9 (v4.0 Worldwide catalog) now builds on the shipped tag model and the proven bundle-per-cert pipeline.
### Tasks
- build session CRUD screens
- build exam CRUD screens
- build question CRUD screens
- add question import flow
- add `.psmk` import/export and import audit history
- add CSV/XLSX import with draft review and source-license attestation
- plan QTI and Moodle XML/GIFT adapters; defer PDF/OCR and proprietary formats until review tooling is proven
- manage domains and curriculum categories
- support tagging by difficulty, domain, and taxonomy
- handle question explanation and reference metadata
- build sample and production data workflows

### Definition of done
- admins can add, edit, and delete sessions, exams, and questions
- imported questions are available in actual exams
- the system can support multi-domain content packages

---

## Epic 5: UX and responsive product polish
### Priority: P1
### Tasks
- finalize UI design tokens
- standardize typography, spacing, and colors
- finish dark-mode app styling and contrast
- refine dashboard cards and stats blocks
- polish the exam interface for readability and usability
- implement responsive layout for desktop, tablet, and mobile
- optimize touch interactions for mobile devices
- ensure keyboard access and accessibility basics

### Definition of done
- the product feels premium and consistent across screens
- the exam flow remains usable on smaller screens
- branding and UX match the mockups and product vision

---

## Epic 6: Analytics and reporting
### Priority: P1
### Tasks
- build learner progress analytics dashboard
- show mastery by domain and phase
- track attempts, completions, and pass rates
- provide item difficulty and performance overview
- support cohort or class-level reporting
- expose admin reporting views for completion and progress

### Definition of done
- admins can review learner performance trends
- the product has a measurable score and mastery view
- domain-level readiness is visible and interpretable

---

## Epic 7: Platform packaging and multi-device support
### Priority: P1 / P2
### Tasks
- prepare browser deployment strategy
- plan Windows desktop packaging (.exe and MSIX)
- plan Linux packaging (.deb, .rpm, AppImage)
- prepare Android APK and AAB build flow
- prepare macOS package strategy
- prepare iOS packaging path
- create final app icon and splash asset variants
- test layout behavior across screen sizes and densities

### Definition of done
- each target platform has a packaging path
- native screen sizing and design adaptation are planned
- branding assets are finalized for each install target

---

## Epic 8: QA, quality gates, and production hardening
### Priority: P1
### Tasks
- add unit tests for adaptive logic
- add integration tests for routes and progress transitions
- add UI smoke tests for core screens
- validate auth and role enforcement
- review security boundaries and permission checks
- add linting and build validation
- check performance bottlenecks and slow routes
- define release process and environment management

### Definition of done
- build and test checks pass reliably
- no critical auth or role bypass issues remain
- the app is stable enough for staging deployment

---

## Epic 9: v4.0 Worldwide certification catalog — approved JAN 2026 spec
### Priority: P0 (v4.0 build target)
### Status: schema + seeders + gating **shipped (Sprint 5)** — see [worldwide-catalog-sprint-5-report.md](worldwide-catalog-sprint-5-report.md); CAT engine v4, VUE chrome, and certificates remain (Sprints 6-8)
### Tasks
- session model gains `phase_type` (`cert|lesson|phase|domain|mock|final`), `cert_slug`, `theta_required`; exam gains `time_minutes`/`is_final`/`irt_enabled`; question gains `correct_key`/IRT field alignment
- wire `WorldwidePassimarkCatalogSeeder` (17 certs) + 205-cert catalog JSON ([worldwide-205-cert-catalog.json](worldwide-205-cert-catalog.json), ~2,870 sessions) — Lessons 25Q @UP → Phases 50-75Q @UP → Domains 75Q @UP+Pressure → Mocks 70/100/120% → Final 100% real spec
- CAT engine v4: IRT 3PL, MLE theta (Newton-Raphson), Fisher-information item selection, per-cert `passTheta`/`passScoreScaled`, real adaptive cutoffs (e.g. NCLEX 75-145)
- Pearson VUE exam chrome (timer, palette, flag/review, strike-through, calculator, break dialogs) and mastery dashboard (dotted→solid progress rings, theta trendline, weak-zone heatmaps)
- verifiable certificates: `Certificate.jsx`, credential ID `PMK-{CERT}-2026-{HEX}`, QR → verify.passimark.com, pass probability, blockchain-hash placeholder (Polygon/IPFS)
- PWA manifest brand compliance (`#0F172A` background) and deployment guide
### Definition of done
- a learner can take any shipped cert through the full CAT ladder and earn a verifiable credential
- seeded catalog counts match the v4 spec; full PHPUnit + production build green

---

## Prioritization summary
### P0: must-have for MVP
- auth and roles
- student dashboard
- exam flow
- result scoring
- admin approval flow
- session and question CRUD basics

### P1: strong product maturity
- responsive UI polish
- analytics and reports
- content management tooling
- QA and build quality

### P2: platform scale and distribution
- native packaging for desktop/mobile
- multi-domain catalog expansion (v4.0 Worldwide — now P0-approved, see Epic 9)
- enterprise features and cohort management

---

## Definition of done for MVP
The MVP is complete when a learner can:
- sign in
- view sessions and progress
- take an exam in an operational mode
- see score and results
- request approval when required
- and an admin can review and unlock progression.

This creates the minimum working loop for the platform and gives a reliable base for the next stage of growth.

---

## Engineering notes
- Keep the adaptive CAT engine as the product differentiator.
- Build the app in vertical slices rather than broad parallel work.
- Validate each completed milestone with real end-to-end behavior.
- Treat the mockups as the product direction, not the final implementation requirement.
