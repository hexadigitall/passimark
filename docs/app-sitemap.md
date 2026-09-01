# Passimark App Sitemap and Screen Blueprint

## Public section
### Marketing site
- Home
- Features
- Solutions / Domains
- Pricing or Plans
- About
- Contact
- Login
- Sign Up

## Authentication
- Login
- Register
- Forgot Password
- Reset Password
- Email Verification
- Logout

## Learner dashboard
- Overview
- My Sessions
- Active Exam
- Practice Lab
- Results
- Progress
- Study Recommendations
- Profile Settings

## Session / exam flow
- Session list
- Session detail
- Exam instructions
- Exam start confirmation
- Active question screen
- Flagged questions panel
- Review screen
- Final submit confirmation
- Results summary
- Explanation view
- Retake path

## Admin section
- Overview
- Learners
- Pending Approvals
- Session Management
- Exam Management
- Question Library
- Content Import
- Reports
- Settings

## Admin task flows
- approve learner completion
- reject learner completion with notes
- add new session
- edit session details
- add new exam
- add new question
- bulk import questions
- audit learner attempts

## Reporting views
- learner progress report
- domain mastery report
- session pass rate
- exam analytics
- item difficulty stats
- cohort comparison

## Settings
- branding and logo
- platform configuration
- role management
- notification preferences
- environment configuration

## Screen blueprint by role
### Student screen sequence
1. Login
2. Dashboard
3. Session list
4. Start exam
5. Answer questions
6. Finish exam
7. Review results
8. Request approval
9. Unlock next session

### Admin screen sequence
1. Login
2. Admin dashboard
3. Pending approvals queue
4. Review learner attempt
5. Approve or reject
6. Update learner progression
7. Manage content library or session data

## Navigation pattern recommendation
- persistent left sidebar for desktop
- collapsible menu for tablet
- bottom sheet or compact drawer for mobile
- exam mode uses minimal chrome and sticky controls

## Screen behaviors
### Dashboard behavior
- summary cards for sessions and mastery
- session progress chips
- current phase visibility
- recommended next action

### Exam behavior
- question viewport with timer
- option grid or list
- flag question action
- question navigation drawer
- answer review before submission

### Admin behavior
- tables with sortable columns
- filters by status, domain, and date
- bulk actions for approvals or imports
- confirmation modals for destructive actions

## UI state considerations
- loading states
- empty states
- error toasts
- form validation states
- success confirmation states
- locked session states

## Final design principle
The app should feel like a premium assessment product rather than a generic learning portal. Every screen should reinforce trust, mastery, and progression.
