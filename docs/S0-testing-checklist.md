# Sprint 0 & 1 Testing Checklist

## Sprint 0: Environment Hardening (Current)

### S0.1: Environment & Configuration Setup ✅

#### S0.1.1: APP_KEY Generation ✅
- [x] Composer dependencies installed (74 packages)
- [x] PHP 8.2.33 available on system PATH
- [x] Laravel bootstrap files created (artisan, bootstrap/app.php, bootstrap/cache/)
- [x] `php artisan key:generate` executed successfully
- [x] `.env` file has valid APP_KEY (base64:...)
- [x] Git committed and pushed to GitHub

#### S0.1.2: Database Migrations & Seeding ⏳ (In Progress)
- [ ] `php artisan migrate --seed` completes without errors
- [ ] SQLite database file created at `database/database.sqlite`
- [ ] All 5 migration tables created:
  - [ ] `users` table exists with columns: id, name, email, password, role, created_at, updated_at
  - [ ] `passimark_sessions` table exists
  - [ ] `passimark_exams` table exists
  - [ ] `passimark_questions` table exists
  - [ ] `passimark_progress` table exists
- [ ] Test data seeded:
  - [ ] 1 admin user: `admin@passimark.com / password`
  - [ ] 1 student user: `student@passimark.com / password`
  - [ ] 46 sessions across 4 phases
  - [ ] 500+ CISSP-style questions with IRT parameters
- [ ] No foreign key constraint violations
- [ ] No duplicate key errors

#### S0.1.3: Frontend Build Assets 🚩 (Blocked on S0.1.2)
- [ ] `npm install` completes successfully
- [ ] React, Inertia, Tailwind, Vite installed
- [ ] No peer dependency warnings (only optional warnings OK)
- [ ] `npm run build` completes without errors
- [ ] Production bundle created in `public/build/`
- [ ] CSS and JS assets compile correctly

### S0.2: Authentication & Roles Verification 🚩 (Blocked on S0.1.2)

#### S0.2.1: Test Auth Flow
- [ ] Laravel dev server starts: `php artisan serve` → http://localhost:8000
- [ ] Login page accessible at `/login`
- [ ] Can submit login form with `student@passimark.com / password`
- [ ] Session created after successful login
- [ ] Redirected to `/dashboard` after login
- [ ] Can logout and return to login page
- [ ] Invalid credentials show error message
- [ ] Auth middleware blocks unauthenticated users from `/dashboard`

#### S0.2.2: Test Roles & Permissions
- [ ] `student@passimark.com` has `student` role
- [ ] `admin@passimark.com` has `admin` role
- [ ] Student can view `/dashboard` but cannot access `/admin`
- [ ] Admin can view both `/dashboard` and `/admin`
- [ ] Role-based middleware correctly enforces permissions
- [ ] Spatie laravel-permission package loaded correctly
- [ ] `auth()->user()->roles` returns role collection

### S0.3: Routes & Models Verification 🚩 (Blocked on S0.1.2)

#### S0.3.1: Test Web Routes
- [ ] `php artisan route:list` shows all routes without errors
- [ ] GET `/` redirects to `/dashboard` or `/login`
- [ ] GET `/login` renders login page (status 200)
- [ ] GET `/register` renders registration page
- [ ] POST `/login` processes credentials
- [ ] GET `/dashboard` shows student dashboard (authenticated users only)
- [ ] GET `/admin` shows admin panel (admin only)
- [ ] GET `/admin/sessions` lists all sessions
- [ ] GET `/admin/sessions/{id}` shows session details
- [ ] All routes return HTTP 200-302 (no 500 errors)

#### S0.3.2: Verify Model Relationships
- [ ] User → Sessions relationship loads (User has many Sessions)
- [ ] Session → Questions relationship loads (Session has many Questions)
- [ ] Session → Exams relationship loads (Session has many Exams)
- [ ] Question → IRT parameters load correctly (difficulty, discrimination, guessing)
- [ ] Progress → User relationship loads
- [ ] Progress → Session relationship loads
- [ ] Attempt → Answers relationship loads (eager loaded)
- [ ] No N+1 query problems when using `with()` eager loading

### S0.4: Git Workflow Setup ✅

#### S0.4.1: Repository Configuration
- [x] Git initialized and connected to `https://github.com/hexadigitall/passimark.git`
- [x] Initial commit with project files
- [x] `main` branch contains production-ready code
- [x] `develop` branch created for feature integration
- [x] `feature/sprint-0-environment` branch created for current sprint
- [x] `.gitignore` excludes vendor/, node_modules/, .env.local, logs

#### S0.4.2: Team Workflow Ready
- [x] Feature branch naming convention documented (feature/sprint-X, feature/TASK-ID)
- [x] Commit message format specified (use task IDs: S0.1.1, S1.2.3)
- [x] PR template prepared with description fields
- [x] Branch protection rules recommended (at least 1 approval before merge)
- [x] Instructions documented in DEVELOPMENT.md

### S0.5: Documentation & Logging Setup ✅

#### S0.5.1: Documentation Complete
- [x] [DEVELOPMENT.md](DEVELOPMENT.md) - Developer onboarding guide
  - Quick start setup (5-10 minutes)
  - Project structure explained
  - Git workflow documented
  - Common tasks and debugging
  - Performance tips
  - Deployment checklist
- [x] [GETTING_STARTED.md](GETTING_STARTED.md) - Project context and task selection
- [x] [docs/product-spec.md](docs/product-spec.md) - Product vision and features
- [x] [docs/technical-architecture.md](docs/technical-architecture.md) - System design
- [x] [docs/sprint-0-1-tasks.md](docs/sprint-0-1-tasks.md) - Task breakdown
- [x] [README.md](README.md) - Project quick reference

#### S0.5.2: Logging & Monitoring
- [ ] Laravel logs accessible at `storage/logs/laravel.log`
- [ ] Log rotation configured (no single huge log file)
- [ ] Debug bar visible when APP_DEBUG=true
- [ ] Query logging works: `DB::enableQueryLog()` + `dd(DB::getQueryLog())`
- [ ] Error pages render correctly (not blank white screen)
- [ ] CORS headers configured (if needed for frontend dev server)

---

## Sprint 1: Student Dashboard & Auth UI

### S1.1: Login Form UI Components

- [ ] Login form component created at `resources/js/Pages/Auth/Login.jsx`
- [ ] Email input field with validation
- [ ] Password input field (type="password")
- [ ] "Remember me" checkbox (optional)
- [ ] "Forgot password?" link (placeholder)
- [ ] Submit button with loading state
- [ ] Error message display for failed login
- [ ] Form styling with Tailwind dark theme
- [ ] Component responsive on mobile

### S1.2: Dashboard Layout & Navigation

- [ ] Main layout component created at `resources/js/Layouts/DashboardLayout.jsx`
- [ ] Top navigation bar with:
  - [ ] App logo/branding
  - [ ] User profile dropdown
  - [ ] Logout button
- [ ] Sidebar with navigation menu:
  - [ ] Dashboard link
  - [ ] Sessions link
  - [ ] Help/FAQ link
- [ ] Mobile-responsive hamburger menu
- [ ] Dark theme styling with emerald accents

### S1.3: Sessions List & Progress Display

- [ ] Sessions page component at `resources/js/Pages/Passimark/Sessions.jsx`
- [ ] Fetch sessions list from backend API
- [ ] Display session cards with:
  - [ ] Session title
  - [ ] Phase/domain information
  - [ ] Current progress percentage
  - [ ] Pass score requirement
  - [ ] Start exam button
  - [ ] Session status badge (not_started, in_progress, passed, pending_approval)
- [ ] Responsive grid layout (1 col mobile, 2 cols tablet, 3 cols desktop)

### S1.4: User Progress Visualization

- [ ] Progress page showing learner's overall advancement
- [ ] Session-level progress indicators (circles showing pass/fail)
- [ ] Question performance analytics
- [ ] Estimated ability level (theta) visualization
- [ ] Score history chart

### S1.5: User Profile & Settings

- [ ] Profile page with user information display
- [ ] Edit profile form (name, email)
- [ ] Password change form
- [ ] Account settings (notifications, preferences)

### S1.6: Backend API Integration

- [ ] Create API endpoints in `app/Http/Controllers/PassimarkController.php`:
  - [ ] `GET /api/sessions` - List user's sessions
  - [ ] `GET /api/sessions/{id}` - Get session details
  - [ ] `GET /api/progress` - Get user progress
  - [ ] `POST /api/sessions/{id}/start` - Start exam session
- [ ] Inertia shared data setup for authenticated user info
- [ ] CSRF token handling for forms

### S1.7: Styling & Theme

- [ ] Apply Tailwind CSS to all components
- [ ] Dark theme primary colors (slate/dark backgrounds)
- [ ] Emerald/teal accent colors for interactive elements
- [ ] Consistent spacing and typography
- [ ] Smooth transitions and hover effects
- [ ] Loading skeletons for async data

### S1.8: Testing & Validation

- [ ] All components render without errors
- [ ] Dashboard loads in < 2 seconds
- [ ] Mobile responsive on all device sizes
- [ ] Session list updates when backend data changes
- [ ] Form validation works for login
- [ ] Error handling for failed API calls
- [ ] No console warnings or errors in DevTools

---

## Blocking Conditions

### Cannot Proceed Until S0.1.2 Complete
- S0.1.3: Frontend build (needs database.sqlite existing)
- S0.2: Auth testing (needs users table seeded)
- S0.3: Routes testing (needs models working with DB)
- S1.x: All Sprint 1 tasks (needs complete environment)

### Current Status
**S0.1.2 Database Seeding:** ⏳ Running...
- Expected completion: 10-20 minutes after start
- Check `database/database.sqlite` file size increasing
- When complete: `storage/logs/laravel.log` will show final status

---

## Quick Verification Commands

After S0.1.2 complete, use these to verify each section:

```bash
# S0.1: Laravel working
php artisan --version  # Should show Laravel Framework 11.56.1

# S0.2: Database seeded
php artisan migrate:status  # Should show all migrations ✓ Ran
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM users;"  # Should return 2

# S0.3: Models working
php artisan make:model PassimarkTest --force  # Should create without errors

# S0.4: Git ready
git branch -a  # Should show main, develop, feature/sprint-0-environment
git log --oneline | head -3  # Should show commits

# S1: Frontend ready
npm run build  # Should complete in < 60 seconds
```

---

**Last Updated:** Sprint 0  
**Status:** In Progress ⏳
