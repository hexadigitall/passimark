# Passimark Technical Architecture Readout

## Overview
Passimark is a Laravel 11 application using Inertia and React, designed around a modular adaptive assessment engine. It follows a conventional server-rendered app pattern with client-side React pages and a strong relational database model.

## Stack
### Backend
- PHP 8.2
- Laravel 11
- Composer dependency management

### Frontend
- React 18
- Vite
- Inertia React bridge
- Axios for client requests

### Database
- Laravel-supported relational database (MySQL/PostgreSQL-compatible schema pattern)

## Project structure
### Business logic and controllers
- app/Http/Controllers/PassimarkController.php
- app/Http/Controllers/PassimarkAdminController.php

### Models
- app/Models/PassimarkSession.php
- app/Models/PassimarkExam.php
- app/Models/PassimarkQuestion.php
- app/Models/PassimarkProgress.php
- app/Models/PassimarkAttempt.php
- app/Models/PassimarkAttemptAnswer.php

### Service layer
- app/Services/CatEngine.php

### Web routes
- routes/web.php

### Database schema
- database/migrations/2026_01_01_000001_create_passimark_tables.php

### Seed data
- database/seeders/PassimarkSeeder.php

### UI pages
- resources/js/Pages/Passimark/

## Architectural pattern
This follows a classic Laravel MVC + Inertia design:
- Laravel handles routing, auth, validation, and persistence
- React renders the interactive interface
- Inertia passes data between Laravel and React without a separate API layer

This is well-suited for a SaaS app that needs a high-quality admin dashboard and exam experience without building a full separate frontend API.

## Main domain model
### Session
A session is an ordered learning unit, often part of a phase or track. It has:
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
Each question has:
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
Tracks per-user, per-session state and performance:
- status
- score
- ability theta
- attempts

### Attempt
A learner attempt records:
- user id
- session id
- exam id
- mode
- theta
- timestamp metadata
- score and pass/fail result
- response payload

### AttemptAnswer
Stores per-question responses and correctness data.

## Core business flow
1. User signs in.
2. Dashboard loads session and progress data.
3. User starts a session or exam.
4. Attempt is created.
5. CAT engine selects the next question.
6. Learner answers a question.
7. Response is stored.
8. Ability estimate is updated.
9. System decides whether to continue or terminate the exam.
10. Progress and unlock state are updated.
11. Instructor approval may unlock the next session.

## Adaptive engine design
The core logic is centered in CatEngine.

The service appears to do the following:
- choose the next unanswered question
- use answered questions to estimate learner performance
- use item parameters such as difficulty, discrimination, and guessing
- stop the exam once a termination condition is met
- calculate final score and readiness state

This is strongly aligned with IRT-style adaptive testing and 3PL logic concepts.

## Authorization design
Routes define access boundaries:
- authenticated users access learner features
- instructor/admin users access admin routes

This suggests a role-based permission model with middleware and route groups.

## Data flow architecture
### Request flow
- user request hits a Laravel route
- controller loads related models
- validation and auth checks run
- business logic updates progress and attempts
- Inertia returns page props or JSON responses
- React UI updates state and renders the next screen

### Adaptive evaluation flow
- learner answer arrives
- item parameters are read from the question
- current ability theta is updated
- next question is chosen from remaining session questions
- exam may terminate when enough information is gathered

## Frontend status and architectural observation
The frontend screens exist under resources/js/Pages/Passimark, but several files explicitly note that the full CAT UI is not implemented in the base app and is instead represented in mockups or artifact previews.

This indicates the architecture is mostly in place, while the presentation layer and user experience are still being finalized.

## Scalability characteristics
The current model is inherently scalable because it separates:
- content domains
- sessions and tracks
- exams and attempts
- progress records
- question metadata

That makes it a viable base for:
- multiple certifications
- multiple learning tracks
- enterprise training groups
- adaptive exam operations at scale

## strengths
- clear domain model
- role separation
- adaptive testing foundation
- content and session modularity
- strong data model for progression and performance tracking

## risks and considerations
- exam UI still needs full implementation
- progress state logic should be formalized in a state machine
- content import and authoring need stronger tooling
- analytics and reporting are not yet broad enough
- test coverage should be added for CAT logic and controller flows

## Conclusion
The architecture is coherent and well-structured for a scalable adaptive assessment SaaS product. The core ideas are solid: modular sessions, exam modes, adaptive intelligence, and role-based progression. The main work remaining is UX completion, operational polish, and product-level content management.
