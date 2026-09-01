# Development Guide - Passimark

## Quick Start for New Developers

### Prerequisites
- **PHP 8.2+** (with extensions: fileinfo, zip, pdo_sqlite)
- **Node.js 20+** and npm
- **Git** for version control
- **Composer** (PHP dependency manager)
- **SQLite3** (included with PHP)

### Setup Steps (5-10 minutes)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/hexadigitall/passimark.git
   cd passimark
   ```

2. **Create local environment file:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Install PHP dependencies:**
   ```bash
   composer install
   ```

4. **Set up the database:**
   ```bash
   php artisan migrate --seed
   ```
   *This creates the SQLite database and seeds with test data (46 sessions, 500+ questions, default users)*

5. **Install frontend dependencies:**
   ```bash
   npm install
   ```

6. **Start the development servers:**
   
   **Terminal 1 - Backend (Laravel):**
   ```bash
   php artisan serve
   ```
   Runs on `http://localhost:8000`

   **Terminal 2 - Frontend (Vite):**
   ```bash
   npm run dev
   ```
   Runs on `http://localhost:5173` with HMR (hot module reload)

7. **Access the application:**
   - Navigate to `http://localhost:8000`
   - Login with test credentials:
     - **Student:** `student@passimark.com` / `password`
     - **Admin:** `admin@passimark.com` / `password`

---

## Project Structure

```
passimark/
├── app/                          # Laravel application code
│   ├── Http/
│   │   ├── Controllers/          # Request handlers (PassimarkController, PassimarkAdminController)
│   │   ├── Middleware/           # Request/response middleware
│   │   └── Requests/             # Form validation
│   ├── Models/                   # Eloquent domain models
│   │   ├── PassimarkSession.php  # Session definition
│   │   ├── PassimarkExam.php     # Exam configuration
│   │   ├── PassimarkQuestion.php # Question item with IRT parameters
│   │   ├── PassimarkProgress.php # User progression tracking
│   │   ├── PassimarkAttempt.php  # Exam attempt record
│   │   └── PassimarkAttemptAnswer.php # Individual response
│   ├── Services/
│   │   └── CatEngine.php         # Computerized Adaptive Testing algorithm (IRT 3PL)
│   └── Providers/                # Service provider bootstrap
│
├── resources/
│   ├── js/
│   │   ├── Pages/Passimark/      # React page components
│   │   │   ├── Dashboard.jsx     # Student dashboard
│   │   │   ├── Exam.jsx          # Exam taking interface
│   │   │   └── Admin.jsx         # Admin panel
│   │   └── app.jsx               # React app entry point
│   ├── views/                    # Blade templates (minimal, mostly Inertia)
│   └── css/
│       └── app.css               # Tailwind CSS
│
├── database/
│   ├── migrations/               # Database schema definitions
│   │   └── 2026_01_01_000001_create_passimark_tables.php
│   └── seeders/
│       └── PassimarkSeeder.php   # Test data: users, sessions, questions
│
├── routes/
│   ├── web.php                   # Web routes (auth, dashboard, exams)
│   └── api.php                   # (Future) REST API routes
│
├── docs/                         # Project documentation
│   ├── product-spec.md           # Product vision and features
│   ├── technical-architecture.md # System design and tech stack
│   ├── mvp-backlog.md            # Feature prioritization (P0/P1/P2)
│   ├── implementation-sprint-plan.md # 9-sprint delivery roadmap
│   ├── sprint-0-1-tasks.md       # Granular task breakdown
│   └── sprint-progress-tracker.md # Daily standup template
│
├── tests/                        # Unit and feature tests
├── bootstrap/                    # Application bootstrap
├── config/                       # Configuration files
├── public/                       # Web root (assets, favicon)
├── vendor/                       # Composer dependencies (auto-generated)
├── node_modules/                 # npm dependencies (auto-generated)
├── .env                          # Environment configuration (development)
├── .env.example                  # Environment template
├── composer.json                 # PHP dependencies
├── package.json                  # Node.js dependencies
├── artisan                       # Laravel CLI entry point
└── vite.config.js                # Vite build configuration
```

---

## Git Workflow - Branch Strategy

### Branch Naming Convention

- **Main branches:**
  - `main` - Production-ready code (protected, PR required)
  - `develop` - Integration branch for features (PR required)

- **Feature branches:**
  - `feature/sprint-0-environment` - Sprint 0 environment setup
  - `feature/sprint-1-dashboard` - Sprint 1 student dashboard
  - `feature/S1.1-login-ui` - Individual task (for larger sprints)

### Typical Workflow

1. **Sync with latest develop:**
   ```bash
   git checkout develop
   git pull origin develop
   ```

2. **Create feature branch from develop:**
   ```bash
   git checkout -b feature/sprint-0-environment
   ```

3. **Work and commit:**
   ```bash
   git add .
   git commit -m "S0.1.1: Generate APP_KEY and create bootstrap structure"
   ```
   *Use task ID (S0.1.1, S1.2.3) in commit messages for traceability*

4. **Push and create pull request:**
   ```bash
   git push -u origin feature/sprint-0-environment
   ```
   Then open PR on GitHub, add description, link related tasks

5. **Merge after review:**
   - At least one approval required
   - All CI checks pass (tests, linting)
   - Merge to `develop`

6. **Promotion to main:**
   - At end of sprint, create PR from `develop` → `main`
   - Tag release: `git tag -a v0.1.0 -m "Sprint 0 completion"`

### Commit Message Guidelines

Format: `[TASK_ID] Brief description (max 50 chars)`

Examples:
```bash
git commit -m "S0.1.1: Generate APP_KEY"
git commit -m "S1.1.1: Build login form component"
git commit -m "S1.3.2: Fetch session list from backend"
git commit -m "S1.7.1: Apply Tailwind styling to dashboard"
```

---

## Common Development Tasks

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/AuthTest.php

# With coverage
php artisan test --coverage
```

### Database Commands

```bash
# Run migrations only
php artisan migrate

# Rollback last migration batch
php artisan migrate:rollback

# Reset database (DANGER - deletes all data)
php artisan migrate:refresh --seed

# Check migration status
php artisan migrate:status
```

### Frontend Commands

```bash
# Development server (watch mode + HMR)
npm run dev

# Build for production
npm run build

# Check for linting/formatting issues
npm run lint

# Format code
npm run format
```

### Laravel Artisan Utilities

```bash
# List all available routes
php artisan route:list

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Interact with code (REPL)
php artisan tinker
# Example: PassimarkSession::with('questions')->first()

# Generate new model, migration, controller
php artisan make:model PassimarkResource -mrc  # Model, Migration, Resource (API)
```

---

## Debugging

### Backend (Laravel/PHP)

**View logs:**
```bash
tail -f storage/logs/laravel.log
```

**Use debug bar (enabled by APP_DEBUG=true in .env):**
- Open http://localhost:8000 and look for debug toolbar at bottom of page

**Enable query logging in code:**
```php
// In controller
\DB::enableQueryLog();
$users = User::all();
dd(\DB::getQueryLog());
```

### Frontend (React)

**Check browser console:**
- Open DevTools (F12) → Console tab
- Look for React component errors or network errors

**Use React DevTools:**
- Install [React DevTools extension](https://react-devtools-tutorial.vercel.app/) for Chrome/Firefox
- Inspect component tree and state

**Debug Inertia page props:**
- Pass `?inertia-debug` to any URL to see props passed from backend:
  ```
  http://localhost:8000/dashboard?inertia-debug
  ```

### Network Issues (Backend ↔ Frontend)

**Check network tab in DevTools:**
- Open DevTools → Network tab
- Filter by XHR/Fetch
- Click requests to see payload and response

**Test API endpoints directly:**
```bash
curl http://localhost:8000/api/sessions
```

---

## Performance Tips

### Database
- Use `eager loading` with `with()` to avoid N+1 queries:
  ```php
  // ✓ Good - loads questions in one query
  $sessions = PassimarkSession::with('questions')->get();
  
  // ✗ Bad - N+1 query problem
  $sessions = PassimarkSession::all();
  foreach ($sessions as $session) {
      $questions = $session->questions; // Extra query per session
  }
  ```

### Frontend
- Use React.memo for expensive components
- Implement lazy loading for large lists
- Minimize re-renders with proper dependency arrays in useEffect

### Caching
- Enable query result caching:
  ```php
  $sessions = Cache::remember('sessions', 3600, function () {
      return PassimarkSession::all();
  });
  ```

---

## Deployment Checklist

Before pushing to production:
- [ ] All tests pass: `php artisan test`
- [ ] Frontend builds without errors: `npm run build`
- [ ] No console warnings in browser DevTools
- [ ] `.env` production values set (database, APP_KEY, etc.)
- [ ] Database migrations tested on production schema
- [ ] Analytics/monitoring configured
- [ ] SSL certificate valid
- [ ] Backup database before migrate

---

## Getting Help

1. **Check existing documentation:**
   - [GETTING_STARTED.md](GETTING_STARTED.md) - Initial project overview
   - [docs/product-spec.md](docs/product-spec.md) - What we're building
   - [docs/technical-architecture.md](docs/technical-architecture.md) - How it's built

2. **Check task tracking:**
   - [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) - Current sprint tasks
   - [docs/sprint-progress-tracker.md](docs/sprint-progress-tracker.md) - Daily progress

3. **Search codebase:**
   ```bash
   grep -r "specific term" app/ resources/
   ```

4. **Ask team members** in Slack/Discord or create GitHub Issues for blockers

---

## Environment Variables

Key `.env` settings for development:

```env
APP_NAME=Passimark
APP_ENV=local
APP_DEBUG=true                          # Enable debug mode (queries, errors)
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite                    # Use SQLite for development
DB_DATABASE=database/database.sqlite

MAIL_MAILER=log                        # Log emails to file instead of sending

LARAVEL_TINKER=true                    # Enable artisan tinker REPL

VITE_ASSET_URL=http://localhost:5173   # Frontend dev server
```

---

**Last Updated:** Sprint 0  
**Maintainer:** Development Team
