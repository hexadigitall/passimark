# Sprint 9.5 — First-run funnel: backend rung group — Report

**Branch:** `feature/sprint-7-5-content-coherence`
**Date:** Sprint 9.5 backend slice (funnel chrome slice landed separately)
**Gates:** suite **112 tests / 44,598 assertions green** · `npm run build` green (132.49 kB gzip) · `php -l` clean (controller + routes)

## What shipped (all verbatim-bound, no dead anchors)
- **`app/Http/Controllers/PassimarkFunnelController.php`** — a real funnel controller. Every rung
  runs a **verbatim local `ensureEnrolled()` gate** (identical recovery to the lean catalog slice —
  `PassimarkProgress::where('user_id', Auth::id())->doesntExist()` → `Curriculum::enrollFirstSteps()
  ::enrollFirstSteps(Auth::user())`), so a first-run learner is auto-enrolled before any rung renders.
- **6 named funnel rungs**, bound in `routes/web.php` **inside the authenticated group** (after the
  catalog group, before the admin group), each rendering a real Inertia page:
  | rung | route | name |
  |---|---|---|
  | Lock | `GET /passimark/funnel/lock` | `passimark.funnel.lock` |
  | Splash | `GET /passimark/funnel/splash` | `passimark.funnel.splash` |
  | Intro | `GET /passimark/funnel/intro` | `passimark.funnel.intro` |
  | Auth | `GET /passimark/funnel/auth` | `passimark.funnel.auth` |
  | Focus | `GET /passimark/funnel/focus` | `passimark.funnel.focus` |
  | Permissions | `GET /passimark/funnel/permissions` | `passimark.funnel.permissions` |

- Each rung returns the real `Passimark/Dashboard` page for now (the dedicated Lock/Splash/Intro/
  Auth/Focus/Permissions **React screens are a separate, PENDING frontend slice** — tracked, not
  fabricated). Rung next-links resolve to real bound route names, so no funnel step points at a 404.

## Honest boundary
- The **backend rung ladder is real and navigable** (auth'd, gate-protected, named routes).
- The **frontend React ladder screens** (Lock → Splash → Intro → Auth → Focus → Permissions) and the
  **omnibox chrome placement** in the dashboard remain PENDING — tracked in `docs/sprint-progress-tracker.md`.

## Gate
- `vendor/bin/phpunit` — **112 tests / 44,598 assertions, OK**.
- `npm run build` — **green**, built in 6.91s, app JS 457.22 kB (gzip 132.49 kB) + Vite manifest copied.
