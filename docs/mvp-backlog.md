# Passimark MVP Backlog

## Objective
Turn the current concept into a usable, testable, polished adaptive assessment app with a complete learner flow and a working admin approval workflow.

## Milestone 1: Foundation and setup
### Tasks
- verify Laravel app boots successfully
- configure environment and database connection
- run migrations and seeders
- confirm auth system works
- confirm role setup for student and instructor/admin
- verify dashboard routes and controllers are reachable
- ensure all assets load correctly

### Acceptance criteria
- app runs locally with no fatal errors
- default seeded users can log in
- database tables are created and populated
- dashboard route loads with session data

---

## Milestone 2: Core learner flow
### Tasks
- build the real student dashboard UI
- add session listing and progress display
- implement start exam flow
- support CAT exam progression
- support timed exam mode
- support practice mode
- store answer responses correctly
- calculate score and pass/fail results
- update progress state after completion

### Acceptance criteria
- a learner can access and start a session
- answers are persisted and scored
- progress status updates correctly
- at least one full session flow works end-to-end

---

## Milestone 3: Admin and approval workflow
### Tasks
- build admin dashboard UI
- list pending learner submissions
- review learner session completion
- approve or reject completion
- unlock next session correctly
- restrict instructor-only routes and actions

### Acceptance criteria
- admin can review learner completion requests
- approval unlocks the next session
- reject path keeps the learner locked

---

## Milestone 4: Content management
### Tasks
- build session CRUD screens
- build exam CRUD screens
- build question CRUD screens
- add bulk question import flow
- add domain, difficulty, and taxonomy management
- support sample and production question sets

### Acceptance criteria
- admin can create and edit sessions, exams, and questions
- imported or created questions are available in exam flows
- the system can support multiple domains and certification tracks

---

## Milestone 5: UX polish and responsiveness
### Tasks
- finalize design token system
- standardize colors, typography, spacing
- finish dashboard styling
- improve exam UI layout
- implement responsive behavior for desktop, tablet, and mobile
- optimize navigation and action buttons for touch
- verify dark theme consistency and accessibility

### Acceptance criteria
- pages render cleanly across sizes
- exam flow remains usable on phones and tablets
- UI matches product branding and mockups closely

---

## Milestone 6: Native and multi-platform packaging
### Tasks
- package web app for browser deployment
- prepare native Windows packaging (.exe / MSIX)
- prepare Linux packaging (.deb / .rpm / AppImage)
- prepare Android APK and AAB builds
- prepare macOS app packaging
- prepare iOS build pipeline
- standardize icon and splash assets for each platform

### Acceptance criteria
- each platform has a packaging strategy and build path
- assets are correctly sized for each target platform
- app feels native on each device class

---

## Milestone 7: Analytics and reporting
### Tasks
- dashboard analytics for learner performance
- domain mastery summary
- question performance and difficulty tracking
- cohort benchmarking
- reporting for admin review

### Acceptance criteria
- admin can view learner performance trends
- key metrics are visible in dashboard widgets
- domain-level progress is understandable

---

## Milestone 8: Production hardening
### Tasks
- add automated tests
- verify auth, route, and model behavior
- add linting and build validation
- improve error handling and validation
- audit security permissions
- set up environment management and deployment pipeline

### Acceptance criteria
- project passes build and test checks
- release workflow is repeatable
- app is stable enough for staging and deployment

---

## Prioritization
### P0 (must ship for MVP)
- login/auth
- learner dashboard
- exam flow
- admin approvals
- progress tracking
- session and question CRUD

### P1 (important for adoption)
- analytics
- responsive UX polish
- question import
- multi-domain content support

### P2 (platform expansion)
- native desktop/mobile packaging
- enterprise admin tooling
- deeper reporting and benchmarking

## Definition of done for MVP
The MVP is complete when a learner can:
- log in
- view sessions
- take an exam in at least one mode
- see results
- trigger the approval flow
- and an admin can unlock progression.

This creates a working product loop and foundation for expansion.
