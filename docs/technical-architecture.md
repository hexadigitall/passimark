# Passimark Technical Architecture Readout

## 1. Overview
Passimark is a Laravel 11 application using Inertia and React, built around a modular adaptive assessment engine. It follows a conventional server-driven web application pattern with React-powered screens, a relational database, and a strong domain model for learning progression and evaluation.

## 2. Technology stack
### Backend
- PHP 8.2
- Laravel 11
- Composer dependency management
- Laravel auth and middleware-based authorization

### Frontend
- React 18
- Vite
- Inertia React integration
- Axios for client requests
- Tailwind-based styling direction

### Data layer
- Laravel Eloquent ORM
- relational DB model with MySQL/PostgreSQL-compatible schema design

## 3. Project structure
### Application layer
- app/Http/Controllers/PassimarkController.php
- app/Http/Controllers/PassimarkAdminController.php

### Domain models
- app/Models/PassimarkSession.php
- app/Models/PassimarkExam.php
- app/Models/PassimarkQuestion.php
- app/Models/PassimarkProgress.php
- app/Models/PassimarkAttempt.php
- app/Models/PassimarkAttemptAnswer.php

### Service layer
- app/Services/CatEngine.php

### Routes
- routes/web.php

### Database
- database/migrations/2026_01_01_000001_create_passimark_tables.php

### Seed and demo data
- database/seeders/PassimarkSeeder.php

### UI screens
- resources/js/Pages/Passimark/

## 4. Architectural pattern
The application uses a classic Laravel MVC pattern with Inertia:
- Laravel manages auth, routing, validation, and persistence
- React handles page rendering and interactive UI state
- Inertia passes data between backend and frontend without a separate backend API layer

This is appropriate for a product that needs a polished exam workflow and admin dashboard without needing a separate API-first SPA architecture from day one.

## 5. Core domain model
### Session
A session is an ordered learning unit in a phase or track. It includes:
- number
- phase
- title
- description
- domain
- pass score
- time limit
- question count

### Exam
An exam belongs to a session and supports multiple modes:
- cat
- timed
- practice

### Question
Each question contains:
- content
- options
- difficulty
- discrimination
- guessing
- domain
- bloom level
- explanation
- reference

### Progress
Track a learner’s per-session progression:
- status
- score
- ability theta
- attempts

### Attempt
Represents one learner attempt at a session or exam:
- user id
- session id
- exam id
- exam mode
- theta
- start and end timestamps
- score and pass/fail
- responses payload

### AttemptAnswer
Stores per-question response data:
- selected option
- correctness
- time spent

## 6. Core workflow
1. User signs in.
2. Dashboard loads session and progression data.
3. User starts a session or exam.
4. Attempt record is created.
5. CAT service chooses the next question.
6. User answers a question.
7. Response is saved.
8. Ability estimate is updated.
9. System determines whether to continue or terminate the exam.
10. Progress status is committed.
11. Instructor approval may unlock the next learning level.

## 7. Adaptive engine architecture
The adaptive engine is the core differentiation of the product. It sits in CatEngine and is designed to:
- select the next unanswered question
- learn from prior answers
- estimate current ability or theta
- determine exam termination conditions
- calculate final score and readiness state

This aligns with adaptive testing concepts and IRT-style item response logic, especially around:
- item difficulty
- item discrimination
- guessing probability

## 8. Authorization and security model
The route structure implies a role-aware design:
- authenticated learners access learner features
- instructor/admin users access admin routes
- authorization is enforced through Laravel middleware and route grouping

This is the correct foundation for a multi-role learning platform.

## 9. Data flow architecture
### Request flow
- a request hits a Laravel route
- controller loads related models
- validations and auth checks run
- progress and attempt records are updated
- Inertia returns page props or JSON responses
- React re-renders the relevant UI

### Adaptive evaluation flow
- answer is submitted
- question metadata is read
- current theta is updated
- remaining questions are filtered
- next question is selected
- system decides whether to terminate based on adaptive thresholds

## 10. Frontend status and maturity
The frontend exists under resources/js/Pages/Passimark, but the base project is still partially scaffolded. Several components note that the full CAT UI is implemented in external artifact previews or design mockups rather than the base app itself.

This indicates:
- the architecture is in place
- the user experience layer still needs completion
- the product is conceptually strong but not visually or functionally complete end-to-end yet

## 11. Why the architecture is scalable
The model is intentionally built to support broader use beyond one domain:
- sessions and domains are modular
- questions are not hardcoded to one subject area
- progress is tracked per learner, per session
- exam modes are reusable across different content sets
- instructor approval and unlocking patterns support staged learning paths

This makes the product suitable for:
- multi-track learning programs
- certification prep systems
- domain-based mastery ecosystems
- enterprise staff assessment environments

## 12. Strengths of the current architecture
- clear domain model and separation of concerns
- strong educational progression concept
- adaptive engine foundation already in place
- role-aware access design
- content-driven model that can support multiple domains
- good basis for a SaaS-style assessment product

## 13. Risks and design gaps
The key weaknesses are not in the core model but in product completion:
- UI still needs full implementation
- admin flows need final polish and deeper reporting
- question and curriculum management need stronger tooling
- analytics are still limited
- tests should be added around business logic and route-based rules

## 14. Recommended evolution path
### Phase 1: Product-base completion
- stabilize auth and roles
- complete learner dashboard and exam flow
- finish admin approval system
- validate scoring and progression logic

### Phase 2: Product maturity
- build question bank and import tools
- expand analytics and reporting
- improve dashboard and results views
- finalize responsive behavior

### Phase 3: Multi-platform expansion
- web app optimization
- desktop packaging for Windows/macOS/Linux
- mobile packaging for Android/iOS
- native UX adaptation by device class

## 15. Conclusion
The architecture is coherent and strong enough to support a scalable adaptive assessment platform. The core concepts are sound: domain-based sessions, multi-mode evaluations, adaptive question selection, role-based access, and sequence gating. The biggest remaining work is front-end completion, product-level polish, and platform expansion rather than rethinking the system structure itself.

---

This document reflects the current codebase structure and the product vision already encoded in the repository’s migrations, models, routes, and seed data.
