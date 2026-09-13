# Getting Started: Passimark Development

⚠️ **PREREQUISITE:** Before starting, ensure you have PHP 8.2+, Composer, and Node.js installed.  
See [docs/S0-1-0-php-setup.md](docs/S0-1-0-php-setup.md) if you need help setting these up (takes 10-15 minutes).

## Quick Start for New Contributors

Welcome to Passimark! This guide will help you get up to speed and start contributing to the project.

### What is Passimark?
Passimark is a premium adaptive assessment platform built with Laravel 11, React, and Inertia. It helps learners progress through structured certification exams using intelligent question selection (CAT - Computerized Adaptive Testing) and instructor-approved progression gating.

### Key links
- **Source code:** https://github.com/hexadigitall/passimark
- **Active project:** D:\projects\passimark
- **Documentation:** See `docs/` folder

---

## Step 1: Understand the Project (30 min read)

1. **[docs/product-spec.md](docs/product-spec.md)** — What is Passimark? Who uses it? What problems does it solve?
   - Read this first to understand the product vision
   - 15 minutes

2. **[docs/technical-architecture.md](docs/technical-architecture.md)** — How is it built technically?
   - Understand the Laravel + React + Inertia stack
   - Learn about the CAT engine and session gating model
   - 15 minutes

3. **[README.md](README.md)** — Quick reference and default logins
   - 5 minutes

---

## Step 2: Get the Code Running (30-45 min setup)

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- npm or yarn
- MySQL/SQLite/PostgreSQL (as configured in .env)

### Local setup
```bash
# Clone the repo (if you haven't already)
git clone https://github.com/hexadigitall/passimark.git
cd passimark

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Set up database
# Edit .env to point to your local database
php artisan migrate --seed

# Start dev servers (in separate terminals)
php artisan serve                    # Laravel backend: http://localhost:8000
npm run dev                          # Frontend with Vite hot reload

# Default test logins
# Student: student@passimark.com / password
# Admin: admin@passimark.com / password
```

### Verify it's working
1. Open http://localhost:8000 in your browser
2. Log in with one of the default credentials above
3. You should see the dashboard with seeded sessions

---

## Step 3: Understand Current Work (5 min)

### Where are we in development?
Live status index: **[docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md)**.

Quick summary (as of the v4.0 audit):
- ✅ Sprints 0-3 done (environment, student auth/dashboard, exam flow, admin workflow)
- 🔶 Sprint 4 in progress — certification track model done; **tag taxonomy (4.2) is the next implementation task**, and the entry point to start coding
- ⏭️ Sprints 5-8 planned — v4.0 Worldwide catalog (approved JAN 2026 spec): see [docs/v4-worldwide-catalog-spec.md](docs/v4-worldwide-catalog-spec.md) and [docs/sprint-5-v4-worldwide-catalog.md](docs/sprint-5-v4-worldwide-catalog.md)

This means:
- ✅ Backend is scaffolded and functional
- ✅ Database schema and seeders are in place
- ✅ Dashboard, auth UI, exam flow, and admin workspace are built
- ⏳ Worldwide 205-cert catalog, IRT 3PL engine upgrade, Pearson VUE UI, and certificates remain

### What needs to be built?
See [docs/mvp-backlog.md](docs/mvp-backlog.md) for the full feature roadmap.

---

## Step 4: Pick a Task (10 min)

### If you're onboarding
Read [docs/v4-worldwide-catalog-spec.md](docs/v4-worldwide-catalog-spec.md) (approved build target) and [docs/sprint-4-taxonomy-plan.md](docs/sprint-4-taxonomy-plan.md) (next implementation sprint).

### If you're ready to code
Start with **Sprint 4.2 (tag taxonomy)** from [docs/sprint-4-taxonomy-plan.md](docs/sprint-4-taxonomy-plan.md):
- 4.2 tag model: migration, pivot tables, models, data backfill, admin CRUD
- 4.3 validation and integrity
- 4.4 backward compatibility

Each task is broken into sub-tasks that take 2-4 hours to complete.

---

## Step 5: Track Your Progress (Daily)

Update [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) as you complete tasks:
- Mark tasks as complete: `[ ]` → `[x]`
- Log blockers in the "Known Issues" section
- Update the summary table

### Daily standup template
```
Yesterday:
- [Task completed]
- [Task completed]

Today:
- [Task planned]
- [Task planned]

Blockers:
- [Any issues or dependencies]
```

---

## Step 6: Commit and Push Your Work

Use clear commit messages following this pattern:
```bash
git commit -m "Sprint 1: Add login page UI component"
git commit -m "Sprint 1: Implement student dashboard backend API"
git commit -m "Sprint 1: Fix responsive layout on mobile devices"
```

Push to feature branches:
```bash
git checkout -b feature/sprint-1-dashboard
git push origin feature/sprint-1-dashboard
```

---

## Reference Documentation

Keep these bookmarks handy while coding:

### Product & Planning
- [docs/full-implementation-plan.md](docs/full-implementation-plan.md) — Detailed product spec including UI, branding, and features
- [docs/missing-pieces-roadmap.md](docs/missing-pieces-roadmap.md) — Known gaps and future roadmap
- [docs/app-sitemap.md](docs/app-sitemap.md) — Screen and navigation blueprint

### Execution
- [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) — Current sprint tasks (START HERE for work items)
- [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) — Track daily progress
- [docs/implementation-sprint-plan.md](docs/implementation-sprint-plan.md) — Full 9-sprint roadmap

### Technical
- [docs/technical-architecture.md](docs/technical-architecture.md) — How it's built
- `app/Services/CatEngine.php` — Adaptive question selection logic
- `database/seeders/PassimarkSeeder.php` — Sample content and structure
- `resources/js/Pages/` — React components (currently scaffolded, not complete)

---

## Common Tasks

### I want to add a new page
1. Create React component in `resources/js/Pages/Passimark/`
2. Add route in `routes/web.php`
3. Add controller method in `app/Http/Controllers/PassimarkController.php`
4. Return Inertia response with props
5. Import component in route and pass props

### I want to update the database
1. Create migration: `php artisan make:migration add_new_field_to_table`
2. Edit migration in `database/migrations/`
3. Run migration: `php artisan migrate`
4. Update model in `app/Models/` if needed

### I want to style something
1. Use Tailwind CSS classes in React components
2. Check `tailwind.config.js` for available tokens
3. Review [docs/full-implementation-plan.md](docs/full-implementation-plan.md) **Section 6: Branding** for color and typography choices

### I hit an error
1. Check `storage/logs/laravel.log` for backend errors
2. Check browser console for frontend errors
3. Run `php artisan tinker` to debug queries and models
4. Review the task definition in sprint-0-1-tasks.md to verify prerequisites

---

## Next Steps

Sprints 0-3 are done. The queue ahead:
- **Sprint 4 (remaining):** tag taxonomy 4.2-4.4 — [docs/sprint-4-taxonomy-plan.md](docs/sprint-4-taxonomy-plan.md)
- **Sprint 5:** Worldwide catalog schema + seeder (17-cert + 205-cert JSON)
- **Sprint 6:** CAT engine v4 (IRT 3PL MLE + Fisher)
- **Sprint 7:** Pearson VUE exam chrome + mastery dashboard
- **Sprint 8:** Certificates + PWA + deployment
- **Sprints 9-12:** UX polish, analytics, packaging, QA

See [docs/implementation-sprint-plan.md](docs/implementation-sprint-plan.md) for the full roadmap.

---

## Questions?

- Read the **product docs** if you don't understand what we're building
- Read the **technical docs** if you don't understand how it's built
- Read the **task docs** if you don't know what to work on next
- Check **existing code** in the models, controllers, and seeders for patterns

## Let's go!

Start with [docs/sprint-4-taxonomy-plan.md](docs/sprint-4-taxonomy-plan.md) (4.2 tag taxonomy), set up your local environment, and begin building.

Welcome to Passimark! 🚀
