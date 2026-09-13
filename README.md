
# Passimark v1.0 - Adaptive Exam Intelligence
Master Every Session. Unlock Your Certification.

Laravel 11 + Inertia + React CAT Simulator with Session Gating.

> **v4.0 Worldwide (approved JAN 2026):** the product is expanding to a 200+-cert global CAT platform. See [docs/v4-worldwide-catalog-spec.md](docs/v4-worldwide-catalog-spec.md) and [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) for scope and status. The code below still reflects v1 (single CISSP track).

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
Current focus: **Sprint 5 done (v4 Worldwide catalog — schema, 17-cert + 205-cert seeders, gating) → Sprint 6 (IRT 3PL CAT engine)**
- [docs/v4-worldwide-catalog-spec.md](docs/v4-worldwide-catalog-spec.md) - approved v4.0 build spec (205-cert global platform)
- [docs/sprint-4-taxonomy-plan.md](docs/sprint-4-taxonomy-plan.md) - next implementation sprint (4.2)
- [docs/sprint-5-v4-worldwide-catalog.md](docs/sprint-5-v4-worldwide-catalog.md) - Sprints 5-8 worldwide catalog breakdown
- [docs/cissp-prep-bundle.md](docs/cissp-prep-bundle.md) - CISSP textbook audit + extraction/seeding pipeline (Sprint 4.5)
- [docs/worldwide-catalog-sprint-5-report.md](docs/worldwide-catalog-sprint-5-report.md) - Sprint 5: v4 Worldwide schema + 17/205-cert seeders (verified counts)
- [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) - live status index
- [docs/implementation-sprint-plan.md](docs/implementation-sprint-plan.md) - full 13-sprint roadmap

### For product context
- [docs/product-spec.md](docs/product-spec.md) - vision and features
- [docs/missing-pieces-roadmap.md](docs/missing-pieces-roadmap.md) - known gaps and roadmap
- [docs/full-implementation-plan.md](docs/full-implementation-plan.md) - detailed product and UX spec
- [docs/passimark-file-format-rfc.md](docs/passimark-file-format-rfc.md) - proposed portable `.psmk` assessment package

## Key Files
- database/seeders/PassimarkSeeder.php - 46 sessions + 500+ questions
- app/Services/CatEngine.php - adaptive logic
- resources/js/Pages/* - React Inertia UI (Pearson VUE style)
