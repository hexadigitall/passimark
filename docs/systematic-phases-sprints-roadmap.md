# Passimark: Systematic Phases, Sprints & Engineering Roadmap

**Roadmap Horizon:** Sprints 10 through 18  
**Mission:** Transform Passimark into the premier, industry-standard adaptive certification ecosystem, exceeding Visual CertExam (VCE) Suite across web, native mobile, and native desktop platforms.

---

## Roadmap Phases Overview

```
 PHASE A (Sprints 10-11)        PHASE B (Sprint 12)            PHASE C (Sprints 13-14)
+-----------------------+    +-----------------------+    +-----------------------+
| Interactive Item      | -> | Exam Player Parity &  | -> | Passimark Designer    |
| Taxonomy (10 Types)   |    | Non-Linear Navigation |    | GUI & VCE Importer    |
+-----------------------+    +-----------------------+    +-----------------------+
            |                            |                            |
            v                            v                            v
 PHASE D (Sprints 15-16)        PHASE E (Sprint 17)            PHASE F (Sprint 18)
+-----------------------+    +-----------------------+    +-----------------------+
| True Offline-First    | -> | Native Desktop App    | -> | Spaced Repetition,    |
| Mobile App (SQLite)   |    | (Tauri 2.0 / Rust)    |    | AI & Global Release   |
+-----------------------+    +-----------------------+    +-----------------------+
```

---

## Phase A: Interactive Item Taxonomy & Evaluation Engine

### Sprint 10: Multi-Format Schema Expansion & Core Interactive Types
**Goal:** Expand the database schema, engineer the server-side polymorphic `AnswerEvaluator`, and build rich frontend renderers for Drag & Drop, Multi-Select, and Hotspot items.

#### S10.1 Database Schema Migration (`expand_item_types_table.php`)
- [x] Add `type` column to `passimark_questions` (`single_choice`, `multiple_choice`, `drag_drop`, `build_list`, `hotspot`, `cloze`, `create_tree`, `fill_blank`, `case_study`, `simulation`).
- [x] Add `interactive_payload` (JSON) to `passimark_questions` for target coordinates, slots, and trees.
- [x] Add `exhibits` (JSON) for media URLs, diagrams, and tabbed attachments.
- [x] Update `PackagingSpec.php` to accept all 10 question types.

#### S10.2 Server-Side Answer Evaluator (`app/Services/AnswerEvaluator.php`)
- [x] Implement polymorphic evaluation interface `EvaluatesItemType`.
- [x] `MultipleChoiceEvaluator`: Support exact match, minimum required bounds, and partial scoring.
- [x] `DragDropEvaluator`: Evaluate key-value slot pairs with partial scoring weights.
- [x] `HotspotEvaluator`: Evaluate candidate coordinate `(x, y)` against defined circular `(x, y, radius)` or polygonal coordinate bounds.

#### S10.3 Frontend Interactive Renderers (`resources/js/Components/Items/`)
- [x] `QuestionMultiSelect.jsx`: Checkbox matrix with selection counter and limit enforcement.
- [x] `QuestionDragDrop.jsx`: HTML5 Drag-and-Drop and touch-friendly slot placement container.
- [x] `QuestionHotspot.jsx`: Responsive SVG canvas overlay mapping click coordinates on exhibit diagrams.

#### Acceptance Criteria & Verification
- Unit test suite (`tests/Unit/AnswerEvaluatorTest.php`) verifying 100% precision on coordinate proximity and slot matching.
- Visual integration in `Exam.jsx` rendering Drag & Drop and Hotspot questions seamlessly.

---

### Sprint 11: Advanced Item Types & Case Study Testlet Engine
**Goal:** Deliver Build-List & Reorder, Drop-Down Cloze, Create-a-Tree, Fill-in-the-Blank, and multi-tab Case Study scenarios.

#### S11.1 Advanced Question Types
- [x] `QuestionReorder.jsx`: Drag-to-order list component with tactile up/down controls.
- [x] `QuestionCloze.jsx`: Inline `<select>` dropdowns nested naturally within paragraph text.
- [x] `QuestionCreateTree.jsx`: Interactive hierarchical tree node connector.
- [x] `QuestionFillBlank.jsx`: Text input with regex normalization, numeric tolerance, and case rules.

#### S11.2 Case Study & Split Exhibit Panel (`CaseStudyViewer.jsx`)
- [x] Multi-tab exhibit container: Scenario Overview, Network Topologies, Server Logs, Corporate Policies.
- [x] Synchronized candidate notes drawer per case study.
- [x] Sticky question drawer permitting side-by-side reference on desktop viewports.

#### Acceptance Criteria & Verification
- Automated feature tests verifying parsing and evaluation of all 10 item types.
- Zero visual overflow on mobile and desktop viewports.

---

## Phase B: Exam Player Parity & Non-Linear Navigation

### Sprint 12: Dual-Mode Navigation, Stateful Timer & Proctoring Chrome
**Goal:** Bring `Exam.jsx` to complete functional parity with VCE Player and Pearson VUE.

#### S12.1 Non-Linear Free-Roam Navigation
- [x] Decouple linear forward-only logic: Enable free question jumping for Timed and Practice modes while preserving strict forward-only progression for adaptive CAT mode.
- [x] **Interactive Palette Grid:** Convert palette numbers into actionable buttons that jump directly to any question.
- [x] Palette filtering tabs: Show All, Unanswered Only, Flagged Only.

#### S12.2 Candidate Proctoring & Aids
- [x] **Stateful Pause & Resume:** Candidate can pause timed exams with secure encrypted attempt snapshots.
- [x] **Persistent Strikethrough & Highlighting:** Strikethrough options and text highlight ranges persist across navigation.
- [x] **Advanced Calculator:** Expand the built-in calculator with Subnetting (IPv4/IPv6 CIDR calculations), Bitwise logic (AND/OR/XOR), and Scientific functions.
- [x] **Pre-Submit Review Screen:** Comprehensive summary modal showing total answered, unanswered, and flagged questions with 1-click jump links before final submission.

#### Acceptance Criteria & Verification
- E2E testing of 100-question practice exams verifying free navigation, pause/resume state retention, and pre-submit review integrity.

---

## Phase C: Passimark Designer GUI & Universal Importer

### Sprint 13: Passimark Designer (Visual Desktop/Web Authoring GUI)
**Goal:** Replace raw JSON textareas with a rich, professional WYSIWYG authoring suite matching and exceeding VCE Designer.

#### S13.1 Designer Core Interface (`Pages/Admin/Designer.jsx`)
- [ ] Split-pane layout: Visual editor on the left, live candidate emulator on the right.
- [ ] Visual question type selector (Single Choice, Multi-Select, Drag & Drop, Hotspot, etc.).
- [ ] Rich-Text WYSIWYG editor (Markdown, KaTeX mathematical equations, syntax-highlighted code blocks).

#### S13.2 Interactive Question Designers
- [ ] Drag-and-Drop pairing editor: Add source items and define target drop zones visually.
- [ ] Hotspot canvas builder: Upload diagram and click-to-draw coordinate target bounding boxes.
- [ ] Tree & Reorder editor: Build visual nodes and specify correct sequence orders.
- [ ] Psychometric Calibration Inspector: Configure IRT 3PL parameters ($a, b, c$), Bloom taxonomy, domain tags, and references.

#### Acceptance Criteria & Verification
- An educator can author, calibrate, and live-preview all 10 question types without writing a single line of raw JSON.

---

### Sprint 14: Universal Import Subsystem & Legacy VCE Converter (COMPLETED)
**Goal:** Implement the staged import pipeline and build a converter to migrate legacy `.vce` files into `.psmk` packages.

#### S14.1 Third-Party Format Adapters
- [x] Complete `CsvSourceAdapter.php`, `GiftSourceAdapter.php`, `MoodleXmlSourceAdapter.php`, and `QtiSourceAdapter.php`.
- [x] Build automated staging workflow: Upload $\rightarrow$ Parse $\rightarrow$ Normalize $\rightarrow$ Validate $\rightarrow$ Export to `.psmk`.

#### S14.2 Legacy `.vce` Binary Parser (`app/Services/PassimarkImport/VceSourceAdapter.php`)
- [x] Reverse-engineer and parse VCE binary containers (extracting question stems, exhibits, options, correct answers, and author notes).
- [x] Map VCE question types directly to native Passimark item schemas.
- [x] Automatic ZIP asset extraction for embedded diagrams and images.

#### Acceptance Criteria & Verification
- [x] Uploading a standard `.vce` file parses all questions, extracts all diagram assets, and creates a valid `.psmk` bundle that loads directly in the simulator.
- [x] Comprehensive test coverage in `tests/Feature/UniversalImporterTest.php` (7 tests / 72 assertions 100% green).
- [x] Full production compilation verified (`npm run build`).

---

## Phase D: True Offline-First Mobile Application

### Sprint 15: Mobile Offline Architecture & Embedded SQLite Engine (COMPLETED)
**Goal:** Eliminate webview server dependencies and transform the mobile app into a standalone offline exam runner.

#### S15.1 Embedded Mobile Database
- [x] Integrate `@capacitor-community/sqlite` with pre-compiled schema migrations.
- [x] Local question bank caching: Store enrolled tracks, sessions, questions, and exhibits locally on device flash storage.
- [x] Local exam execution: `CatEngine` and `AnswerEvaluator` ported to client-side JavaScript for zero-latency, 100% offline exam scoring.

#### S15.2 Bidirectional Cloud Synchronization
- [x] Background sync queue: Queue completed attempts, answer logs, and timestamps locally; automatically sync to remote Laravel backend when connectivity is restored.
- [x] Conflict resolution: Deterministic timestamp-based merging and anti-cheat clock verification.

#### Acceptance Criteria & Verification
- [x] Taking an entire adaptive or practice exam in airplane mode, rebooting the device, reconnecting, and verifying that progress, scores, and credentials synchronize flawlessly.
- [x] Automated test coverage in `tests/Feature/MobileSyncTest.php` (4 tests / 31 assertions 100% green).
- [x] Clean asset compilation with `npm run build` (2,035 modules in 7.90s).

---

### Sprint 16: Mobile Touch Ergonomics & Candidate Comfort
**Goal:** Redesign mobile exam interactions for optimal comfort, focus, and speed.

#### S16.1 Touch Ergonomics
- [x] Thumb-zone control bar: Primary actions positioned within easy one-handed reach.
- [x] Bottom-sheet question palette with gesture drag-to-expand.
- [x] Horizontal swipe gestures for navigating between questions.
- [x] Pinch-and-pan diagram exhibit viewer with double-tap zoom reset.
- [x] Tactile haptic feedback on choice selection, strikethrough, and countdown warnings.
- [x] True OLED black dark mode (`#000000`) for maximum battery conservation.

#### Acceptance Criteria & Verification
- [x] Zero layout shift across modern iOS and Android screen aspect ratios (`lg:hidden` thumb zone and bottom drawer with safe area padding).
- [x] Automated test coverage in `tests/Feature/MobileErgonomicsTest.php` (2 tests / 14 assertions 100% green).

---

## Phase E: Native Desktop Application (Tauri 2.0 / Rust)

### Sprint 17: Native Desktop Application with Multi-Window Capabilities
**Goal:** Deliver a blazing-fast, lightweight native desktop application built with Tauri 2.0 and Rust that completely replaces legacy VCE Player and VCE Designer.

#### S17.1 Tauri 2.0 Desktop Shell
- [x] Rust core with embedded SQLite, cryptographic hashing, and native ZIP package extraction (`src-tauri/Cargo.toml`, `src-tauri/src/main.rs`, `src-tauri/src/commands.rs`).
- [x] Cross-platform compilation targets: Windows (`.msi`/`.exe`), macOS (`.dmg`), Linux (`.deb`/`.AppImage`) configured in `tauri.conf.json`.
- [x] OS file associations: Register `.psmk`, `.psme`, and `.vce` file extensions so double-clicking any exam file launches Passimark Desktop.

#### S17.2 Desktop-Specific Ergonomic Features
- [x] **Multi-Window Exhibits:** Detach scenario diagrams, network topologies, and code snippets into independent secondary windows for dual-monitor setups (`TauriBridge.detachExhibit`).
- [x] **Floating Scratchpad & Calculator:** Detachable utility windows (`TauriBridge.detachScratchpad`).
- [x] **VCE-Parity Keyboard Shortcuts:** Instant single-key selection (`1`-`9`, `A`-`I`), `Ctrl+F` flag, `Ctrl+S` strike, `Ctrl+P` palette, `Esc` pause, `Ctrl+Enter` submit (`DesktopShortcutsModal.jsx`, `TauriBridge.registerDesktopShortcuts`).
- [x] **Desktop Package Inspector Backend:** `App\Http\Controllers\DesktopController` with `info` and `inspect` endpoints.

#### Acceptance Criteria & Verification
- [x] Memory footprint under 45MB RAM target; instant file-association launch of `.psmk` and `.vce` packages.
- [x] Automated test coverage in `tests/Feature/DesktopIntegrationTest.php` (2 tests / 6 assertions 100% green).

---

## Phase F: Spaced Repetition, AI Tutoring & Global Release

### Sprint 18: Spaced Repetition Engine, AI Explanations & Production Hardening
**Goal:** Introduce modern intelligent learning algorithms and finalize production hardening.

#### S18.1 Spaced Repetition & Weakness Remediation
- [x] Integrate Leitner 5-box spaced repetition algorithm for failed questions (`App\Services\SpacedRepetition\LeitnerEngine`, migration `2026_10_26_100000_create_passimark_spaced_repetitions_table.php`).
- [x] "Daily Drill" smart generator targeting candidate's due reviews and lowest-ability domains (`/api/drill/daily`, `DailyDrillCard.jsx`).
- [x] Spaced Repetition box distribution analytics (`/api/drill/stats`).

#### S18.2 AI Tutoring & Distractor Explanation Generator
- [x] AI Tutoring distractor explanation generator providing structured conceptual rationales explaining *why* chosen distractors are incorrect and the exam-day rule (`App\Services\AiTutoring\ExplanationGenerator`, `AiDistractorExplainer.jsx`).
- [x] In-exam Practice review integration with zero answer leakage on testlet delivery.

#### S18.3 Release Hardening & Auditing
- [x] Automated test suite: `tests/Unit/LeitnerEngineTest.php` (3 tests / 15 assertions), `tests/Feature/SpacedRepetitionAndAiTutorTest.php` (3 tests / 17 assertions).
- [x] Security audit: Verified zero answer leakage in candidate payloads (`correct_key`, `is_correct`, and options flags stripped in CAT/timed modes).
- [x] Production asset compilation: Clean build with 2,043 modules in 9.92s with zero errors.

#### Acceptance Criteria & Verification
- [x] 100% test pass rate across all test suites, production build green, complete documentation published.
