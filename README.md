
# Passimark v1.0 - Adaptive Exam Intelligence
Master Every Session. Unlock Your Certification.

Laravel 11 + Inertia + React CAT Simulator with Session Gating.

## Install
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve

Default logins:
admin@passimark.com / password (instructor)
student@passimark.com / password

## Structure
- 46 Sessions (Phase 1-4) seeded from CISSP textbook
- CAT Engine: IRT 3PL adaptive (theta, b, a, c)
- Gating: student completes -> pending_approval -> admin approves -> next unlocks
- Modes: cat (150Q adaptive), timed (180min), practice (instant feedback)

## Development

### For new developers
Start here:
1. [DEVELOPMENT.md](DEVELOPMENT.md) - local setup guide (see docs/ after Sprint 0)
2. [docs/technical-architecture.md](docs/technical-architecture.md) - how the app works
3. [docs/mvp-backlog.md](docs/mvp-backlog.md) - what we're building

### For implementation tasks
Current focus: **Sprint 0 & Sprint 1** (environment hardening and student dashboard)
- [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) - concrete task breakdown
- [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) - daily progress tracking
- [docs/implementation-sprint-plan.md](docs/implementation-sprint-plan.md) - full sprint roadmap

### For product context
- [docs/product-spec.md](docs/product-spec.md) - vision and features
- [docs/missing-pieces-roadmap.md](docs/missing-pieces-roadmap.md) - known gaps and roadmap
- [docs/full-implementation-plan.md](docs/full-implementation-plan.md) - detailed product and UX spec
- [docs/passimark-file-format-rfc.md](docs/passimark-file-format-rfc.md) - proposed portable `.psmk` assessment package

## Key Files
- database/seeders/PassimarkSeeder.php - 46 sessions + 500+ questions
- app/Services/CatEngine.php - adaptive logic
- resources/js/Pages/* - React Inertia UI (Pearson VUE style)
