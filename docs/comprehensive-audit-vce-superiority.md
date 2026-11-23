# Passimark Comprehensive Codebase Audit, Critical Gap Analysis & VCE-Superiority Architectural Blueprint

**Document Version:** 2.0.0  
**Status:** Normative Architecture & Strategic Blueprint  
**Target Platform Standards:** Visual CertExam (Avanset VCE Suite) Parity & Beyond  
**Tech Ecosystem:** Laravel 11 / Inertia.js / React 18 / Tailwind CSS / Capacitor (Mobile) / Tauri 2.0 (Native Desktop) / Rust / SQLite  

---

## 1. Executive Summary & Strategic Context

Passimark was established to challenge the 20-year duopoly of static certification exam simulators—most notably the **Visual CertExam (VCE) Suite** (Avanset VCE Player and VCE Designer) and Pearson VUE/Prometric legacy testing engines. 

While VCE is built on an archaic, static linear testing paradigm with proprietary binary containers (`.vce`), Passimark introduces psychometrically calibrated **Item Response Theory (IRT 3-Parameter Logistic / 3PL)** adaptive testing, dynamic candidate ability ($\theta$) estimation, and automated mastery certification.

However, an exhaustive, unvarnished audit of the current Passimark codebase reveals that while the psychometric IRT backend and web-based catalog navigation are robust, **critical gaps exist across the entire stack that prevent Passimark from functioning as an indispensable, production-grade industry-standard platform**. 

### Core Audit Verdict

1. **Question Type Stagnation:** Passimark currently functions almost entirely as a **single-choice text quiz engine**. Real certification examinations (Cisco CCNA/CCNP, CompTIA Security+/Network+, AWS Solutions Architect, Microsoft Azure, NCLEX, USMLE) rely heavily on performance-based and interactive items: Drag-and-Drop matching/sequencing, Hotspot/diagram coordinate targets, Drop-down cloze sentences, Create-a-Tree topologies, Build-List and Reorder, and multi-tab Case Study Testlets. Passimark currently supports none of these interactive renderers in `Exam.jsx`.
2. **Authoring Degradation:** VCE's market dominance stems directly from **VCE Designer**, a visual desktop application allowing educators and candidates to create and edit rich exams graphically. In Passimark, authoring in `Admin.jsx` requires instructors to **manually type raw JSON arrays into an unformatted textarea**. A single syntax error crashes the form.
3. **Player Navigation Lock-in:** The exam simulator (`Exam.jsx` + `CatEngine.php`) enforces a strict forward-only progression across all modes. In Practice and Timed modes, candidates cannot freely jump between items, cannot review flagged questions before submission, and cannot return to modify answers. The question palette is comprised of static, non-interactive `<span>` elements.
4. **The Mobile Webview Illusion:** The current mobile distribution (`android/`, `ios/`, `capacitor.config.json`) is not a native or offline mobile application. It is a thin Capacitor webview hardcoded to load a local network IP (`http://192.168.0.105:8000`). If disconnected from the developer's local Wi-Fi, the app fails to load. It possesses zero local persistence, zero touch ergonomics, and no offline exam execution.
5. **Total Absence of a Native Desktop Platform:** There is currently zero desktop presence (no Electron, no Tauri, no native desktop binaries). Desktop users are forced into a responsive web page that fails to utilize multi-window capabilities, native keyboard navigation, split-screen design authoring, or OS-level file associations (`.psmk`, `.vce`).
6. **Stubbed Import Pipeline:** While Sprint 9 introduced a file format RFC and basic `.psmk` validator, all third-party format adapters in `app/Services/PassimarkImport/` (`CsvSourceAdapter`, `GiftSourceAdapter`, `MoodleXmlSourceAdapter`, `PdfDraftAdapter`, `QtiSourceAdapter`, `XlsxSourceAdapter`) and `PassimarkImportController` are **empty boilerplate stubs**. No converter exists for legacy `.vce` files.

---

## 2. Forensic Codebase Audit: The 9 Quality Dimensions

```
+---------------------------------------------------------------------------------------+
|                              PASSIMARK CODEBASE AUDIT MATRIX                          |
+----------------------+---------------------------+------------------------------------+
| Dimension            | Current Health            | Critical Gap / Risk                |
+----------------------+---------------------------+------------------------------------+
| 1. Structure         | Moderate (Modular Laravel)| Monolithic web coupling; no offline|
| 2. Code Quality      | Moderate-High (Strict PHP)| Rigid answer evaluation; no visitor|
| 3. Design & Layout   | Moderate (Tailwind Tokens)| Mobile & Desktop UX compromises    |
| 4. UI Ergonomics     | Low-Moderate              | Palette non-clickable; raw JSON    |
| 5. Engine & Approach | High (IRT 3PL MLE)        | Forward-only; no non-linear review |
| 6. Scope             | Low-Moderate              | 1 question type vs. VCE's 10 types |
| 7. Depth             | Moderate                  | Stubs in import pipeline & batches |
| 8. Outcomes          | Low (Web Prototype)       | Cannot replace VCE offline workflow|
| 9. Interoperability  | Low                       | No VCE parser; .psmk restricted    |
+----------------------+---------------------------+------------------------------------+
```

### 2.1 Structure & Architecture
- **Inertia/Laravel Tight Coupling:** The frontend (`resources/js/Pages/Passimark/Exam.jsx`) is architected as an Inertia component that relies on continuous server-side hydration (`router.visit`, `axios.post('/passimark/attempt/{attempt}/answer')`). This architectural pattern completely prevents the application from operating in zero-connectivity environments (such as flights, secure proctored test centers, or areas with unstable mobile connectivity) without a complete local backend instance.
- **Database Schema Constraints:**
  - `passimark_questions`: Contains `content` (text) and `options` (JSON). While sufficient for single/multi-choice, it lacks dedicated normalized fields or polymorphic schemas for interactive items (e.g., target bounding boxes for hotspots, source/target slot pairs for drag-drop, tree parent-child schemas, or exhibit media arrays).
  - `passimark_exams`: Modeled around fixed question counts or IRT cutoffs, but lacks session persistence for candidate pause states, scratchpad logs, or multi-section timing.
  - **Migration Duplication Incident:** During development, duplicate migrations were generated (`2026_10_25_200337_create_passimark_import_batches_table.php` and `2026_10_25_200554_create_passimark_import_batches_table.php`). In SQLite in-memory testing (`phpunit`), executing duplicate table creation crashed 384 feature tests with `table "passimark_import_batches" already exists`.
- **Packaging Specification Limits:** `app/Services/PassimarkPackage/PackagingSpec.php` explicitly hardcodes:
  ```php
  public const ITEM_TYPE = 'single_choice';
  ```
  This hard constraint prevents the `.psmk` export/import pipeline from handling any question format other than single-choice.

### 2.2 Code Quality & Implementation Logic
- **Rigid Answer Evaluation:** In `PassimarkController::answer()`, answer validation is performed via string comparison:
  ```php
  $isCorrect = count($correctKeys) > 0 && $correctKeys === $chosen;
  ```
  This is incapable of evaluating:
  - Drag-and-drop matching with partial credit.
  - Hotspot coordinate proximity within an $(x, y, r)$ radius or polygon bounding box.
  - Fill-in-the-blank regex pattern matching, numeric range tolerance ($\pm 5\%$), or case-insensitive string normalization.
  - Reorder list sequence permutations (Levenshtein distance or Kendall tau distance).
- **Incomplete Import Subsystem:** The directory `app/Services/PassimarkImport/` contains:
  - `CsvSourceAdapter.php` (Empty class constructor)
  - `GiftSourceAdapter.php` (Empty class constructor)
  - `ImportStagingService.php` (Empty class constructor)
  - `MoodleXmlSourceAdapter.php` (Empty class constructor)
  - `PdfDraftAdapter.php` (Empty class constructor)
  - `QtiSourceAdapter.php` (Empty class constructor)
  - `SourceAdapter.php` (Empty class constructor)
  - `XlsxSourceAdapter.php` (Empty class constructor)
  - `PassimarkImportController.php` (Empty single-action invoke method)
  - `routes/web.php` maps routes to nonexistent controller methods: `upload`, `extract`, `normalize`, `review`, and `export`. Accessing these routes results in an immediate HTTP 500 error (`Method does not exist`).

### 2.3 Design & Tokens
- **Brand System:** The Tailwind palette (`tailwind.config.js`) defines high-contrast tokens (`brand-50` through `brand-950`, `accent #7CFC8F`, `deep #0F172A`).
- **Surface Hierarchy:** The app relies on heavy slate shades (`bg-slate-900`, `border-slate-800`). While modern and clean, it lacks visual distinction between active examination proctoring surfaces, administrative control panels, and study review modes.
- **Absence of Light Theme Execution:** Although settings include a theme preference (`users.preferences.theme`), the application chrome is hardcoded with dark classes (`bg-slate-950 text-white`), causing theme switching to be largely ineffective across key screens.

### 2.4 Layout & Viewport Adaptability
- **Desktop Ultrawide Wastage:** On monitors exceeding 1440px width, `Exam.jsx` restricts the canvas to `max-w-6xl` (1152px) centered horizontally, leaving excessive dead margin space on the flanks. In contrast, native desktop exam software (VCE, Pearson VUE, Prometric) leverages widescreen layouts to present split-screen documentation: scenario text and network diagrams on the left, interactive question elements on the right.
- **Mobile Responsive Degradation:** On smartphone screens ($< 640\text{px}$):
  - The side panel (`<aside>`) containing the palette and details collapses below the question content.
  - The action buttons ("Submit Answer", "Flag", "Strike") require substantial vertical scrolling, breaking the candidate's focus and increasing test anxiety.
  - No swipe navigation gestures or floating ergonomic thumb controls exist.

### 2.5 UI & Candidate Interaction
- **Static Question Palette:** Lines 394–400 of `Exam.jsx` render palette numbers inside a non-interactive `<span>`:
  ```jsx
  {paletteItems.map((item) => (
    <span key={item.position} className={`flex h-7 items-center justify-center rounded text-[10px] font-semibold ${paletteStyle[item.status]}`}>
      {item.position}
    </span>
  ))}
  ```
  Candidates cannot click an item in the palette to navigate to that question.
- **Non-Functional Flag Review:** The Flagged Review modal (`reviewOpen`) only displays a list of flagged question numbers:
  ```jsx
  <span key={position} className="rounded bg-amber-400/20 px-3 py-1 text-sm font-semibold text-amber-300">Q{position}</span>
  ```
  Clicking a flagged badge does not jump to the question.
- **Primitive Calculator:** The custom shunting-yard calculator in `Exam.jsx` supports only basic arithmetic (`+`, `-`, `*`, `/`, `%`). Real certification exams (Cisco CCNA, AWS, CompTIA, CEH, Financial certs) require:
  - Bitwise operations (AND, OR, XOR, NOT, Bit-shift) for IPv4/IPv6 subnet calculation.
  - Hexadecimal, Decimal, and Binary conversions.
  - Scientific functions (logarithms, exponents, square roots).
- **Temporary Strikethrough:** Strikethroughs on options are stored in local React component state (`strikes = new Set()`) and are erased as soon as the candidate navigates to the next question.

### 2.6 Engine & Navigation Approach
- **Rigid Forward-Only Progression:** In `CatEngine::nextQuestion()`, items are delivered sequentially or by IRT Fisher information maximization. While psychometrically mandatory for Computer Adaptive Testing (CAT) to prevent ability score gaming, **linear timed exams and practice drills must support free-roam non-linear navigation**. Forcing forward-only progression across all modes violates standard testing protocols.
- **Lack of Pre-Submission Summary:** VCE and Pearson VUE present a comprehensive pre-submission review screen displaying:
  - Total Questions Answered
  - Unanswered / Skipped Questions
  - Flagged Questions for Review
  - Direct jump links to address incomplete questions before the final submission lock.
  In Passimark, the final question immediately terminates the exam or jumps directly to the result screen.

### 2.7 Scope & Depth
- **Item Bank Homogeneity:** Although the catalog contains 203 certification bundles, over 99% of the items are 4-option multiple choice. The depth of question formats that test deep technical troubleshooting, procedural sequencing, architectural design, and log analysis is completely absent.
- **Zero Offline Execution:** A candidate cannot take an exam without an active, connected web server responding to HTTP API requests.

### 2.8 Outcomes & Deliverables
- **The "VCE Killer" Gap:** In its current state, Passimark cannot replace Visual CertExam for a candidate or organization because it cannot load `.vce` files, cannot author exams visually, cannot be taken offline on a laptop or phone, and cannot deliver interactive item types.

---

## 3. Comprehensive Competitive Analysis: Passimark vs. Visual CertExam (VCE)

To surpass VCE, Passimark must match every single feature VCE provides while eliminating its legacy architectural flaws.

### 3.1 Feature Comparison Matrix

| Functional Capability | Visual CertExam (VCE) | Passimark Current State | Target Passimark State (Post-Sprint 18) |
| :--- | :--- | :--- | :--- |
| **Adaptive Testing (CAT)** | ❌ None (Fixed linear order) | ✅ Full IRT 3PL ($\theta, a, b, c$) | **✅ Advanced IRT 3PL + CAT Early Termination + Multi-Stage Adaptive (MST)** |
| **Single Choice (Radio)** | ✅ Yes | ✅ Yes | **✅ Yes (with Rich Text, Code Highlighting, LaTeX)** |
| **Multiple Choice (Checkbox)** | ✅ Yes (Exact bounds) | ⚠️ Partial (Basic array check) | **✅ Yes (Strict bounds, partial scoring, distractor explanations)** |
| **Drag & Drop Matching** | ✅ Yes (Source to Target) | ❌ None | **✅ Yes (Touch & Mouse, Canvas connectors, snap-to-grid)** |
| **Build List & Reorder** | ✅ Yes (Move up/down) | ❌ None | **✅ Yes (Interactive drag-handle, keyboard shortcuts)** |
| **Point & Click (Hotspot)** | ✅ Yes (Clickable image) | ❌ None | **✅ Yes (SVG coordinate mapping, polygon boundaries, zoomable)** |
| **Drop-Down Cloze** | ✅ Yes (Inline select) | ❌ None | **✅ Yes (Inline select tokens, multi-blank scenarios)** |
| **Create-a-Tree** | ✅ Yes (Hierarchical nodes)| ❌ None | **✅ Yes (Interactive tree builder, DAG graph validation)** |
| **Case Study / Testlets** | ⚠️ Basic (Text tabs) | ❌ None | **✅ Yes (Multi-tab exhibit viewer: PDFs, topologies, terminal logs)** |
| **Exam Player Navigation** | ✅ Free-roam grid, jump | ❌ Forward-only | **✅ Dual-Mode: Strict Forward CAT + Full Free-Roam Linear/Practice** |
| **Pre-Submit Review Screen** | ✅ Yes (Answered/Flagged) | ❌ None | **✅ Yes (Grid filter: All, Incomplete, Flagged with 1-click jump)** |
| **Mark for Review** | ✅ Yes (Persistent) | ⚠️ Visual icon only | **✅ Yes (Persistent state, review modal jump, palette sync)** |
| **Pause & Resume Exam** | ✅ Yes (Stateful timer) | ❌ None (Timer continues) | **✅ Yes (Encrypted attempt snapshot, anti-cheat timestamp validation)** |
| **Specialized Calculators** | ⚠️ Standard OS calc call | ⚠️ Basic 4-op arithmetic | **✅ Yes (Scientific, Programmer Bitwise/Subnetting, Financial)** |
| **Exam Designer (Authoring)** | ✅ Full Desktop GUI | ❌ Raw JSON textarea | **✅ Passimark Designer: Split-pane WYSIWYG, live emulator preview** |
| **Content File Container** | ⚠️ `.vce` (Proprietary binary)| ⚠️ `.psmk` (Single-choice only)| **✅ `.psmk` v2.0 (Open ZIP, JSON Schema, Media Assets, SHA-256)** |
| **VCE File Import** | ❌ Native only | ❌ None | **✅ Native VCE v4/v5 binary parser & converter to `.psmk`** |
| **Mobile Application** | ⚠️ Outdated paid iOS/Android| ❌ Webview to local IP | **✅ Native Offline App (Capacitor + SQLite, touch gestures, haptics)** |
| **Desktop Application** | ✅ Windows MFC (Legacy) | ❌ None | **✅ Native Desktop (Tauri 2.0 / Rust, multi-window, OS integration)** |
| **Psychometric Analytics** | ❌ Percentage score only | ⚠️ Basic theta history | **✅ Domain Mastery Radar, IRT $\theta$ curve, Calibration feedback** |
| **Spaced Repetition & AI** | ❌ None | ❌ None | **✅ Leitner flashcard review, AI distractor rationale generation** |

---

## 4. Architectural Blueprints for Superiority

```
                                  PASSIMARK UNIFIED ECOSYSTEM
                                 
      +-----------------------------------------------------------------------------------+
      |                                  PASSIMARK CLOUD                                  |
      |   Laravel 11 Backend API · PostgreSQL/SQLite · Redis · IRT 3PL Psychometric Core  |
      +-----------------------------------------------------------------------------------+
                                   ▲                                 ▲
                     REST / JSON-API Sync               REST / JSON-API Sync
                                   ▼                                 ▼
+----------------------------------------------------+   +----------------------------------------------------+
|             NATIVE MOBILE CLIENT                   |   |            NATIVE DESKTOP CLIENT                   |
|   Capacitor 8 + React 18 + Tailwind + OP-SQLite    |   |   Tauri 2.0 (Rust) + React 18 + Embedded SQLite    |
|   - Offline-First Local Database                   |   |   - Multi-Window Exhibit & Scratchpad Support      |
|   - Touch Ergonomics, Gestures & Haptics           |   |   - Passimark Designer (Split-Screen WYSIWYG)      |
|   - Bottom Sheet Palette & Zoomable Exhibits       |   |   - OS File Associations (.psmk, .vce) & Menus     |
|   - Background Cloud Sync Engine                   |   |   - High-Speed Keyboard Shortcuts (1-9, Ctrl+F)    |
+----------------------------------------------------+   +----------------------------------------------------+
```

---

## 5. Mobile Application Blueprint: True Offline-First Architecture

The current mobile build is merely a webview pointing to a development server. The new mobile client must be a **first-class, standalone offline engine**.

### 5.1 Mobile Technology Stack
- **Container Framework:** Capacitor 8.x with native Android (Java/Kotlin) and iOS (Swift) bridges.
- **Local Embedded Database:** `@capacitor-community/sqlite` (or `op-sqlite`) running SQLite 3 with WAL (Write-Ahead Logging) mode enabled.
- **Client Application Layer:** React 18 SPA (compiled directly into the local mobile binary bundle, eliminating all network loading dependencies).
- **State & Offline Sync:** Zustand for in-memory exam state; TanStack Query for cache synchronization with background offline sync queues.
- **Touch & Gesture Engine:** Lucide React, Framer Motion touch gestures, and `@capacitor/haptics` for tactile tactile confirmation.

### 5.2 Mobile UI & Touch Ergonomics
- **Safe Area & Thumb Zone Optimization:**
  - Key actions (Select, Next, Flag, Strike) are anchored in the bottom 40% of the screen ("The Thumb Zone").
  - Header displays a non-intrusive sticky HUD: elapsed/remaining time, question index pill, and flag toggle.
- **Bottom Sheet Question Palette:**
  - Swiping up from the bottom drawer reveals the full question matrix palette.
  - Tapping any question number instantly navigates to that question (in linear/practice modes).
  - Visual status color codes: Green (Answered), Amber (Flagged), Slate (Unanswered), Ring (Current).
- **Swipe Gestures:**
  - Horizontal swipe left: Advance to next question.
  - Horizontal swipe right: Return to previous question (in free-roam modes).
  - Two-finger tap: Toggle flag on active item.
- **Zoomable Diagram & Exhibit Viewer:**
  - Pinch-to-zoom and pan gesture support for complex network topologies, electrical schematics, and clinical charts.
  - Double-tap to reset zoom.
- **Tactile Haptic Feedback:**
  - Light haptic pulse on selecting an option.
  - Medium haptic pulse on striking through a distractor.
  - Distinct warning vibration when the countdown reaches 5 minutes and 1 minute.
- **True Standalone Capability:**
  - Users can download or airdrop `.psmk` bundles directly to their mobile device.
  - The app registers as an OS handler for `.psmk` files; opening a bundle imports it into the local SQLite database instantly with zero network required.

---

## 6. Native Desktop Application Blueprint: Ergonomic & Authoring Powerhouse

A native desktop app must not be a stretched mobile interface. Desktop applications have distinct physical ergonomics: wide displays, multi-monitor setups, precision mouse pointers, physical keyboards, and deep OS integration.

### 6.1 Desktop Technology Stack
- **Framework:** **Tauri 2.0** (Rust core + Webview frontend) or **Electron 32+**.
  - *Recommendation:* Tauri 2.0 delivers 15MB binary footprints, zero background memory bloat (< 40MB RAM vs. Electron's 250MB+), native Rust speed for cryptography/ZIP packaging, and cross-platform compilation (`.msi`/`.exe` for Windows, `.dmg` for macOS, `.deb`/`.AppImage` for Linux).
- **Local Storage:** Embedded SQLite with memory-mapped I/O (`mmap_size = 268435456`) capable of querying 500,000+ item banks with sub-millisecond latency.
- **OS File Association:** System-level registry bindings for `.psmk`, `.psme`, and `.vce` files. Double-clicking any assessment file in Windows Explorer or macOS Finder opens the Passimark Player immediately.

### 6.2 Desktop-Specific Ergonomic Advantages
- **Multi-Window Support:**
  - **Detachable Exhibit Window:** Candidates with dual-monitor setups can detach case study exhibits, network topologies, or code listings onto a secondary monitor while maintaining the question and answer choices on the primary display.
  - **Detachable Floating Scratchpad / Calculator:** Freely positionable across the screen without obstructing question stems.
- **Desktop Keyboard Navigation (VCE-Parity):**
  - Keys `1` – `9` or `A` – `I`: Select corresponding answer option.
  - `Enter` / `Space`: Confirm answer / Next question.
  - `Backspace` / `Left Arrow`: Previous question (in linear modes).
  - `Ctrl + F` / `Cmd + F`: Toggle Mark for Review.
  - `Ctrl + S` / `Cmd + S`: Strike-through selected distractor.
  - `Ctrl + P` / `Cmd + P`: Toggle Navigation Palette grid.
  - `Esc`: Pause examination / Open break dialog.
- **Passimark Designer: Split-Screen Visual Authoring:**
  - **Left Pane:** WYSIWYG Question Editor (Markdown/HTML, LaTeX formula editor, exhibit uploader, interactive type selector).
  - **Right Pane:** Real-Time Interactive Simulator Preview (Candidate View). Edits in the left pane reflect in real-time in the interactive player preview without page reload.
  - **Inspector Drawer:** IRT 3PL parameter calibration ($a, b, c$), Bloom taxonomy selectors, domain tags, authoring references, and difficulty sliders.
- **Native Printing & Score Export:**
  - Direct integration with OS print spoolers to generate formal PDF score transcripts with cryptographic QR verification stamps.

---

## 7. The Universal Question Taxonomy: 10 Interactive Types

To surpass VCE, Passimark must support the complete taxonomy of modern assessment item formats:

```
+-----------------------------------------------------------------------------------+
|                        PASSIMARK QUESTION TAXONOMY (10 TYPES)                     |
+-----+-------------------------+---------------------------------------------------+
| #   | Item Type               | Interaction Paradigm                              |
+-----+-------------------------+---------------------------------------------------+
| 1.  | Single Choice (Radio)   | 1-of-N selection with strikethrough               |
| 2.  | Multiple Choice (Check) | K-of-N selection with exact or min/max bounds     |
| 3.  | Drag & Drop Matching    | Drag items from source pool to target slots       |
| 4.  | Build List & Reorder    | Select items from source pool and arrange order   |
| 5.  | Point & Click (Hotspot) | Click precise coordinate/polygon target on image  |
| 6.  | Drop-Down Cloze         | Inline dropdown selectors within text paragraph   |
| 7.  | Create-a-Tree           | Build hierarchical parent-child node tree         |
| 8.  | Fill in the Blank       | Text entry with regex, numeric & case rules       |
| 9.  | Case Study / Testlet    | Multi-tab exhibit scenario with grouped sub-items |
| 10. | Terminal Simulation     | Interactive CLI emulator evaluating shell commands|
+-----+-------------------------+---------------------------------------------------+
```

Detailed JSON schema definitions and evaluation rules for all 10 types are specified in [CONTENT.md](file:///D:/projects/passimark/CONTENT.md).

---

## 8. Summary of Strategic Next Steps

1. **Deploy Remediation Sprint (Sprint 10):** Build the interactive item engine and polymorphic `AnswerEvaluator`.
2. **Implement VCE & Universal Importer (Sprint 14):** Build the `.vce` binary parser to enable instant migration of massive existing exam repositories into the `.psmk` ecosystem.
3. **Roll out Native Desktop & Offline Mobile Architectures (Sprints 15-17):** Deliver dedicated desktop and mobile apps engineered specifically for their physical platforms.
4. **Publish Authoritative Rules & Specifications:** Establish [AGENTS.md](file:///D:/projects/passimark/AGENTS.md) and [CONTENT.md](file:///D:/projects/passimark/CONTENT.md) as immutable engineering standards.
