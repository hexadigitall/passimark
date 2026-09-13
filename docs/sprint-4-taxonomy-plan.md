# Sprint 4: Certification Track and Tag Taxonomy

**Status:** planned, not started
**Depends on:** Sprint 3 CRUD foundation (done)

> **v4.0 linkage:** the approved Worldwide catalog spec ([v4-worldwide-catalog-spec.md](v4-worldwide-catalog-spec.md)) depends on this sprint's tags as the real `domain` model. 4.2 must complete before Sprint 5 (worldwide schema/seeder) in [sprint-5-v4-worldwide-catalog.md](sprint-5-v4-worldwide-catalog.md).

## Why this sprint exists

The current schema hardcodes one curriculum: every `PassimarkSession` implicitly belongs to the same 46-session CISSP program, and `domain`/`bloom_level` are free-text strings duplicated per row with no lookup table. Every strategic document (`product-spec.md`, `full-implementation-plan.md`, `mvp-backlog.md`) describes Passimark as a multi-certification, tag-driven platform, but no migration, model, or CRUD screen has ever implemented that. This sprint closes that gap before further content-scale features are built on top of the wrong shape.

## Scope

### 4.0: Web branding asset wiring — done
- Favicon (`.ico` + 16x16/32x32 PNG), apple-touch-icon, and `<link rel="manifest">` added to `resources/views/app.blade.php`
- `public/manifest.json` icons array expanded to the full existing set (16 through 1024px) from `public/images/passimark/`
- Verified live: tags render in the served page head and `/manifest.json` returns 200
- Native platform icon sets (Android mipmaps, iOS icon sets) remain out of scope here — tracked under Epic 7 in `mvp-backlog.md` until native packaging begins

### 4.0.1: Branding and flow coherence pass — done
- Replaced the placeholder icon+text mark in `DashboardLayout.jsx` with the real Passimark logo image; removed the orphaned, unused `PassimarkLayout.jsx` brand component instead of leaving two competing brand headers
- Flash messages shared by `HandleInertiaRequests` now render as a dismissible banner in the dashboard shell, closing a silent-confirmation gap on actions like approval requests (covered by a feature test asserting the session flash value)
- Added a "Back to dashboard" link on the admin control center, which previously had no return navigation
- Restyled `Register.jsx` to match `Login.jsx`'s gradient/blur background and rounded card so the two auth screens are visually consistent

### 4.1: Certification track model — done
- Migration `2026_01_01_000003_create_passimark_certification_tracks_table.php`: creates `passimark_certification_tracks`, adds `certification_track_id` FK to `passimark_sessions`, and backfills any existing sessions onto a default CISSP track
- Model: `PassimarkCertificationTrack` with a `sessions()` relation; `PassimarkSession` now has `certificationTrack()` and requires `certification_track_id`
- Admin CRUD: create/update/delete endpoints in `PassimarkAdminController`, role-protected via the existing `role:instructor,admin` middleware; deleting a track with assigned sessions is blocked
- Admin UI: new "Tracks" tab in `Admin.jsx`, and the session form now requires selecting a track
- `PassimarkSeeder` explicitly assigns all seeded CISSP content to a `cissp` track
- Verified by a feature test that creates a second track ("Security+") and a session scoped to it entirely through the CRUD API — the actual Sprint 4 exit criterion

### 4.2: Tag taxonomy
- Migration: `passimark_tags` (`id`, `type` [`domain`|`bloom`|`skill`], `label`, `slug`, timestamps)
- Pivot migrations: `passimark_question_tag`, `passimark_session_tag`
- Models: `PassimarkTag` with `questions()`/`sessions()` belongs-to-many relations
- Data backfill: a one-time console command that reads existing `domain`/`bloom_level` string values, creates distinct tags, and attaches them — this must not silently drop existing content
- Admin CRUD: tag management screen (create/rename/delete tag)
- Question/session forms updated to select tags instead of free-typing domain/bloom strings

### 4.3: Validation and integrity
- `PassimarkAdminController::questionData()`/`sessionData()` updated to validate tag IDs against the `passimark_tags` table instead of accepting arbitrary strings
- Deleting a tag in use must be blocked or require explicit reassignment (decide and enforce one rule, do not leave orphaned pivot rows)

### 4.4: Backward compatibility
- `domain` and `bloom_level` string columns remain during migration for read compatibility, then are deprecated once all reads/writes go through tags
- Existing seeded content (`PassimarkSeeder`) updated to assign tags instead of raw strings once the model exists

## Out of scope for this sprint

- CAT engine configurability per track (follow-up sprint)
- Multi-tenant/organization support
- `.psmk` import/export implementation

## Exit criteria

- A second certification track can be created through the admin UI without touching code
- Existing CISSP content is fully backfilled with tags and a certification track with zero data loss
- All new endpoints are covered by a feature test proving validation and role protection
- Full PHPUnit suite and production build remain green
