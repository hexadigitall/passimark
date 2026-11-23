# AGENTS.md — Autonomous Engineering & Architectural Mandate

**Brand:** Hexadigitall Inc. / Passimark  
**Target:** The Indispensable, Psychometrically Grounded Adaptive Certification Platform (Surpassing Visual CertExam Suite)  
**Scope:** Web App, Native Mobile (Capacitor/SQLite), Native Desktop (Tauri 2.0/Rust)  

---

## 1. Prime Directive & Operational Rules

Every autonomous agent, LLM assistant, and human engineer contributing to this repository must strictly adhere to the standards outlined in this document. This file is the **immutable single source of truth** for architectural decisions, coding patterns, security invariants, and testing standards.

### Non-Negotiable Invariants

1. **VCE Superiority Invariant:** Every assessment feature must meet or exceed the capability of Visual CertExam (VCE Player & VCE Designer) while leveraging modern psychometrics (IRT 3PL), intelligent spaced repetition, and cloud-to-device synchronization.
2. **Dual-Platform Physical Parity (No Stretched Ports):**
   - **Mobile** must be built for touch ergonomics: thumb zones, bottom sheets, swipe gestures, pinch-to-zoom diagrams, tactile haptics, and true offline SQLite persistence.
   - **Desktop** must be built for desktop power: multi-window exhibit detachment, side-by-side split-screen WYSIWYG authoring, native OS menus, file association (`.psmk`, `.vce`), and physical keyboard navigation (1-9, Ctrl+F, Ctrl+S, Esc).
   - Under no circumstances should a mobile layout be stretched onto a desktop monitor, nor should a desktop interface be shrunk into a mobile webview.
3. **No Answer Leakage (Candidate Security):** Correct answers (`correct_key`, `is_correct`, `options[].is_correct`) must never be delivered to the client during active CAT or Timed assessments. Answer evaluation is strictly server-side (or executed locally in an encrypted client runtime) until completion or Practice mode immediate feedback.
4. **Zero-Regression Verification:** No pull request, commit, or task is complete without:
   - Full PHPUnit test suite passing (`php artisan test` or `.\vendor\bin\phpunit.bat`).
   - Clean production asset compilation (`npm run build`).
   - Zero linter or syntax errors (`php -l`).
   - Verification that migrations are idempotent and free of duplicate table definitions.

---

## 2. Technology Stacks & Architecture

```
+---------------------------------------------------------------------------------------+
|                                    PASSIMARK STACK MATRIX                             |
+-------------------+-----------------------------------+-------------------------------+
| Layer             | Technology / Library              | Purpose                       |
+-------------------+-----------------------------------+-------------------------------+
| Backend Core      | PHP 8.2+, Laravel 11              | REST API, Auth, IRT Engine    |
| Psychometrics     | App\Services\Irt\Irt3PL           | Newton-Raphson MLE, Fisher CAT|
| Database (Server) | PostgreSQL (Prod) / SQLite (Dev)  | Normalized Content & Attempt  |
| Frontend Shell    | Inertia.js v1, React 18           | SPA Reactive Rendering        |
| Styling & Tokens  | Tailwind CSS 3.4                  | Passimark High-Contrast Scale |
| Icons & Symbols   | Lucide React                      | Consistent SVG Icons          |
| Math / Equations  | KaTeX                             | LaTeX Mathematical Rendering  |
| Code Formatting   | PrismJS / Highlight.js            | Syntax Highlighting in Items  |
| Mobile Runtime    | Capacitor 8 + Local SQLite        | Offline Mobile Native Engine  |
| Desktop Runtime   | Tauri 2.0 + Rust + Local SQLite   | Native Multi-Window Desktop   |
| Assessment Package| .psmk (ZIP + JSON Schema)         | Portable Exam Container       |
+-------------------+-----------------------------------+-------------------------------+
```

### Adopted Key Platform Decisions (Immutable Rationale)

1. **Desktop Shell — Tauri 2.0 (Rust Core) Formally Adopted:**
   - *Rationale:* Electron's 250MB+ memory footprint and bundled Chromium instances make running background proctoring and exam simulators sluggish on modest hardware. Tauri 2.0 compiles down to a native 15MB binary with < 40MB RAM usage, provides instantaneous cold-boot (< 400ms), native OS menus, multi-window detachment without process overhead, and seamless integration with the host OS file associations (`.psmk`, `.vce`).
2. **Mobile Persistence — `@capacitor-community/sqlite` Formally Adopted:**
   - *Rationale:* Provides reliable SQLite 3 with SQLCipher 256-bit AES encryption across iOS and Android without native JSI bridge instability, enabling zero-network exam simulation, background synchronization, and secure offline storage of question banks and candidate attempts.

---

## 3. Directory Layout & Code Organization

```
passimark/
├── app/
│   ├── Console/Commands/        # Artisan CLI tools (Seeders, Package export/import)
│   ├── Http/Controllers/        # Controllers (Lean dispatchers; business logic in Services)
│   ├── Models/                  # Eloquent models with strict casts and relationships
│   ├── Services/
│   │   ├── Irt/                 # Psychometrics: Irt3PL (MLE, Fisher Information)
│   │   ├── AnswerEvaluator/     # Polymorphic evaluation for all 10 question types
│   │   ├── PassimarkPackage/    # .psmk ZIP packaging, validation, export, import
│   │   ├── PassimarkImport/     # Third-party format adapters (CSV, GIFT, QTI, VCE)
│   │   ├── Curriculum.php       # Progression, session unlocking, gating
│   │   └── CertificateIssuer.php# Deterministic credential minting & verification
├── database/
│   ├── migrations/              # Versioned schema migrations (NEVER duplicate table names)
│   ├── seeders/
│   │   ├── CISSPBundleSeeder.php# Flagship authored textbook bundle
│   │   └── data/bundles/        # 203 authored certification JSON bundles
├── resources/
│   ├── js/
│   │   ├── Components/
│   │   │   ├── Items/           # Renderers for all 10 question types (DragDrop, Hotspot, etc.)
│   │   │   ├── Chrome/          # Palette, Timer, Calculator, Scratchpad, BottomSheet
│   │   │   └── Exhibits/        # Multi-tab scenario, diagram zoomer, terminal viewer
│   │   ├── Layouts/             # DashboardLayout, ExamLayout, DesignerLayout
│   │   └── Pages/
│   │       ├── Passimark/       # Dashboard, Track, Exam, Result, Certificate, Profile
│   │       └── Admin/           # Operations Dashboard, Reports, Designer GUI
├── src-tauri/                   # Tauri 2.0 Rust desktop core & multi-window management
├── android/                     # Capacitor native Android shell
├── ios/                         # Capacitor native iOS shell
├── docs/                        # Architecture specs, sprint trackers, audit reports
├── AGENTS.md                    # This file (Engineering directives)
└── CONTENT.md                   # Normative assessment schema & question taxonomy
```

---

## 4. Question Taxonomy Standards

Passimark supports **10 distinct item formats**. Every agent authoring or touching question evaluation logic must support:

1. `single_choice`: Standard 1-of-N radio button selection.
2. `multiple_choice`: K-of-N selection with exact or bounded constraints.
3. `drag_drop`: Source cards dragged into target slots.
4. `build_list`: Dragging items from a bank and arranging them in correct sequence.
5. `hotspot`: Clicking $(x, y)$ coordinates on an exhibit diagram.
6. `cloze`: Dropdown selectors inline within text passages.
7. `create_tree`: Hierarchical parent-child node building.
8. `fill_blank`: Free text entry with regex, numeric tolerance, and case normalization.
9. `case_study`: Multi-tab exhibit dossier (scenario, topology, logs) with linked sub-items.
10. `simulation`: Interactive terminal or console command prompt emulator.

*Full JSON specifications and scoring algorithms are defined in [CONTENT.md](file:///D:/projects/passimark/CONTENT.md).*

---

## 5. Security & Psychometric Invariants

1. **IRT Ability Bounds:** Candidate ability $\theta$ is clamped between $[-3.0, +3.0]$. Discrimination $a \in [0.01, 3.0]$, difficulty $b \in [-3.0, 3.0]$, guessing $c \in [0.0, 0.99]$.
2. **Deterministic Credentialing:** Certificates are minted exclusively upon passing approved `final` milestones with $\theta \ge \theta_{\text{required}}$. Format: `PMK-{CERTCODE}-{YEAR}-{HEX8}`.
3. **Anti-Cheat Timestamp Validation:** In timed mode, client-submitted `time_spent` is cross-verified against server attempt creation timestamps to detect client clock tampering.
4. **Idempotent Operations:** All seeders, package imports, and batch processors must be strictly idempotent. Re-running a command must produce zero duplicate rows and zero integrity exceptions.

---

## 6. Development & Verification Workflow

Before reporting any task complete or committing changes, you must execute the following checklist in sequence:

```bash
# 1. PHP Syntax & Linting Check
php -l <modified-file>

# 2. Database Migration Integrity
php artisan migrate:status

# 3. Full Automated PHPUnit Test Suite
.\vendor\bin\phpunit.bat

# 4. Frontend Compilation & Asset Integrity
npm run build
```

**Zero Warnings, Zero Errors.** If any assertion fails or an asset throws a 404, diagnose the root cause immediately before proceeding.
