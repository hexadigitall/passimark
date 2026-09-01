# Sprint 0 & 1 Concrete Task List

## Sprint 0: Environment Hardening and Baseline Execution

### S0.1: Environment and Configuration Setup
#### S0.1.1 Validate Laravel startup and environment
- [ ] Copy `.env.example` to `.env` with proper database configuration
- [ ] Generate APP_KEY with `php artisan key:generate`
- [ ] Verify `config/app.php`, `config/database.php`, and `config/cache.php` are set correctly
- [ ] Test app boot: `php artisan serve` and verify no fatal errors
- [ ] Check `storage/` and `bootstrap/cache/` directories are writable
- **Definition of done:** App boots cleanly and serves HTTP 200 on http://localhost:8000/

#### S0.1.2 Database setup and migrations
- [ ] Ensure database (MySQL/SQLite/PostgreSQL) is configured and accessible
- [ ] Run `php artisan migrate --seed`
- [ ] Verify all tables exist: `passimark_sessions`, `passimark_exams`, `passimark_questions`, `passimark_progress`, `passimark_attempts`, `passimark_attempt_answers`
- [ ] Confirm seeded data is present (46 sessions, sample questions, default users)
- [ ] Run `php artisan tinker` and query `User::count()` to verify seeded users exist
- **Definition of done:** Database is populated and queryable; no migration errors

#### S0.1.3 Frontend build and asset compilation
- [ ] Install npm dependencies: `npm install`
- [ ] Run Vite development server: `npm run dev`
- [ ] Verify no build errors in console
- [ ] Check that JS and CSS assets compile correctly
- [ ] Confirm logo and branding assets load from `public/images/` and `resources/images/`
- **Definition of done:** Frontend build completes without errors; assets are accessible

### S0.2: Authentication and Role Verification
#### S0.2.1 Test auth flow
- [ ] Attempt login with seeded student credentials (from seeder)
- [ ] Attempt login with seeded admin/instructor credentials
- [ ] Verify session is created and user is authenticated
- [ ] Test logout flow and session termination
- [ ] Verify `auth()` helper and `Auth::user()` work in controllers
- **Definition of done:** Login/logout cycle works for both student and admin roles

#### S0.2.2 Validate role and permission structure
- [ ] Confirm `roles` and `permissions` tables exist (via Spatie permission)
- [ ] Check that users have assigned roles (student, instructor, admin, super_admin)
- [ ] Test `$user->hasRole('student')` and `$user->can('...')` logic
- [ ] Verify middleware like `role:student` and `role:admin` are configured
- **Definition of done:** Role checks are functional; auth middleware guards work

### S0.3: Core Route and Controller Verification
#### S0.3.1 Test web routes
- [ ] Verify `/login` renders and is accessible
- [ ] Verify `/register` (if enabled) is accessible
- [ ] Verify protected student routes require auth (e.g., `/dashboard`)
- [ ] Verify admin routes require admin role
- [ ] Check that Inertia routes render with React components
- [ ] Test that route parameters pass correctly to controllers
- **Definition of done:** All core routes load without errors; protected routes enforce auth

#### S0.3.2 Verify model relationships
- [ ] Test `User::with('sessions', 'progress')` and similar eager loads
- [ ] Verify `PassimarkSession` and `PassimarkExam` relationships are correct
- [ ] Confirm `PassimarkQuestion` belongs to exams
- [ ] Verify `PassimarkProgress` and `PassimarkAttempt` track user/session/attempt state
- [ ] Run `php artisan tinker` and query a few relationships to confirm data flow
- **Definition of done:** Models load related data correctly; no N+1 or circular reference issues

### S0.4: Git and Workflow Setup
#### S0.4.1 Confirm Git repository state
- [ ] Verify remote is set to `https://github.com/hexadigitall/passimark.git`
- [ ] Verify branch is `main`
- [ ] Check that `.gitignore` excludes `.env`, `node_modules/`, and build artifacts
- [ ] Confirm all necessary files are committed (no untracked critical files)
- **Definition of done:** Git is ready for team development; remote and branch are correct

#### S0.4.2 Create development branch structure
- [ ] Create a `dev` branch from `main` for integration work
- [ ] Create `feature/sprint-0` branch for environment work
- [ ] Document branch naming convention: `feature/sprint-N-description` or `bugfix/issue-description`
- [ ] Push branches to remote
- **Definition of done:** Team has a clear branching strategy for upcoming sprints

### S0.5: Baseline Documentation and Task Tracking
#### S0.5.1 Create Sprint 0 completion checklist
- [ ] Create a `docs/SPRINT_0_PROGRESS.md` file to track tasks
- [ ] List all S0.1 through S0.5 tasks with checkbox status
- [ ] Update as tasks are completed
- **Definition of done:** Team can track Sprint 0 progress in real time

#### S0.5.2 Document local development setup
- [ ] Create a `DEVELOPMENT.md` guide in root with:
  - Steps to set up local environment
  - How to run migrations and seeders
  - How to start Laravel dev server
  - How to run Vite dev server
  - How to connect to database
  - Common troubleshooting steps
- [ ] Reference `.env.example` for required config keys
- **Definition of done:** New developers can set up the project without extensive help

#### S0.5.3 Establish logging and debugging baseline
- [ ] Verify `storage/logs/laravel.log` is writable
- [ ] Test logging: `Log::info('Test message')` and verify it appears in logs
- [ ] Configure `APP_DEBUG=true` for local development
- [ ] Document how to view logs for debugging
- **Definition of done:** Team has a standard way to investigate runtime issues

---

## Sprint 1: Student Auth and Dashboard Foundation

### S1.1: Student Login and Auth UI
#### S1.1.1 Build login page UI
- [ ] Create React component `resources/js/Pages/Auth/Login.jsx`
- [ ] Add email input field
- [ ] Add password input field
- [ ] Add "Remember me" checkbox
- [ ] Add "Forgot password" link
- [ ] Add submit button
- [ ] Style with Tailwind CSS dark theme
- [ ] Add form validation and error display
- **Definition of done:** Login page renders with all fields; styling matches dark theme mockup

#### S1.1.2 Test login flow end-to-end
- [ ] Submit login form with valid seeded credentials
- [ ] Verify `POST /login` is called with correct data
- [ ] Verify authentication succeeds and redirects to dashboard
- [ ] Test with invalid credentials and verify error message
- [ ] Verify "Remember me" sets correct session/cookie behavior
- **Definition of done:** Login form works end-to-end; errors are clear and helpful

#### S1.1.3 Build logout flow
- [ ] Add logout button/link to header or profile menu
- [ ] Implement `POST /logout` endpoint if not present
- [ ] Verify logout clears session and redirects to login
- [ ] Test logout from all major pages
- **Definition of done:** Users can log out cleanly from any page

### S1.2: Student Dashboard Layout and Navigation
#### S1.2.1 Create main dashboard layout component
- [ ] Create React layout component `resources/js/Layouts/StudentLayout.jsx` with:
  - Top navigation bar with logo and user menu
  - Left sidebar with navigation links
  - Main content area for page content
  - Responsive toggle for mobile nav
- [ ] Style with dark theme and green/teal accents
- [ ] Add Passimark logo and branding to header
- **Definition of done:** Layout renders correctly on desktop; navigation is accessible

#### S1.2.2 Build sidebar navigation for student
- [ ] Add "Dashboard" link
- [ ] Add "My Sessions" link
- [ ] Add "Active Exams" link (if applicable)
- [ ] Add "Practice Lab" link (if applicable)
- [ ] Add "Results" link
- [ ] Add "Progress" link
- [ ] Add "Profile Settings" link
- [ ] Highlight active page link
- [ ] Collapse on mobile with hamburger menu
- **Definition of done:** All navigation links present and functional

#### S1.2.3 Build top navigation bar
- [ ] Add Passimark logo on left
- [ ] Add user greeting or name on right
- [ ] Add user profile dropdown menu
- [ ] Add logout option in dropdown
- [ ] Style with dark background and good contrast
- **Definition of done:** Header is visible, branded, and functional on all pages

### S1.3: Dashboard Overview and Session Display
#### S1.3.1 Create dashboard main page component
- [ ] Create `resources/js/Pages/Passimark/Dashboard.jsx`
- [ ] Receive `sessions`, `progress`, and `userProgress` props from controller
- [ ] Display welcome message with user name
- [ ] Show current phase or stage
- [ ] Add summary stats (sessions completed, overall progress, mastery %)
- [ ] Style stat cards with Tailwind CSS
- **Definition of done:** Dashboard displays user-specific data and feels welcoming

#### S1.3.2 Build session list display
- [ ] Create session card component showing:
  - Session title
  - Domain or category
  - Phase (e.g., "Phase 1 - Foundations")
  - Pass score requirement
  - Current status (locked, available, in-progress, completed)
  - Progress percentage or bar
  - Action button (Start, Resume, View Results, or Locked)
- [ ] Display sessions in order
- [ ] Sort or group by phase
- [ ] Add visual indicators for locked vs. available sessions
- **Definition of done:** Sessions are visible, grouped logically, and show status clearly

#### S1.3.3 Implement session filtering and search
- [ ] Add filter by status (available, completed, locked, in-progress)
- [ ] Add search by session title or domain
- [ ] Filter sessions based on user's progress state
- [ ] Update display when filters change
- **Definition of done:** Users can find sessions quickly using filters

### S1.4: User Progress and Roadmap Visualization
#### S1.4.1 Build progress overview
- [ ] Create progress component showing mastery by domain
- [ ] Display overall completion percentage
- [ ] Show phase progression (e.g., "3 of 5 phases complete")
- [ ] Create a progress bar or ring for visual representation
- [ ] Fetch progress data from controller
- **Definition of done:** Users can see their overall progress at a glance

#### S1.4.2 Create session-level progress detail
- [ ] For each session, show:
  - Number of attempts made
  - Best score achieved
  - Pass/fail status
  - Date of last attempt
  - Time spent (if tracked)
- [ ] Add expandable detail view for each session
- [ ] Show next recommended session or action
- **Definition of done:** Users understand their progress per session

#### S1.4.3 Build phase roadmap visualization
- [ ] Create visual roadmap showing all phases
- [ ] Indicate which phases are locked, available, in-progress, or complete
- [ ] Show dependencies (e.g., "Complete Phase 1 to unlock Phase 2")
- [ ] Use icons or badges for status
- **Definition of done:** Users see the full progression path ahead of them

### S1.5: Profile and Account Settings
#### S1.5.1 Create profile page
- [ ] Create `resources/js/Pages/Passimark/Profile.jsx`
- [ ] Display user name, email, and role
- [ ] Show user joined date
- [ ] Display current phase and progress summary
- [ ] Add edit profile link (for future "edit name/password" feature)
- **Definition of done:** Users can view their account information

#### S1.5.2 Add basic profile settings
- [ ] Add option to change password (form only; no backend yet if complex)
- [ ] Add email preference settings (if applicable)
- [ ] Add download or export progress data option
- [ ] Style consistently with dashboard
- **Definition of done:** Users have basic profile management

### S1.6: Backend: Dashboard Data API
#### S1.6.1 Create PassimarkController methods
- [ ] Implement `dashboard()` method to fetch:
  - User's completed sessions
  - User's progress records
  - Available sessions based on phase/role
  - Overall mastery and completion stats
- [ ] Return data as Inertia props to React component
- [ ] Use eager loading to avoid N+1 queries
- **Definition of done:** Dashboard API returns correct data; queries are optimized

#### S1.6.2 Create session list endpoint
- [ ] Implement method to fetch all sessions available to user
- [ ] Include user's progress for each session
- [ ] Filter by role/approval state
- [ ] Return JSON or Inertia props
- **Definition of done:** Frontend receives complete session data with progress

#### S1.6.3 Create progress calculation methods
- [ ] In `PassimarkProgress` model, add method to calculate:
  - Overall completion percentage
  - Mastery by domain
  - Current phase
  - Sessions completed vs. total
- [ ] Cache or optimize to avoid repeated calculation
- **Definition of done:** Progress metrics are calculated and available for display

### S1.7: UI/UX Refinement and Styling
#### S1.7.1 Apply design tokens to dashboard
- [ ] Define Tailwind CSS config colors for dark theme
- [ ] Use emerald/teal for primary accent color
- [ ] Ensure all text meets WCAG contrast minimums
- [ ] Apply consistent spacing and typography
- [ ] Use brand fonts (if any are chosen)
- **Definition of done:** Dashboard looks professional and on-brand

#### S1.7.2 Test responsive behavior
- [ ] Test dashboard on desktop (1440px+)
- [ ] Test on tablet (768px to 1023px)
- [ ] Test on mobile (375px to 767px)
- [ ] Verify navigation collapses on small screens
- [ ] Verify cards and lists stack properly
- [ ] Test touch interactions on mobile
- **Definition of done:** Dashboard is usable on all screen sizes

#### S1.7.3 Add empty states and loading states
- [ ] Show skeleton/loading state while data fetches
- [ ] Show helpful message if no sessions are available
- [ ] Show message if user is waiting for approval
- [ ] Style empty states to match brand
- **Definition of done:** Every state of the UI is handled gracefully

### S1.8: Testing and Validation
#### S1.8.1 Write unit tests for dashboard logic
- [ ] Test `PassimarkController->dashboard()` returns correct shape
- [ ] Test progress calculation for various scenarios
- [ ] Test session availability filtering
- [ ] Verify queries use eager loading
- **Definition of done:** Core dashboard logic has test coverage

#### S1.8.2 Manual testing checklist
- [ ] Log in as student and verify dashboard loads
- [ ] Verify all navigation links work
- [ ] Verify progress updates correctly
- [ ] Verify session status displays correctly
- [ ] Verify responsive layout on multiple devices
- [ ] Test with different user roles (student, admin)
- **Definition of done:** Dashboard is functionally complete and ready for Sprint 2

#### S1.8.3 Create user acceptance criteria
- [ ] Student can log in successfully
- [ ] Student sees personalized dashboard
- [ ] Student can see all available sessions
- [ ] Student can see their progress and mastery
- [ ] Student can navigate to profile
- [ ] All text is readable and styled correctly
- **Definition of done:** Dashboard meets user expectations

---

## Task Assignment and Tracking

### How to use this document
1. **Assign tasks:** Each task (S0.1.1, S1.1.1, etc.) is a deliverable that can be assigned to a developer
2. **Track progress:** Replace `[ ]` with `[x]` as tasks are completed
3. **Update frequently:** Use this as the source of truth for sprint status
4. **Review daily:** Review during standup to identify blockers

### Estimated effort
- **Sprint 0:** 3-5 days (1 developer, environment-focused)
- **Sprint 1:** 5-7 days (1-2 developers, UI and auth-focused)

### Dependencies
- Sprint 0 must complete before Sprint 1 begins
- Sprint 1 dashboard requires S0.2 and S0.3 to be done
- Sprint 1 testing assumes S0 environment is stable

### Blockers and risks
- Database setup issues (check connectivity and migrations)
- npm or Composer dependency conflicts (clean install if needed)
- Tailwind CSS config or asset paths (verify vite.config.js)
- Seeder data not loading (check for validation or constraint errors)

---

## Commit strategy for Sprints 0 and 1
- Commit environment setup changes: `git commit -m "Sprint 0: Environment setup and validation"`
- Commit auth UI changes: `git commit -m "Sprint 1: Add login page and auth UI"`
- Commit dashboard: `git commit -m "Sprint 1: Add student dashboard and session list"`
- Commit tests: `git commit -m "Sprint 1: Add dashboard tests and validation"`
- Push to feature branch before opening PR for review

