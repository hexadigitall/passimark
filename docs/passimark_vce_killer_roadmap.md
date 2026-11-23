# Passimark: Adaptive Exam Intelligence Repository
## Technical Roadmap, Codebase Remediation & VCE Superiority Sprint Plan

**Brand:** Hexadigitall Inc.  
**System Architecture:** Laravel 11 + Inertia.js + React 18 + Tailwind CSS  
**Engine:** IRT 3-Parameter Logistic (3PL) Adaptive Testing Engine  

---

## Executive Summary & Competitive Analysis

Passimark is built to replace legacy static exam simulators (such as Visual CertExam Suite / VCE Player & Designer) with a cloud-native, adaptive, psychometrically grounded certification environment. 

While VCE relies on fixed-sequence offline `.vce` files and lacks adaptive testing capabilities, Passimark delivers dynamic candidate ability estimation ($\theta$). However, to achieve complete market dominance over VCE, Passimark must bridge key gaps in item format diversity, GUI authoring, non-linear exam review, and portable file execution.

### Competitive Matrix: Passimark vs. Visual CertExam (VCE)

| Capability | Visual CertExam (VCE) | Passimark Current State | Target Passimark State (Post-Sprint 9) |
| :--- | :--- | :--- | :--- |
| **Testing Engine** | Static / Random sequence | Adaptive IRT 3PL ($\theta, a, b, c$) | **Adaptive IRT 3PL + Linear Timed + Practice** |
| **Question Types** | 8+ (MC, Multi-select, D&D, Hotspot, Reorder, Fill-in) | Single-choice text only | **10+ Types (Includes Case Studies & Audio/Exhibits)** |
| **Navigation** | Free-roam grid, bookmarking, review panel | Forward-only linear | **Dual Mode: CAT Forward-Only + Free-Roam Grid** |
| **Authoring (CRUD)** | *VCE Designer* Desktop GUI | Raw JSON API (`POST /admin/...`) | **Passimark Designer (GUI + Live Preview + Import)** |
| **File Portability** | `.vce` single portable file | Database / Web Server dependent | **`.pmkx` File Package + Native Offline Player** |
| **Analytics** | Simple score % | Basic attempt history | **Domain Mastery Radar, IRT $\theta$ Curve, Calibration** |

---

## Codebase Audit & Immediate Technical Remediation

Prior to feature expansion, the following critical bugs and structural gaps in the current codebase must be resolved:

1. **Hardcoded Pass Score:** `PassimarkController::finish()` currently evaluates pass/fail using a hardcoded threshold of 70 instead of referencing `$attempt->session->pass_score`.
2. **Missing Ownership Guards:** `PassimarkController::answer()` and `finish()` do not verify `$attempt->user_id === Auth::id()`, allowing authorization bypass.
3. **Null-Safety on Final Approval:** `PassimarkAdminController::approve()` fails when approving Session 46, as `$next` session is `null`.
4. **CAT Question Exhaustion:** `CatEngine::shouldTerminate()` expects up to 75–150 questions in adaptive mode, but current database seeders only contain 25 questions per session, causing infinite loop risks or `null` item returns.
5. **Schema Constraints:** `passimark_questions` lacks fields for question types, options JSON schema constraints, exhibit media URLs, and multi-correct key maps.

---

## Comprehensive 6-Sprint Master Roadmap (Sprints 4 – 9)

```
        SPRINT 4                     SPRINT 5                     SPRINT 6
+-----------------------+    +-----------------------+    +-----------------------+
| Database Schema &     |    | Multi-Format Item     |    | Navigation, Review    |
| Codebase Hardening    | -> | Interactive Engine    | -> | Palette & Dual Engine |
+-----------------------+    +-----------------------+    +-----------------------+
            |                            |                            |
            v                            v                            v
        SPRINT 7                     SPRINT 8                     SPRINT 9
+-----------------------+    +-----------------------+    +-----------------------+
| Passimark Designer    |    | .pmkx File Package    |    | Psychometrics,        |
| GUI & VCE Importer    | -> | & Offline Player      | -> | Analytics & Release   |
+-----------------------+    +-----------------------+    +-----------------------+
```

---

### Sprint 4: Schema Expansion, Security Hardening & Core Fixes

#### Goal
Remediate initial codebase vulnerabilities, expand database schemas for interactive item types, and ensure solid backend logic.

#### Subtask Breakdown

* **S4.1 Database Schema Extensions (`2026_01_01_000002_expand_passimark_schema.php`)**
  * Add `type` column to `passimark_questions` (`single_choice`, `multiple_choice`, `fill_in_blank`, `drag_drop`, `hotspot`, `reorder`, `case_study`).
  * Add `exhibits` (JSON) and `attachments` (JSON) to `passimark_questions`.
  * Create `passimark_packages` table for portable exam container tracking.
* **S4.2 Authorization & Ownership Guards**
  * Enforce `$attempt->user_id === Auth::id()` in `PassimarkController::answer()` and `finish()`.
  * Add Spatie role permission checks to all instructor endpoints.
* **S4.3 Null Safety & Gating Fixes**
  * Update `PassimarkAdminController::approve()` to check `if ($next)` before attempting session unlocking.
  * Refactor `PassimarkController::finish()` to use `$attempt->session->pass_score`.
* **S4.4 Question Exhaustion Safeguard in `CatEngine.php`**
  * Update `shouldTerminate()` to check if unanswered question count is zero (`!$remainingQuestions`), forcing attempt completion gracefully.

---

### Sprint 5: VCE-Killer Question Types & Interactive UI Engine

#### Goal
Build rich interactive question components in `Exam.jsx` that match and exceed VCE's item renderer capability.

#### Subtask Breakdown

* **S5.1 Multiple Selection Component (`QuestionMultiSelect.jsx`)**
  * Checkbox matrix with configurable minimum/maximum selection bounds.
* **S5.2 Drag-and-Drop Matching & Ordering (`QuestionDragDrop.jsx`)**
  * Interactive HTML5 Drag-and-Drop container supporting *Select & Place* and *Drop & Connect*.
* **S5.3 Hotspot / Image Target Component (`QuestionHotspot.jsx`)**
  * SVG/Canvas overlay on exhibit diagrams allowing candidates to click specific image coordinates.
* **S5.4 Reorder / Build-List Component (`QuestionReorder.jsx`)**
  * Up/down sorting list interface with touch/drag support for procedural questions.
* **S5.5 Case Study & Split Exhibit Panel (`CaseStudyViewer.jsx`)**
  * Side-by-side scenario documentation viewer with tabbed exhibits (network topology, log files, policies).
* **S5.6 Backend Answer Evaluator Service (`app/Services/AnswerEvaluator.php`)**
  * Server-side validation logic for complex JSON structures (arrays, coordinate tolerance, string normalization for fill-in-the-blank).

---

### Sprint 6: Exam Navigation, Review Palette & Dual-Engine Modes

#### Goal
Provide dual-mode navigation: strict forward-only progression for adaptive CAT mode, and full free-roam navigation for Timed/Practice modes.

#### Subtask Breakdown

* **S6.1 Navigation Drawer & Matrix Palette (`ExamNavigator.jsx`)**
  * Interactive grid showing question numbers, answered status, flagged/bookmarked items, and incomplete markers.
* **S6.2 Flag for Review & Bookmark System**
  * Toggle flag status on active items, persisting state in `passimark_attempt_answers`.
* **S6.3 Pre-Submit Review Screen (`ReviewModal.jsx`)**
  * Summary view displaying total answered, skipped, and flagged items prior to final submission in Timed and Practice modes.
* **S6.4 Detailed Post-Exam Review Mode (`ExamResultsReview.jsx`)**
  * Question-by-question breakdown post-exam with filtering by *Incorrect*, *Flagged*, or *Domain*, featuring answer explanations and reference documentation.

---

### Sprint 7: Passimark Designer GUI Content Authoring & Importer

#### Goal
Eliminate manual JSON database seeding by building a full web-based GUI authoring tool (*Passimark Designer*) and file importer.

#### Subtask Breakdown

* **S7.1 Passimark Designer Curriculum Manager (`Pages/Admin/Designer.jsx`)**
  * Visual CRUD interface for creating, updating, and reordering Sessions, Exams, and Questions.
* **S7.2 WYSIWYG & LaTeX Item Editor (`QuestionEditor.jsx`)**
  * Rich-text editing with inline KaTeX math equation rendering, exhibit image uploader, and IRT 3PL ($a, b, c$) calibration sliders.
* **S7.3 Bulk CSV / JSON Importer & Validator (`app/Services/ContentImporter.php`)**
  * Import wizard capable of parsing structured spreadsheets and JSON question banks with live syntax validation.
* **S7.4 Legacy VCE / Text File Converter**
  * Text parser capable of converting plain-text exam dumps into native Passimark structured JSON format.

---

### Sprint 8: `.pmkx` File Format Specification & Standalone Player Engine

#### Goal
Engineers Passimark's proprietary offline file container (`.pmkx`) and lightweight desktop/PWA standalone player to beat VCE offline delivery.

#### Specification of `.pmkx` Package Architecture

A `.pmkx` file is a compressed ZIP package containing:
```
my-exam-package.pmkx
├── manifest.json       # Exam metadata, session layout, pass threshold
├── content/
│   └── questions.json  # Full question bank with IRT 3PL parameters
├── assets/             # Images, network diagrams, audio exhibits
└── signature.sha256    # Cryptographic integrity signature
```

#### Subtask Breakdown

* **S8.1 Package Generator Service (`app/Services/PackageExporter.php`)**
  * Export any Passimark session or exam into a standalone `.pmkx` encrypted ZIP bundle.
* **S8.2 Standalone Client Engine (PWA + SQLite WASM / IndexedDB)**
  * Offline-capable web runner that executes `.pmkx` files inside the browser without requiring a active Laravel backend.
* **S8.3 Native Desktop & Mobile Packaging (Electron / Tauri & Capacitor)**
  * Build native installers (`.exe` for Windows, `.dmg` for macOS, `.apk` for Android) configured to open `.pmkx` files directly.

---

### Sprint 9: Enterprise Analytics, Psychometrics & Production Release

#### Goal
Deliver CISO-grade domain mastery dashboards, candidate performance analytics, and finalize production hardening.

#### Subtask Breakdown

* **S9.1 Domain Mastery Radar & Ability Curve (`MasteryAnalytics.jsx`)**
  * Visual chart displaying domain competency across standard exam domains alongside IRT theta progression ($\theta$).
* **S9.2 Automated Test Suite Expansion**
  * PHPUnit tests for `CatEngine`, `AnswerEvaluator`, and authorization policies.
* **S9.3 Production CI/CD & Deployment Pipeline**
  * Docker production configuration, asset minification, and database indexing optimization.

---

## Verification & Definition of Done

To consider Passimark's VCE Superiority release complete, the system must satisfy:

1. **Schema Integrity:** Database supports all 8+ question types with full backwards compatibility.
2. **IRT CAT Precision:** `CatEngine` successfully terminates between 75–150 questions based on $\theta$ convergence without item starvation.
3. **Interactive Parity:** Candidate can complete Drag-and-Drop, Hotspot, and Multi-Select items cleanly.
4. **Offline Capability:** A user can download a `.pmkx` file, load it into the offline player app, complete an exam, and export verifiable results without network connectivity.