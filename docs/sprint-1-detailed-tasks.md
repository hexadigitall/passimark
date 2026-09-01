# Sprint 1: Student Dashboard & Authentication UI - Detailed Tasks

**Sprint Duration:** 5-7 days  
**Target:** Complete functional login → dashboard with session management  
**Team Size:** 1-2 developers

---

## S1.1: Login & Auth UI - Complete Authentication Form (2 days)

### S1.1.1: Build Login Page Component ✅ (In Progress)
**File:** `resources/js/Pages/Auth/Login.jsx`
**Status:** Component structure started
**Tasks:**
- [x] Create React component with Inertia form
- [x] Dark theme styling with Tailwind
- [ ] Email input with validation
- [ ] Password input (password type)
- [ ] Remember me checkbox (optional)
- [ ] Submit button with loading state
- [ ] Error message display for failed login
- [ ] Forgot password link (placeholder)
- [ ] Responsive on mobile
- [ ] Pre-fill test credentials

**Definition of Done:**
- Component renders without errors
- Form validates on submit
- Login works with test user (student@passimark.com/password)
- Redirects to dashboard on success
- Shows error message on invalid credentials
- Styles match dark premium theme

**Testing:**
```bash
# Login flow test
1. Navigate to http://localhost:8000/login
2. Enter student@passimark.com / password
3. Click Sign In
4. Should redirect to /dashboard
5. Verify session created (check browser cookies)
```

---

### S1.1.2: Build Registration Form  
**File:** `resources/js/Pages/Auth/Register.jsx`
**Tasks:**
- [ ] Create registration component
- [ ] Name, email, password, password_confirm fields
- [ ] Client-side validation (email format, password length)
- [ ] Form submission to `/register` endpoint
- [ ] Error handling and display
- [ ] Link to login page
- [ ] Responsive layout

**Definition of Done:**
- Registration form submits without errors
- User created in database
- Auto-login after registration
- Redirect to dashboard
- Validation errors display correctly

---

### S1.1.3: Test Auth Flow End-to-End  
**Scenarios:**
- [x] Login with valid credentials → redirect to dashboard
- [ ] Login with invalid email → error message
- [ ] Login with wrong password → error message
- [ ] Register new user → auto-login
- [ ] Logout → redirect to login
- [ ] Accessing /dashboard without auth → redirect to /login
- [ ] Remember me functionality (optional)

---

## S1.2: Dashboard Layout & Navigation (1 day)

### S1.2.1: Create Main Dashboard Layout ✅ (In Progress)
**File:** `resources/js/Layouts/DashboardLayout.jsx`
**Status:** Layout structure created
**Tasks:**
- [x] Create responsive sidebar (desktop) / hamburger (mobile)
- [x] Top navigation bar
- [x] User profile dropdown / menu
- [x] Main content area (children)
- [ ] Dark theme with emerald accents
- [ ] Smooth transitions and animations
- [ ] Active route highlighting
- [ ] Mobile-responsive sidebar toggle

**Definition of Done:**
- Layout renders correctly on mobile, tablet, desktop
- Sidebar toggles on mobile
- User info displays correctly
- All navigation links work
- Styling matches design system

---

### S1.2.2: Build Sidebar Navigation  
**Navigation Items:**
- [ ] Dashboard (home icon)
- [ ] My Sessions (book icon)
- [ ] Progress (chart icon)
- [ ] Help/FAQ (question icon)
- [ ] Settings (gear icon)

**Features:**
- [ ] Active link highlighting
- [ ] Icons from lucide-react
- [ ] Smooth hover effects
- [ ] Collapsible on mobile

---

### S1.2.3: Create Top Bar Components  
**Components:**
- [ ] App logo/branding
- [ ] Page title
- [ ] User profile dropdown menu
  - [ ] View profile
  - [ ] Settings
  - [ ] Logout button

**Styling:**
- [ ] Matches sidebar theme
- [ ] Responsive (hide profile on small screens)
- [ ] Notification badge ready (future feature)

---

## S1.3: Sessions List & Dashboard Display (2 days)

### S1.3.1: Build Sessions Grid Component ✅ (In Progress)
**File:** `resources/js/Pages/Passimark/Dashboard.jsx`
**Tasks:**
- [x] Fetch sessions from backend
- [x] Display session cards in grid
- [ ] Responsive: 1 col mobile, 2 cols tablet, 3 cols desktop
- [ ] Session card content:
  - [ ] Session number/title
  - [ ] Domain (subject area)
  - [ ] Question count
  - [ ] Pass score requirement
  - [ ] Current user score (if attempted)

**Definition of Done:**
- Sessions load from backend without errors
- Grid responsive on all devices
- Cards display all required info
- No N+1 query problems (use eager loading)
- Loads in < 2 seconds

---

### S1.3.2: Implement Session Status Badges  
**Statuses & Styling:**
- [ ] `locked` - Gray, lock icon, disabled button
- [ ] `open` - Blue, play icon, "Start Session" button
- [ ] `in_progress` - Yellow, clock icon, "Resume" button
- [ ] `completed` - Green, checkmark, show score
- [ ] `pending_approval` - Orange, alert icon, "Awaiting Approval"
- [ ] `approved` - Green, checkmark, "Next Session Unlocked"

**Visual Indicators:**
- [ ] Background color per status
- [ ] Icon per status
- [ ] Progress bar showing score (if available)
- [ ] Pass/fail indicator

---

### S1.3.3: Add Session Action Buttons  
**Functionality:**
- [ ] "Start Session" button (if open) → POST /passimark/session/{id}/start
- [ ] "Resume" button (if in_progress) → POST /passimark/session/{id}/start
- [ ] "Locked" button (if locked) → disabled state
- [ ] Show approval message (if pending_approval)
- [ ] Button loading state during submission
- [ ] Error handling and display

**Behavior:**
- [ ] Clicking start → redirect to exam interface (S2)
- [ ] Show loading spinner while processing
- [ ] Handle errors gracefully

---

## S1.4: User Progress & Roadmap (1 day)

### S1.4.1: Create Progress Overview Section  
**Display:**
- [ ] Current phase indicator
- [ ] Total progress percentage
- [ ] Sessions completed / total sessions
- [ ] Current ability level (theta estimate)
- [ ] Session history list

**Components:**
- [ ] Progress circle/gauge showing % complete
- [ ] Phase badges (Phase 1/2/3/4)
- [ ] Session progress dots (completed/pending/locked)

---

### S1.4.2: Build Score History Chart  
**Chart Features:**
- [ ] Line/bar chart of session scores over time
- [ ] X-axis: Session number
- [ ] Y-axis: Score percentage
- [ ] Pass score threshold line (70%)
- [ ] Responsive sizing

**Libraries:**
- [ ] Chart.js or Recharts (lightweight)
- [ ] Responsive container

---

## S1.5: User Profile & Settings (1 day)

### S1.5.1: Build Profile Page  
**File:** `resources/js/Pages/Profile/Show.jsx`
**Content:**
- [ ] User name
- [ ] Email address
- [ ] Account creation date
- [ ] Role badge (Student/Instructor/Admin)
- [ ] Total sessions attempted
- [ ] Average score

---

### S1.5.2: Create Edit Profile Form  
**Fields:**
- [ ] Name (editable)
- [ ] Email (readonly or editable)
- [ ] Current password (for verification)
- [ ] New password (optional)
- [ ] Confirm new password

**Features:**
- [ ] Form validation
- [ ] Error handling
- [ ] Success message after save
- [ ] Cancel button

---

### S1.5.3: Settings Panel  
**Options:**
- [ ] Notifications (email alerts for approvals)
- [ ] Timezone
- [ ] Theme preference (if adding light theme later)
- [ ] Download data

---

## S1.6: Backend API Integration (1 day)

### S1.6.1: Implement /api/sessions Endpoint  
**Route:** `GET /api/sessions`
**Returns:**
```json
{
  "data": [
    {
      "id": 1,
      "number": 1,
      "title": "Session 1 • Security Governance",
      "domain": "Security and Risk Management",
      "question_count": 25,
      "pass_score": 70,
      "progress": {
        "status": "open",
        "score": null,
        "attempts": 0
      }
    }
  ]
}
```

**Filtering:**
- [ ] Filter by phase
- [ ] Filter by status (for learner's own sessions)
- [ ] Order by session number

---

### S1.6.2: Implement /api/progress Endpoint  
**Route:** `GET /api/progress`
**Returns:**
```json
{
  "data": {
    "sessions_attempted": 5,
    "sessions_completed": 3,
    "average_score": 82.5,
    "current_phase": 2,
    "ability_theta": 0.45
  }
}
```

---

### S1.6.3: Setup Inertia Shared Data  
**File:** `app/Http/Middleware/HandleInertiaRequests.php`
**Shared Data:**
- [x] `auth.user` (current user)
- [ ] `auth.user.role` (user role for conditionals)
- [ ] `flash` (success/error messages)
- [ ] `csrf_token` (for forms)

---

## S1.7: Styling & Theme Application (1-2 days)

### S1.7.1: Apply Dark Theme Consistently  
**Color Palette:**
- Backgrounds: slate-900, slate-800, slate-700
- Text: white, slate-300, slate-400
- Accents: emerald-400, emerald-500, emerald-600
- Danger: red-400, red-500
- Warning: yellow-400, orange-400

**Typography:**
- [ ] Font family: Inter or system-ui
- [ ] Headings: bold, white
- [ ] Body text: slate-300
- [ ] Small text: slate-400

---

### S1.7.2: Implement Interactive Elements  
**Buttons:**
- [ ] Primary (emerald-600, hover emerald-700)
- [ ] Secondary (slate-700, hover slate-600)
- [ ] Danger (red-600, hover red-700)
- [ ] Disabled state (opacity-50, cursor-not-allowed)

**Inputs:**
- [ ] Background: slate-700
- [ ] Border: slate-600, focus emerald-500
- [ ] Text: white, placeholder slate-400
- [ ] Focus ring: emerald-500/20

**Cards:**
- [ ] Background: slate-800
- [ ] Border: slate-700
- [ ] Hover: border-emerald-500/50
- [ ] Shadow: emerald-500/10

---

### S1.7.3: Add Smooth Animations  
**Transitions:**
- [ ] 200ms smooth transitions on hover
- [ ] Fade-in animations on page load
- [ ] Loading spinners on buttons
- [ ] Skeleton screens for loading states

**Mobile UX:**
- [ ] Touch-friendly button sizes (44px minimum)
- [ ] Smooth hamburger menu animation
- [ ] Responsive text sizes
- [ ] Proper spacing on small screens

---

## S1.8: Testing & Quality Assurance (1-2 days)

### S1.8.1: Frontend Component Testing  
**Test Cases:**
- [ ] Login form validation
- [ ] Login success flow
- [ ] Login error handling
- [ ] Registration flow
- [ ] Logout functionality
- [ ] Dashboard loads sessions
- [ ] Session status badges display correctly
- [ ] Responsive layout on mobile/tablet/desktop
- [ ] Navigation between pages
- [ ] Error messages display

**Tools:**
- [ ] Manual testing (most important for S1)
- [ ] Browser DevTools console (check for errors)
- [ ] Lighthouse (performance)

---

### S1.8.2: Backend API Testing  
**Endpoints:**
- [ ] GET /login → displays login form
- [ ] POST /login → authenticates user
- [ ] POST /logout → ends session
- [ ] GET /dashboard → displays sessions
- [ ] GET /api/sessions → returns sessions JSON
- [ ] GET /api/progress → returns progress JSON

**Test Credentials:**
```
User: student@passimark.com
Pass: password
Role: student
```

---

### S1.8.3: Integration Testing  
**Flows:**
- [ ] Login → Dashboard → See sessions (complete chain)
- [ ] Session status updates persist
- [ ] Navigation between components
- [ ] Forms submit and redirect correctly
- [ ] Errors display and recover gracefully

**Browser Compatibility:**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest, if Mac available)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

---

### S1.8.4: Performance & Debugging  
**Checks:**
- [ ] Page loads in < 2 seconds
- [ ] No console errors
- [ ] No N+1 queries (check Laravel debug bar)
- [ ] Images/assets load correctly
- [ ] Responsive images (srcset)
- [ ] No memory leaks in React

**Tools:**
- [ ] Laravel Debugbar (enabled in .env)
- [ ] Browser DevTools Network tab
- [ ] React DevTools extension
- [ ] Lighthouse Chrome DevTools

---

## Definition of Done for Sprint 1

Sprint 1 is complete when:
- ✅ All S1.1-S1.8 components render without errors
- ✅ Login → Dashboard flow works end-to-end
- ✅ Session list displays with correct status
- ✅ All responsive layouts verified (mobile, tablet, desktop)
- ✅ Dark theme applied consistently
- ✅ No console errors in DevTools
- ✅ Database queries optimized (no N+1)
- ✅ Code committed to `feature/sprint-1-dashboard` branch
- ✅ PR created to merge to `develop`
- ✅ Live demo ready on localhost:8000

---

## Daily Standup Template

**Format:** 5-10 minutes  
**When:** 9:00 AM daily  
**Attendees:** All developers  

### Questions
1. **Yesterday:** What tasks did you complete?
2. **Today:** What will you work on?
3. **Blockers:** Any issues preventing progress?

### Example
```
Yesterday:
- Completed S1.1.1 (Login component)
- Started S1.2.1 (Dashboard layout)

Today:
- Finish S1.2.1 and S1.2.2 (nav sidebar)
- Begin S1.3.1 (sessions grid)

Blockers:
- None
```

---

## Success Metrics

**By end of S1:**
- ✅ User can login with test credentials
- ✅ User can see list of 46 sessions
- ✅ Session status displays (locked, open, completed, etc.)
- ✅ UI responsive on mobile, tablet, desktop
- ✅ Page loads in < 2 seconds
- ✅ No console errors
- ✅ Code reviewed and documented
- ✅ Team prepared for S2 (exam interface)

---

**Sprint 1 Begins:** Sept 1, 2026  
**Target Completion:** Sept 6-8, 2026  
**Handoff to Sprint 2:** Sept 8, 2026
