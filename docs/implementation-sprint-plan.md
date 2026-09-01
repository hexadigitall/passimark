# Passimark Implementation Sprint Plan

## Objective
Translate the approved product and engineering backlog into a practical delivery sequence so the team can build Passimark in executable slices without losing the core adaptive assessment vision.

## Delivery approach
Use vertical slices rather than broad parallel feature development. Each sprint should deliver a working end-to-end capability that can be demonstrated and validated with real user behavior.

---

## Sprint 0: environment hardening and baseline execution
### Goal
Establish a stable application baseline and ensure the project can run cleanly for development and testing.

### Scope
- verify Laravel app boot and environment file configuration
- confirm database connection and migration health
- validate seeded sample data and default users
- verify Laravel auth is working
- test web routes and page rendering
- ensure frontend assets compile with Vite
- confirm logo and branding assets load correctly
- validate Git and branch workflow for development work

### Exit criteria
- local environment starts without fatal errors
- migrations and seeders run successfully
- login flow works for seeded users
- frontend app loads and renders base pages
- issue tracker or task board is ready for implementation work

### Definition of done
- developers can run the app locally on a clean environment
- core platform baseline is stable and predictable

---

## Sprint 1: student auth and dashboard foundation
### Goal
Complete the first user-facing layer for learners and establish the student app shell.

### Scope
- build login and logout flow
- create student dashboard layout
- add session list and status overview
- display learner progress cards and roadmap
- add profile or account summary section
- implement role-aware navigation for students
- connect dashboard data to the existing session and progress models

### Exit criteria
- a learner can log in and see a dashboard
- session data is visible and grouped logically
- the student app feels coherent and navigable

### Definition of done
- the student home experience is usable end-to-end
- the dashboard matches the intended premium UI direction

---

## Sprint 2: adaptive exam flow and result scoring
### Goal
Ship the core product value: the exam experience.

### Scope
- implement exam start screens and instruction flow
- connect CAT logic to actual question selection
- support timed exam mode
- support practice mode
- persist learner responses and attempt metadata
- calculate score, pass/fail, and mastery result
- create summary and results screen
- support retest and re-entry logic for valid attempts

### Exit criteria
- a learner can take at least one complete exam
- the engine selects and tracks questions based on adaptive logic
- score and result data are stored and displayed clearly

### Definition of done
- one business-critical exam flow works end-to-end
- the adaptive engine is demonstrably working with real data

---

## Sprint 3: admin approval and educator workflow
### Goal
Create the operational control layer for instructors and admins.

### Scope
- build admin dashboard overview
- add learner list and session status view
- create approval queue for completion requests
- implement approve/reject workflow with notes
- unlock next stage or session after approval
- handle rejection and locking states correctly
- secure admin routes and functions with role checks

### Exit criteria
- admins can review learner completion attempts
- approval decisions update progress and session access
- only allowed roles can trigger admin actions

### Definition of done
- the learner-to-admin progression loop is complete and enforceable
- the approval workflow is functionally trusted

---

## Sprint 4: content management and question library
### Goal
Give admins a reliable content workflow for creating and maintaining assessment material.

### Scope
- create session management CRUD screens
- create exam management CRUD screens
- create question management CRUD screens
- add bulk import process for questions
- support tags such as domain, phase, difficulty, and taxonomy
- support explanation and reference metadata
- validate question quality and correct-answer rules

### Exit criteria
- admins can add, edit, and remove sessions, exams, and questions
- imported questions are usable inside exam flows
- the system can handle multiple domains and tracks

### Definition of done
- content operations are stable enough to support real content growth
- the platform can be used beyond demo-only question sets

---

## Sprint 5: UX polish and responsive product quality
### Goal
Elevate the app from functional to polished and trustworthy.

### Scope
- refine dark theme design tokens and spacing
- standardize typography and component styling
- improve dashboard cards, exam panels, and tables
- fix responsive behavior for tablet and mobile sizes
- adjust navigation patterns for smaller screens
- improve access and focus states
- ensure exam UI remains readable and low-friction

### Exit criteria
- the app looks consistent across major screen sizes
- core flows remain usable on mobile and tablet
- design matches the project’s premium product direction

### Definition of done
- the product feels polished and coherent across the entire user journey

---

## Sprint 6: analytics, reporting, and domain mastery views
### Goal
Add operational insight and learning visibility.

### Scope
- learner performance summaries
- domain mastery breakdown
- session pass rates
- question-level performance reporting
- cohort comparisons and trend reporting
- basic dashboards for admin and coach roles

### Exit criteria
- admins can view meaningful learner progress data
- mastery and performance reporting is understandable and actionable

### Definition of done
- the platform provides clear performance insight beyond raw scores

---

## Sprint 7: platform packaging and distribution preparation
### Goal
Prepare Passimark for real-world deployment beyond the browser-only prototype.

### Scope
- finalize browser deployment plan
- prepare Windows desktop packaging path
- prepare Linux package path
- prepare Android APK/AAB signing and build path
- prepare macOS and iOS packaging preparation for later release
- create icon and splash asset variants
- test app behavior across target screen sizes and densities

### Exit criteria
- platform packaging strategy is documented and ready to implement
- app assets are aligned for distribution targets
- the product can move from web prototype to deployable product

### Definition of done
- the team has a realistic, executable distribution roadmap for each major platform

---

## Sprint 8: QA, security, and production hardening
### Goal
Move the app from a working demo to a stable deployment candidate.

### Scope
- add unit tests for adaptive logic and model behavior
- add route and permission tests
- add UI smoke tests for major flows
- validate security boundaries and role enforcement
- audit slow queries and high-traffic paths
- define release-check workflow and deployment pipeline
- review error handling and validation completeness

### Exit criteria
- build checks pass consistently
- no critical auth or admin bypass issues remain
- the app is ready for staging or pilot environment deployment

### Definition of done
- the platform is production-grade enough for staged rollout

---

## Recommended sprint order
1. Sprint 0
2. Sprint 1
3. Sprint 2
4. Sprint 3
5. Sprint 4
6. Sprint 5
7. Sprint 6
8. Sprint 7
9. Sprint 8

## Working principles
- keep the adaptive CAT engine at the center of product value
- do not build broad admin tooling before the learner exam loop works
- validate every sprint with a working feature demo
- maintain a premium, dark, exam-focused UI throughout
- treat packaging as a product readiness task, not a separate afterthought

## Execution roadmap
Starting with Sprint 0 and 1, concrete task lists and daily tracking are available in:
- **[docs/sprint-0-1-tasks.md](sprint-0-1-tasks.md)** — detailed task breakdown for Sprints 0 and 1
- **[docs/sprint-progress-tracker.md](sprint-progress-tracker.md)** — daily progress tracking and standup template

## Final delivery target
The project should reach a dependable MVP first, then expand into richer analytics, stronger content tools, and multi-platform deployment. The goal is to build a real product loop before widening scope.
