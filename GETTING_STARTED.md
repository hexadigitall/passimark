# Getting Started: Passimark Development

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

Start with these in order:

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
We are in **Sprint 0: Environment Hardening and Sprint 1: Student Dashboard** phase.

This means:
- ✅ Backend is scaffolded and functional
- ✅ Database schema and seeders are in place
- ⚠️ Frontend dashboard and auth UI are NOT fully built yet
- ⚠️ Exam flow is NOT implemented yet

### What needs to be built?
See [docs/mvp-backlog.md](docs/mvp-backlog.md) for the full feature roadmap.

---

## Step 4: Pick a Task (10 min)

### If you're setting up your environment
Work through [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) **Sprint 0** section:
- Environment setup
- Database validation
- Auth testing
- Git workflow setup

### If you're ready to code
Work through [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) **Sprint 1** section:
- Sprint 1.1: Build login page UI
- Sprint 1.2: Dashboard layout and navigation
- Sprint 1.3: Session display
- Sprint 1.4: Progress visualization
- Sprint 1.5: Profile page
- Sprint 1.6: Backend API endpoints
- Sprint 1.7: Styling and responsiveness
- Sprint 1.8: Testing

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

## Next Steps After Sprint 1

Once the student dashboard is complete, we move to:
- **Sprint 2:** Adaptive exam flow and scoring
- **Sprint 3:** Admin approval workflow
- **Sprint 4:** Content management
- **Sprint 5:** UX polish and responsive design
- And so on...

See [docs/implementation-sprint-plan.md](docs/implementation-sprint-plan.md) for the full roadmap.

---

## Questions?

- Read the **product docs** if you don't understand what we're building
- Read the **technical docs** if you don't understand how it's built
- Read the **task docs** if you don't know what to work on next
- Check **existing code** in the models, controllers, and seeders for patterns

## Let's go!

Pick a task from [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md), set up your local environment, and start building.

Welcome to Passimark! 🚀
