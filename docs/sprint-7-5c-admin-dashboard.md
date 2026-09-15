# Sprint 7.5c — Role-branched admin dashboard

Follow-up of the Sprint 7.5b dashboard audit. The audit found that `GET /` rendered the learner mastery path for **every** authenticated user (`PassimarkController::dashboard` had no role branch), so admins/instructors landed on the student screen; the real admin workspace lived orphaned at `/admin/passimark` in a separate shell.

## Correction
- `PassimarkController::dashboard()` dispatches on `Auth::user()->role`:
  - `admin` / `instructor` → `PassimarkAdminController::dashboard()` → new `Passimark/AdminDashboard` Inertia page
  - `student` → unchanged learner mastery path (`Passimark/Dashboard`)
- `PassimarkAdminController::dashboard()` (new home payload, deliberately lighter than the Control Center's `index()`):
  - `report` — learners, attempts, completed attempts, average score, pending approvals, sessions, questions, tracks, completions, approvals in the last 7 days
  - `pending` — approval queue with learner + session + score
  - `events` — 20 latest approval decisions (with note)
  - `needsAttention` — contentless sessions, empty tracks, untagged questions
- `resources/js/Pages/Passimark/AdminDashboard.jsx` renders inside the shared `DashboardLayout`: Operations header without learner chrome, needs-attention strip, report card grid, inline Approve/Reject on the queue (same `fetch` + XSRF + `router.reload` pattern as the Control Center), recent decisions feed, and quick links to Control Center + Import Questions.
- `DashboardLayout` is role-aware: region filter and IRT θ badge only show for students; staff chrome reads "Passimark Operations"; staff sidebar = Overview / Control Center / Import Questions / Profile / Settings.

## Verification
- `tests/Feature/AdminDashboardTest.php` (4 tests): admin lands on `Passimark/AdminDashboard` with report/queue/attention/events; instructor also lands there; student keeps `Passimark/Dashboard`; a pending progress row appears in both the report count and the queue.
- Full suite **68 tests / 7,112 assertions** green; `npm run build` green; `php -l` clean on touched files.

## Files touched
- `app/Http/Controllers/PassimarkController.php` (role dispatch)
- `app/Http/Controllers/PassimarkAdminController.php` (`dashboard()` home payload)
- `resources/js/Pages/Passimark/AdminDashboard.jsx` (new page)
- `resources/js/Layouts/DashboardLayout.jsx` (role-aware chrome/nav)
- `tests/Feature/AdminDashboardTest.php` (new tests)