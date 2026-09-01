# Passimark Product Specification

## Product vision
Passimark is a modular adaptive assessment and certification-prep platform. The CISSP example is just one curriculum module; the platform is designed to support many subject areas, exam pathways, and learning tracks.

## Product goal
Create a modern assessment SaaS that helps learners:
- progress through structured learning sessions
- take adaptive exams
- improve readiness over time
- unlock higher-level content only when mastery is demonstrated
- receive instructor approval or oversight when needed

## Target users
### Learner
- studies a domain or exam track
- completes sessions and attempts exercises
- wants adaptive, targeted question progression
- needs a structured path to mastery

### Instructor / admin
- reviews learner performance
- approves progression between stages
- manages course content and domains
- monitors readiness and certification progress

## Core user journeys
### Learner journey
1. Sign in to the platform.
2. View dashboard of available sessions and phases.
3. Begin a session in CAT, timed, or practice mode.
4. Answer questions and progress through adaptive difficulty.
5. Complete the exam or session.
6. Request approval if the process requires gating.
7. Unlock the next stage when approved or completed successfully.

### Instructor/admin journey
1. Review pending learner submissions.
2. View each learner’s performance and status.
3. Approve or reject advancement.
4. Unlock the next session or track.
5. Manage content and question sets.

## Core features
### Session catalog
- organized by phase, domain, and track
- clear sequencing and progression
- session metadata including title, description, domain, and difficulty

### Adaptive testing
- item selection is influenced by the learner’s estimated ability
- question difficulty changes based on prior responses
- answers alter the learner’s theta or ability estimate

### Exam modes
- CAT: adaptive exam flow
- Timed: time-constrained assessment
- Practice: learning mode with feedback and instant answer review

### Progress tracking
- scores per session
- attempts per learner
- readiness or mastery estimate
- status lifecycle across locked, open, in progress, completed, pending approval, approved

### Gating and approval workflow
- content unlocks only after completion or approval
- instructor reviews are part of the progression model
- next sessions remain locked until a valid transition occurs

### Domain-based curriculum
- each session belongs to a domain or curriculum area
- learning can be grouped by specialty, certification track, or knowledge area
- the system supports multiple categories, not only one exam type

## Product requirements implied by the codebase
- support multiple assessment categories and domains
- support role-based access for students and admins
- support session-by-session progression
- support adaptive testing using response and item parameters
- support instructor approval workflows
- store rich question metadata (difficulty, discrimination, guessing, domain, explanation, reference)
- support both static and adaptive learning modes

## Product positioning
Passimark is best positioned as:
- an adaptive assessment platform
- a certification-prep and mastery platform
- a learning engine for domain-based progression
- an instructor-enabled assessment system

The CISSP curriculum is a sample implementation, not the boundary of the product.

## Success metrics
The product should measure:
- session completion rate
- pass rate by domain and phase
- adaptive exam efficiency
- learner time-to-readiness
- approval turnaround time for instructors
- content coverage and mastery improvement

## MVP scope
The MVP should include:
- authentication and role access
- dashboard of available sessions
- exam modes
- CAT logic
- score and progress tracking
- instructor approval workflow
- seeded sample content

## Future product roadmap
Possible future extensions:
- multi-certification catalogs
- cohort analytics
- mastery heatmaps
- question-authoring workflows
- AI-assisted content creation
- reporting and benchmarking
- enterprise team and training dashboards

---

This document is based on the project structure and seeded curriculum in the repository, especially the README, routes, models, migrations, and seeders.
