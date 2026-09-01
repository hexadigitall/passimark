# Passimark Product Specification

## 1. Product vision
Passimark is a modular adaptive assessment and certification-prep platform. The current CISSP-style curriculum is only one example of how the system can be configured for a real-world domain or certification track. The product is intended to serve as a reusable assessment engine for learning, mastery, and exam-readiness across many subject areas.

## 2. Product mission
Help learners progress from uncertainty to readiness through adaptive testing, structured learning paths, and instructor-guided progression.

## 3. Problems the product solves
- learners often study static content without knowing where they are weak
- exam prep platforms rarely adapt to real-time ability or mastery signals
- certification paths can feel disconnected and non-sequential
- instructors need visibility into readiness and progression without manual tracking
- many assessment systems are focused only on tests, not on learning progression

## 4. Product strategy
Passimark combines three elements:
- adaptive assessment intelligence
- structured session progression
- instructor or admin oversight and gating

This makes it more than a quiz tool. It becomes a readiness platform for exam prep, skill acquisition, and assessment-driven learning.

## 5. Target users
### Learner / student
- wants a structured path to mastery
- needs feedback on where they are weak
- prefers a realistic exam experience
- wants progress to unlock the next stage of learning

### Instructor / admin
- needs to review outcomes and manage progression
- wants to approve learner advancement
- needs to manage content and exam structure
- wants visibility into performance and completion trends

### Content manager / author
- manages domains, sessions, and question banks
- imports assessments and keeps content current
- tags questions by domain, taxonomy, and difficulty

## 6. Primary user journeys
### Learner journey
1. Log in to the platform.
2. Open the dashboard and review available sessions and phases.
3. Start a session in CAT, timed, or practice mode.
4. Answer questions in a realistic assessment interface.
5. See results or explanations depending on mode.
6. Request approval if a gate requires it.
7. Unlock the next session or learning stage after completion or approval.

### Instructor journey
1. Review pending learner progress.
2. See sessions and scores by learner.
3. Approve or reject completion.
4. Unlock the next content item or tier.
5. Manage imported or authored content.

## 7. Core product features
### 7.1 Session catalog
- sessions are grouped by phase and domain
- progression is ordered and intentional
- learners see a roadmap instead of isolated test attempts

### 7.2 Adaptive exam engine
- question difficulty adjusts based on prior answers
- each learner’s ability estimate evolves over time
- the platform can select stronger or weaker questions based on readiness

### 7.3 Multiple exam modes
- CAT mode: adaptive exam flow
- timed mode: fixed-duration exam
- practice mode: learning-focused feedback loop

### 7.4 Progress tracking
- per-session score and attempt history
- ability estimate or theta-based readiness tracking
- status tracking including locked, open, in progress, completed, pending approval, approved

### 7.5 Gating and approval workflow
- a learner cannot access the next stage without satisfying progression rules
- instructor approval is part of the pathway for structured learning flow

### 7.6 Domain-based curriculum
- content can be mapped to different specialties or certifications
- domains can be organized by knowledge area, learning track, or certification objective

### 7.7 Question metadata model
Each question can include:
- content and answer options
- difficulty and discrimination
- guessing parameter
- domain label
- bloom level
- explanation and reference

## 8. Functional requirements
### Student-facing requirements
- login and session access
- dashboard overview of current progress
- start / resume exam flow
- answer recording and question navigation
- practice and timed exam modes
- completion and result summary
- request for approval where required

### Instructor-facing requirements
- learner management screen
- pending approval queue
- approve / reject actions
- session unlock workflow
- content management screen
- reporting on completion and readiness

### Platform-facing requirements
- role-based access control
- multi-domain content and session support
- modular assessment creation
- exam / question import support
- strong audit trail for progress and approval events

## 9. Product positioning
Passimark is positioned as:
- an adaptive assessment platform
- a certification-prep and mastery system
- a learning engine for domain-based progression
- an instructor-enabled assessment ecosystem

It is not only a CISSP prep tool; it is a reusable platform for adaptive assessment across many domains.

## 10. USP statements
- Adaptive intelligence for exam readiness
- Structured progression from baseline to mastery
- Certification-style experiences with learning discipline
- Instructor-backed unlock paths for real accountability
- A platform that can scale from one domain to many

## 11. MVP scope
The MVP should include:
- secure user authentication and role management
- learner dashboard and session overview
- at least one working adaptive exam flow
- practice and timed exam mode support
- score and progress updates
- instructor approval / session unlock flow
- sample seeded curriculum content

## 12. Non-functional requirements
### Reliability
- session states must be consistent and auditable
- exam result logic must be deterministic and explainable

### Performance
- screens should render quickly, especially dashboards and exam flows
- question selection and scoring should remain responsive during interactive use

### Security
- role-based access must be enforced server-side
- learner progress and results must not be visible outside authorized access

### Usability
- exam screens must be clear and distraction-light
- progress and unlock states must be explicit and easy to understand

## 13. Success metrics
The system should track:
- session completion rate
- pass rate by domain and phase
- exam completion time
- readiness trend over attempts
- learner progression speed
- approval turnaround time for instructors
- content coverage and mastery improvement

## 14. Long-term roadmap
Possible future expansions:
- multi-certification catalog support
- cohort and institution management
- advanced analytics and dashboards
- AI-assisted question generation or review
- content authoring workflows
- enterprise reporting and benchmarking
- mobile and desktop native packaging

## 15. Product summary
Passimark is a reusable adaptive assessment platform designed to deliver structured learning progression, personalized readiness, and instructor oversight. Its architecture supports many domains and exam tracks, while its seeded CISSP example demonstrates how the platform can be configured for real certification preparation.

---

This document reflects the current product direction, project structure, seeded sample curriculum, and the adaptive assessment logic already present in the repository.
