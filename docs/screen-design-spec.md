# Passimark Screen Design Specification

**Status:** authoritative design reference
**Scope:** all 26 routed screens, 2 shared report components, 1 app shell, 3 chrome components
**Supersedes:** the single-line visual brief in `app-sitemap.md:137`

This document exists because there was no design spec. Every screen layout to
date was reverse-engineered from sprint prose, which is why `sprint-8-1` still
describes an app that does not match what shipped. This file is the contract.
It documents the system **as built**, and every known deviation is recorded in
[§11 Gap register](#11-gap-register) rather than left as tribal knowledge.

Everything below is extracted from shipped code, not aspirational. Where a value
is a rule for future work it is marked **Rule**; where it is a measurement of
today it is marked **Now**.

---

## 1. Visual direction

The product brief is *"a premium assessment product rather than a generic
learning portal."* Concretely that means:

- **Evidence over decoration.** Every screen's primary job is to answer "what is
  my standing, and what do I do next." A screen that cannot state both fails.
- **Dark, instrument-panel surface.** The app is dark-only by decision, not by
  omission — see [§2.1](#21-colour). An exam tool is used for long sessions in
  dim rooms; a light surface is a glare source and reads as "web form."
- **One accent, used only for progress and action.** Emerald is the brand and
  nothing else. It never becomes decorative. Danger is red, caution is amber,
  and no other hue competes.
- **Calm density.** The catalog is 205 certifications. The system must make
  that feel navigable, not impressive — one clear primary action per screen, and
  search one keystroke away rather than a wall of tiles.

**Rule:** a new screen ships only if it can state its single primary action in
one sentence. Two competing primary CTAs is a design defect.

---

## 2. Design tokens

Tokens are Tailwind utility classes, not CSS custom properties. There is no
theme layer, so the values below *are* the contract and are greppable.

### 2.1 Colour

**Now** — measured usage across `resources/js`:

| Role | Class family | Uses | Rule |
|---|---|---|---|
| Brand / progress / primary action | `emerald-{400,500,600}` | 254 | The only accent. Primary CTAs, progress bars, focus rings, success. |
| Surface / text | `slate-{400…900}` | 818 | Every surface and every text tone. |
| Caution | `amber-{300…500}` | 52 | Locked states, gated content, "requires approval". |
| Danger / destructive | `red-{300…500}` | 31 | Reject, delete, invalid input, failed verification. |
| Informational | `sky-{300…500}` | 10 | Verification success, neutral system notes. |
| — | `zinc-*`, `indigo-*` | **0** | Not part of the system. Do not introduce. |

**Rule:** `dark:` variants are forbidden. The app has exactly one theme; `dark:`
appears 0 times by design, and adding it implies a light theme that does not
exist. Text hierarchy comes from `slate-100` (primary) → `slate-400`
(secondary) → `slate-500` (placeholder/muted).

> **Resolved.** The stock Tailwind greens are gone. `docs/v4-worldwide-catalog-spec.md`
> §2.2 has specified the brand since Sprint 4 — Primary `#1A9E2D`, Deep `#0F5D2F`,
> Accent `#7CFC8F`, Dark `#0F172A` — but those tokens reached `tailwind.config.js`
> in Sprint 7 and were referenced **zero** times, so the UI rendered emerald
> (hue 160–163°) against the spec's 129° while the manifest and the icon shipped
> the spec green. Two hue families, and no test noticed.
>
> The app now renders the spec's palette as a real scale: `brand-50…950`, with
> `brand-600` = Primary and `brand-800` = Deep exactly. Primary is anchored at
> **600, not 500** — as a button fill with `slate-950` ink it only reaches
> 5.74:1, while `brand-500` reaches 7.76:1. Steps 50–600 are ≥5.08:1 on `#0F172A`;
> 700 is AA-large; 800+ are fill/border only. `BrandPaletteTest` enforces all of
> it, including that no `emerald-*` class or raw emerald hex returns.

### 2.2 Typography

**Now** — Inter Variable, latin subset, self-hosted from `public/fonts`, declared
at `font-weight: 100 900` with `font-display: swap` and an explicit
`unicode-range`, and preloaded with `crossorigin`. One 47 KB file serves the whole
weight axis, so the four weights the app uses cost one request rather than four
static faces. `tailwind.config.js` `fontFamily.sans` and `fault.css` both resolve
through the `--pm-font-sans` custom property in `app.css`, because the fault
screen renders outside React and previously sat on its own unbranded stack while
the JSX looked configured. `TypographyTest` guards it.

Scale usage: `text-sm` 193 · `text-xs`
123 · `text-lg` 25 · `text-xl` 16 · `text-3xl` 16 · `text-2xl` 13 ·
`text-4xl` 4 · `text-base` 3.

**Rule:**
- The type scale is **mode-dependent** ([§12.1](#121-the-concept-focus-and-command)).
  FOCUS screens use `text-base`/`text-lg` minimum. COMMAND tables use
  `text-xs`/`text-sm`. `text-base` appearing only 3 times across 28 screens is
  the symptom of one scale trying to serve both.
- All measured data — scores, timers, counts, table columns — uses
  `tabular-nums` ([§12.3a](#123-three-supporting-concepts)).
- Numerals in prose (e.g. "205 certifications") may use default proportional
  figures.

### 2.3 Radii

**Now** — `rounded-lg` 94 · `rounded-full` 73 · `rounded-2xl` 58 · `rounded-xl`
31 · `rounded-3xl` 3 · `rounded-md` 5 · `rounded-none` 0.

**Rule:**
- `rounded-lg` — inputs, buttons, table cells, inline chips.
- `rounded-2xl` — **cards only.** This is the app's one signature shape.
- `rounded-full` — pills, avatars, icon buttons, progress tracks.
- `rounded-3xl` — reserved for hero surfaces. At most one per screen.

### 2.4 Spacing & elevation

**Rule:**
- Outer padding `p-6` in the app shell. Funnel and auth screens use `p-6` plus a
  centred constraint (see [§3.2](#32-variant-b-full-bleed)).
- Vertical rhythm between sections: `mt-8` minor, `mt-12` major.
- There is **no shadow system.** Depth is expressed with
  `border border-slate-700` on `bg-slate-800`/`bg-slate-900`. Shadows are
  forbidden except on true overlays (modals, drawers).

### 2.5 Iconography

Lucide, default weight and size, `aria-hidden` unless the icon is the only
label. Icon-only controls require an `aria-label`. No filled or duotone styles —
a filled icon next to a stroked one reads as a different product.

---

## 3. Layout system

Two layout variants. Every screen is exactly one of them.

### 3.1 Variant A — app shell (auth + staff chrome)

`resources/js/Layouts/DashboardLayout.jsx` is the only shell. Structure, in DOM
order:

```
fixed <aside>  w-64  bg-slate-800  border-r slate-700   ← nav, 256px
  └ <nav> p-6 space-y-2
<header>        bg-slate-800  border-b slate-700        ← topbar
  └ menu button (lg:hidden) · omnibox (hidden lg:block, max-w-md, lg:mx-auto)
<main>          flex-1 p-6                               ← content
```

**Now:** the sidebar is a fixed overlay that slides via
`transform transition-transform duration-300 ease-in-out`, and is closed by
default below `lg`. Above `lg` it is static and persistent.

**Rule:**
- Breakpoint contract: `lg` is the **only** drawer/static boundary. A screen
  must not introduce a second competing breakpoint for chrome.
- `main` must constrain its content. **Now it does not** — see
  [§11 G-07](#11-gap-register). Until fixed, individual screens supply their own
  `max-w-*` or grid.
- Any screen needing to escape the shell padding must say so in its own header
  comment.

### 3.2 Variant B — full bleed

Used by everything a guest or a learner mid-assessment sees. No sidebar, no
topbar, no nav. Centred column:

| Screen | Constraint |
|---|---|
| `Auth/Login` | `max-w-md` |
| `Auth/Register` | `max-w-md` |
| `Funnel/Auth` | `max-w-lg` |
| `Funnel/Focus` | `max-w-lg` |
| `Funnel/Intro` | `max-w-2xl` |
| `Funnel/Lock` | `max-w-lg` |
| `Funnel/Permissions` | `max-w-lg` |
| `Funnel/Splash` | `max-w-lg` |

**Rule:** Variant B is a deliberate *stripping* of chrome, not a fallback. It is
correct for onboarding and for the exam. It is **wrong** for any screen a user
will navigate between — if a user needs to get somewhere else, they need the
shell.

---

## 4. Responsive rules

**Now** — breakpoint usage: `sm:` 22 · `md:` 23 · `lg:` 20 · `xl:` 5 · `2xl:` 0.

**Rule — the standard every screen must meet:**

| Width | Contract |
|---|---|
| `< 640px` | Single column. No horizontal page scroll, ever. Nav is the drawer. Buttons full-width or stacked. Stat grids collapse to 1–2 columns. |
| `640–1023px` | Two-column stat/grid layouts permitted. Content still inside the shell. |
| `≥ 1024px` (`lg`) | Sidebar static, omnibox visible, full grid layouts. |
| `≥ 1280px` (`xl`) | Content ceiling. **Must not** stretch further. |

**Rule:** wide data tables are wrapped in `overflow-x-auto` so the *table*
scrolls, never the page. This is the app's one sanctioned horizontal scroll.

**Rule:** touch targets ≥ 44px on full-bleed screens, where there is no shell
padding to guarantee spacing.

**Now:** 14 of 28 files contain **no** responsive prefix at all — see
[§11 G-01](#11-gap-register).

---

## 5. Motion & accessibility

**Rule:**
- Motion is functional only: it communicates a state change (drawer opening,
  rung advancing, result revealing). It never decorates.
- One duration: `duration-300` for transforms, matching the drawer. No other
  transition timings.
- **Every animation must respect `prefers-reduced-motion`.** Wrap decorative
  loops in `motion-safe:` or gate them behind a reduced-motion media query.

**Now:** `motion-safe` and `prefers-reduced-motion` appear **0 times** in the
codebase, while `Splash` and `Funnel/Auth` run indefinite loops. This is a real
accessibility defect — see [§11 G-02](#11-gap-register).

**Rule — accessibility floor for every screen:**
- One `<h1>`. Heading levels descend without skipping.
- Every `<Head>` sets a unique `title` (enforced by `ScreenCrawlTest`).
- Every input has a `<label>`, not a placeholder-as-label.
- Every icon-only control has `aria-label`.
- Status colour is never the sole signal — pair with text or an icon.
- Keyboard: the drawer traps focus and `Escape` closes it. The omnibox supports
  arrow keys and Enter.

---

## 6. Required screen states

Every data-backed screen must implement all five. A screen missing any one is
incomplete, not "fine for now."

| State | Requirement |
|---|---|
| **Loading** | Skeleton or spinner in the content region. Never a blank page. The brand boot screen covers app chunk load. |
| **Empty** | Explains *why* it is empty and offers the action that fills it. "No results — try a different term." Never a bare table. |
| **Error** | Names the failure in plain language. `ErrorBoundary` is the last resort, not the plan. |
| **Denied / gated** | Amber, explicit about the gate and how to clear it. Gating is never removed to make a screen look finished. |
| **Success** | Confirms completion. Every mutating screen redirects or confirms. |

---

## 7. Sequence

### 7.1 First-run ladder (guest → dashboard)

Defined once in `Funnel/Auth.jsx:3` as
`['lock','splash','intro','auth','focus','permissions','dashboard']` and rendered
as an `<ol aria-label="Track ladder">` with 7 pips. This array is the single
source of truth for order; it is not repeated in route definitions.

| # | Rung | Route | Guard | Purpose |
|---|---|---|---|---|
| 1 | Lock | `GET /passimark/lock` | guest | Brand gate. No chrome. |
| 2 | Splash | `GET /passimark/splash` | guest | Brand moment. |
| 3 | Intro | `GET /passimark/intro` | guest | How it works. |
| 4 | Auth | `GET /` | guest | Sign in / sign up. |
| 5 | Focus | `GET /passimark/focus` | auth | Pick a certification focus. |
| 6 | Permissions | `GET /passimark/permissions` | auth | Notification opt-in. |
| 7 | Dashboard | `GET /` | auth | Catalog + focus resume. |

**Rule:** rungs 5 and 6 are the only auth-gated rungs. Completing either sets
`preferences.funnel_completed`, after which a returning learner bypasses 1–6
entirely. Staff bypass the ladder on first load. `Login` and `Register` are
always reachable and always offer a way back to the ladder.

**Rule — the ladder is never spoken aloud.** The rung slugs are internal route
identifiers. Rendering them as visible labels ("1 lock · 2 splash · 3 intro · 4
auth · 5 focus · 6 permissions") is developer plumbing presented as a user
journey, and it is forbidden on every screen. Position is expressed only by
`Components/FunnelProgress`, a wordless 1px bar whose location is exposed via
`role="progressbar"` and an "Step N of M" label for assistive tech. Lock and
Splash show no indicator at all — they are brand moments, and a progress bar at
14% is noise. `ScreenCrawlTest` fails the build if a funnel screen renders a raw
rung slug, prints "Rung N", or re-inlines the ladder array.

**Rule — each rung carries its own useful content.** A rung may not describe the
funnel. "This rung threads your credentials through" and "Sprint 9.5 — content
coherence" are implementation notes; a rung either does its job or does not
exist. Specifically: the picker is a real interest picker (search + region chips,
never a 205-entry `<select>`), and Permissions asks for something real with
allow/deny that persist differently.

### 7.2 Learner loop (post-onboarding)

```
Dashboard ──▶ Track ──▶ Cert ──▶ Start session ──▶ Exam ──▶ Result
    ▲            │                                              │
    │            └──────────── remediation ◀───────────────────┘
    └────────────── request approval ◀── gated ── Result
```

### 7.3 Staff loop

```
AdminDashboard ──▶ Approvals ──▶ Approve/Reject ──▶ Learner progression
       │                                                    
       ├──▶ Sessions / Tracks / Questions (read + manage)
       ├──▶ Attempts (audit)
       └──▶ ContentImport ──▶ Question library
```

---

## 8. Component inventory

| Component | File | Contract |
|---|---|---|
| `DashboardLayout` | `Layouts/DashboardLayout.jsx` | The only app shell. Sidebar + topbar + flash region. |
| `Omnibox` | `Components/Omnibox.jsx` | Global typeahead. Searches all 205 certs flat, no region filter. Arrow keys + Enter. `max-w-md`, `lg` only. |
| `Breadcrumbs` | `Components/Breadcrumbs.jsx` | Hierarchy trail for drill-down screens (Track → Cert). |
| `ErrorBoundary` | `Components/ErrorBoundary.jsx` | Last-resort containment. Renders a recoverable state, never a raw stack. |
| `FunnelProgress` | `Components/FunnelProgress.jsx` | Wordless first-run position indicator. `role="progressbar"`, "Step N of M" for AT. Never renders a rung slug. |
| `ReportHeader` | `Pages/Passimark/Reports/ReportHeader.jsx` | Shared report title bar. **Not a route.** |
| `StatStrip` | `Pages/Passimark/Reports/StatStrip.jsx` | Shared stat row. **Not a route.** |

**Rule:** the two `Reports/*` components live under `Pages/` but are not screens.
They must never appear in a route map. `ScreenCrawlTest` enforces the distinction.

---

## 9. Screen specification

Legend — **P** primary CTA · **D** data source · **S** states implemented.

### 9.1 Guest / first run (Variant B, no chrome)

| Screen | Purpose | P | D | Notes |
|---|---|---|---|---|
| `Funnel/Lock` | Brand gate | Start the ladder | none | Single `h1`. Centre column. Must be tap-through in one action. |
| `Funnel/Splash` | Brand moment | Continue | none | **Should** auto-advance; does not. No reduced-motion guard. |
| `Funnel/Intro` | How it works | Start the guide | none | **Should** be 4 slides with pips/Skip; is a single page. |
| `Funnel/Auth` | Credential gate | Sign in / Sign up | `funnel.ladder` | Renders the 7-rung ladder. Real credential gate, not a fake step. |
| `Funnel/Focus` | Choose focus | Save focus | 205 active certs / 9 regions | Validated server-side via `Rule::in`. Chips, not a 205-tile wall. |
| `Funnel/Permissions` | Notification opt-in | Skip to dashboard | none | Skipping must be as easy as accepting. |
| `Auth/Login` | Sign in | Sign In | — | `max-w-md`. Remembers device. |
| `Auth/Register` | Create account | Create account | — | `max-w-md`. Redirects into rung 5, not to `/`. |

### 9.2 Learner — shell

**The dashboard is interest-first.** A learner picked one certification; the
screen is built around that choice and nothing else. This is the one screen
where "show everything" is a defect.

| Order | Block | Rule |
|---|---|---|
| 1 | **Resume** *(only if a session is in flight)* | Outranks everything. Most recent real action. |
| 2 | **Next up** | The single outstanding session in the focused track. Green when startable, **amber when gated** with the reason. Primary CTA. |
| 3 | **Progress strip** | Three figures — sessions done/total, complete %, θ. `tabular-nums`. Not a grid of metric cards. |
| 4 | **Related certifications** | At most 6, one line each. Ranked: same cert → same `cert_key` family → same region. |
| 5 | **Explore other certifications** | Universal search over the whole catalog, debounced. The *only* route to the rest. |
| 6 | **Browse the full catalog** | Collapsed disclosure. A destination, not the landing view. |

**Rules:**
- The `<h1>` is the chosen certification. Never "Choose your certification".
- No global catalog statistics. "205 certifications" is not a learner achievement.
- No focus set is its own lean state with one CTA, not the catalog wall.
- The dashboard must not duplicate the topbar Omnibox. One search, one purpose.
- Decorative per-tile accents, monograms and per-tile θ are **forbidden**. A tile
  is a link with a name and a number, nothing else.

| Screen | Purpose | P | D | Notes |
|---|---|---|---|---|
| `Dashboard` | Standing + next action | Next up / Resume | `focus` (with `next_session`, `cohort`), `continueSession`, `catalogTotal` | See the block order above. |
| `Track` | Session ladder for one cert | Start next session | track, sessions, domain mastery, remediation | Drill-down. Must use `Breadcrumbs`. |
| `Cert` | One certification overview | — | cert, approval state | Gated state is amber and explicit. |
| `Profile` | Identity + focus | Save | user, preferences | |
| `Settings` | Preferences | Save | user, preferences | |
| `Result` | Assessment outcome | View certificate | attempt, answers, history | Dual state: passed (certificate CTA) / not passed (remediation CTA). |
| `Certificate` | Issued credential | Scan to verify | certificate | Show credential ID prominently; QR is secondary. |

### 9.3 Assessment — full bleed, deliberately minimal chrome

| Screen | Purpose | P | D | Notes |
|---|---|---|---|---|
| `Exam` | Take the assessment | Submit | attempt, question, session | The most complex screen. Instructions → question → complete. Timer, flag, review. `sticky` controls. **Zero nav.** |

**Rule:** the exam screen may not show the sidebar, omnibox, or any exit that
loses progress. Leaving mid-attempt is a warning state, never a link.

### 9.4 Public

| Screen | Purpose | P | D | Notes |
|---|---|---|---|---|
| `Verify` | Validate a credential | — | certificate | Two states: verified (sky) and not-found (red). Must be legible to a non-user. |

### 9.5 Staff — shell

| Screen | Purpose | P | D | Notes |
|---|---|---|---|---|
| `AdminDashboard` | Learner operations | Control Center / Import | stats | Entry to all staff work. |
| `Admin` | Control center | Approve | approvals, tracks, taxonomy | Approve/reject with notes. Destructive confirm. |
| `ContentImport` | Bulk question import | Import | sessions | **Full bleed today** — inconsistent; staff need the shell to navigate. |
| `Reports/Approvals` | Approval queue | View | approvals | |
| `Reports/Attempts` | Attempt audit | — | attempts | |
| `Reports/Learners` | Learner roster | — | learners | |
| `Reports/Sessions` | Session inventory | — | sessions | |
| `Reports/Tracks` | Track inventory | — | tracks | |
| `Reports/Questions` | Question library | — | questions | |

**Rule:** all six report screens share `ReportHeader` + `StatStrip` and must
look identical in chrome. Divergence between them is a defect, not variety.

---

## 10. Content rules

- **No placeholder strings.** `ScreenCrawlTest` fails the build on
  `TODO`, `Lorem`, `placeholder`, `coming soon`, `TBD`.
- **No dead CTAs.** Every `<a>`/`<button>` resolves. Zero tolerance — a control
  that goes nowhere is worse than an absent control.
- **Real numbers.** The catalog says 205 certifications because there are 205.
  Never a hardcoded count that drifts from the DB.
- **Copy states the user's standing.** "You've passed 3 of 12 sessions", not
  "Good job!".
- **Errors name the cause.** "Could not verify this code" is correct;
  "Something went wrong" is not.

---

## 11. Gap register

Known deviations from this spec. Each is a real defect unless marked *deferred
by decision*. Ordered by user impact.

| ID | Gap | Impact | State |
|---|---|---|---|
| **G-01** | ~~14 of 28 files have no responsive prefixes.~~ Fixed at the shell (`<main>` steps `p-6`→`p-4`, capped `max-w-7xl`), so every shelled screen inherits it; Auth, Verify, ContentImport and the shared `ReportHeader` (all six reports) were stepped individually. | High — was the probable cause of "screens not optimized correctly" on mobile. | **Closed** |
| **G-02** | No `prefers-reduced-motion` handling; `Splash`/`Funnel/Auth` and the Login pulse loops animated unconditionally. | High — accessibility. | **Closed** — `motion-reduce:` guards on decorative fields; `FunnelProgress` transitions opt out. |
| **G-03** | ~~`<main>` had no max-width.~~ Now `mx-auto w-full max-w-7xl`; content stops stretching on ultrawide displays. | Medium | **Closed** |
| **G-04** | ~~`Splash` had no auto-advance.~~ Now 1800 ms with a visible Continue so it can never trap anyone. | Medium | **Closed** |
| **G-05** | ~~`Intro` described the funnel instead of the product.~~ Now the four product facts: one keystroke to 205 certs, one track at a time, θ adapts, verifiable credential. | Medium | **Closed** |
| **G-06** | Topbar **Browse rails** (region → cert dropdowns) specced in `sprint-8-1`, never built. Omnibox + dashboard search are the discovery paths. | Medium — non-keyboard users rely on search. | Open — decided in principle: search covers it |
| **G-07** | ~~Focus was a read-only strip over a 205-tile wall.~~ The dashboard is now scoped to the focus: next session, progress, ≤6 related, search. | Medium | **Closed** |
| **G-08** | `Lock` uses a text `h1`, not the wordmark asset (974 KB, unreferenced, and must be resized before use). | Low | Open |
| **G-09** | `ContentImport` is full-bleed while every other staff screen is shelled. Under §12.1 this is defensible — it is a single-task FOCUS screen — but it must stay deliberate. | Low | Accepted as FOCUS |
| **G-10** | `sprint-8-1-first-run-funnel.md` still reads `Status: planned` and names 4 files that do not exist. `app-sitemap.md` has no knowledge of the funnel or the 205 catalog. | Low — documentation debt. | Open |
| **G-11** | Dark-only with no `prefers-color-scheme` consideration. | — | Deferred by decision — see §2.1. |
| **G-12** | **Brand split-brain.** `tailwind.config.js` declared `pm.brand` `#1A9E2D` / `pm.accent` `#7CFC8F` while the app rendered stock Tailwind emerald (hue 160–163° vs the spec's 129°), and the tokens were referenced zero times. | **High** — the running app and the installed app were two different greens, and shipping a library default is why the UI read generic. | **Closed** — the spec palette is implemented as `brand-50…950` (600 = Primary, 800 = Deep), 253 classes across 32 files retinted, and the raw emerald hexes in `fault.css` replaced. `BrandPaletteTest` (9 tests) prevents regression. |
| **G-13** | No brand typeface. `font-display` was declared but unused, so text resolved to the OS UI sans — Segoe UI on Windows, SF on macOS, Roboto on Android. | Medium — three users on one dashboard saw three typefaces; invisible to a single-OS developer. | **Closed** — Inter Variable, latin subset, self-hosted and preloaded with `crossorigin`, 48,256 B (17.0% of the entry). `TypographyTest` (8 tests) guards it. |
| **G-14** | `tabular-nums` was used 0 times despite live timers, score tables and attempt counts. | Medium — see §12.3a. | **Closed** — dashboard progress strip, exam timer, `StatStrip` values and all 12 report tables carrying score/θ/duration columns. Inter ships reliable tabular figures, so the webfont also fixes it at the source. |
| **G-15** | `pm.accent`, `shadow-ring` and `font-display` were dead tokens — declared in config, referenced nowhere. The config advertised a design system the app did not implement. | Low — but it is why G-12/G-13 went unnoticed. | **Closed** — `pm.*` replaced by the real `brand` scale, `shadow-ring` removed (focus is expressed with `ring-*` at 38 call sites), `font-display` replaced by `fontFamily.sans`. No dead config remains. |

**Note on G-08:** the 974 KB logo and 478 KB `icon_1024x1024.png` are referenced
by nothing. They are repo assets, not page weight, and must not be dropped into
a screen without being resized first.

---

## 12. Design references and concept

### 12.1 The concept: FOCUS and COMMAND

Passimark has two structurally different jobs, so the system names both rather
than averaging them into one mediocre personality. Every screen is exactly one
mode, declared in its header comment.

| | **FOCUS** | **COMMAND** |
|---|---|---|
| Used by | `Exam`, `Verify` | everything else |
| Chrome | none — no sidebar, no topbar, no exit that loses work | full `DashboardLayout` |
| Type | 16px+ body, generous leading | 12–13px tables, dense rows |
| Density | one decision per screen | many, ranked, filterable |
| Accent | one, used sparingly | one, used for progress + primary CTA |
| Exit | warning state, never a link | always available |

**Rule:** when a screen's mode is ambiguous, it is misdesigned. This is the test
that resolves drift — e.g. `ContentImport` is currently full-bleed while every
other staff screen is shelled (G-09); under this concept that is either correct
(a focus screen: a single import task) or a defect, and the screen must state
which. Ambiguity is not permitted.

### 12.2 Reference products, and what to take from each

These are pattern references, not visual clones. Take the interaction and
information design; do not import a foreign personality.

| Family | Reference | Take | **Do not take** |
|---|---|---|---|
| Learner shell | **Linear** | Border-based depth instead of shadows; keyboard-first navigation; one keystroke to anywhere; density without clutter | Its coldness. A learner mid-track is anxious, not shipping code. |
| Omnibox | **Linear ⌘K / Raycast / Slack quick switcher** | Flat result list, keyboard-first, region grouping, visible "no results" | Nested menus or a browse-tree as the *only* path (see G-06) |
| Progress / track | **Salesforce Trailhead** | Prerequisite modules with explicit locked/unlocked state; earned badges as the reward moment | Badge spam. Every badge must be earned by a real gate. |
| Mastery | **Khan Academy** | Mastery bands over percentages; "what to do next" as the primary output | Percentages as the headline — they make a progressing learner feel behind |
| Assessment | **Mettl / Mercer Mettl** | Honest pre-flight instructions; real gating; candidate-facing clarity | Gamified distraction during an assessment |
| Assessment (focus) | **HackerRank / TestDome** | Minimal chrome, one question per viewport, confident progress | Timers or chrome that compete with the question |
| Admin / ops | **Stripe Dashboard** | Dense sortable tables; color used only where it carries meaning; precise figures | Learner-facing visual language. Staff UI is a different product. |
| Analytics | **Metabase / Grafana** | Column-level density; sortable, filterable, exportable | Chart-first layouts. Staff want tables they can act on. |
| Credentials | **Credly / Mozilla Open Badges** | A public verification page legible to an employer, not only a learner | Learner chrome. `/verify` must not require an account. |
| Funnel | **Stripe onboarding** | A progress checklist that visibly advances | Forcing optional steps (see Permissions rung) |
| Brand / first 3s | **Superhuman / Linear marketing** | Confidence and restraint in the opening moments | Noise. The lock rung is one action, not a cinematic. |

### 12.3 Three supporting concepts

**a) Tabular figures for all measured data.** **Now:** applied to the dashboard
progress strip, the exam timer, `StatStrip` values and all 12 report tables
carrying score/θ/duration columns — set at the table level rather than per cell.
Inter provides the figures reliably; several system stacks only do so
inconsistently, which is part of why the webfont was worth the 48 KB.
**Rule:** `tabular-nums` on every numeric cell, timer, and score. Cheapest
visible quality win available, and `TypographyTest` fails the build if the timer
or the report tables lose it.

**b) Semantic colour as a state machine.** Tighten [§2.1](#21-colour) so the
accent means exactly one thing per context:

| Colour | Means | Never used for |
|---|---|---|
| `emerald-*` | earned / passed / progress / primary action | decoration, hover filler |
| `amber-*` | locked, gated, awaiting approval | warnings about user error |
| `red-*` | failed, rejected, invalid, destructive | emphasis |
| `sky-*` | verified, system-confirmed | links |

If emerald appears on a screen where nothing is earned and no action is
primary, it is wrong.

**c) The ladder as a persistent spine.** The 7-rung onboarding ladder
([§7.1](#71-first-run-ladder-guest-dashboard)) is the app's best idea and it
currently disappears after first run. **Rule:** Track and session screens render
a compact rung indicator — where this learner stands on the ladder, what is
locked, what unlocks next. This is the one element that could be unmistakably
Passimark, and it is currently a throwaway onboarding graphic.

---

## 13. Change protocol

Any change to a screen must satisfy:

1. This spec is updated in the same commit, or the deviation is added to §11.
2. `npm run build` exits 0 and the new screen lands in its own chunk.
3. `php vendor\bin\phpunit` is green, including `ScreenCrawlTest`.
4. The screen meets §4 (responsive) and §5 (accessibility) — including states in
   §6 that did not exist before.
5. Manual check at 375px, 768px, 1280px. Static checks cannot substitute; there
   is no headless browser in this project.
