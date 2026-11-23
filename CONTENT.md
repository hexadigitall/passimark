# CONTENT.md — Normative Assessment Content & Taxonomy Standard

**Specification Version:** 2.0.0  
**Target Format:** Passimark Portable Package (`.psmk` / `.psme` / `.psmm`)  
**Standard Authority:** Passimark Psychometric & Curriculum Board  

---

## 1. Scope & Core Content Principles

This document governs the creation, validation, storage, and exchange of all examination content within the Passimark ecosystem.

### Content Authoring Ground Rules

1. **Original Provenance:** Content must be original practice items authored against public syllabi, RFCs, vendor documentation, or textbooks. Never copy or transcribe proprietary live certification dumps.
2. **Defensible Distractors:** Every distractor must represent a credible, identifiable misconception. Explanations must clearly state not only *why* the correct answer is right, but *why* each distractor is wrong.
3. **Fact-Pinned References:** Every question must cite a real, verifiable source (e.g., `NIST SP 800-53 Rev 5 §AC-2`, `RFC 4861`, `AWS Well-Architected Framework: Reliability Pillar`). Generic citations such as "general knowledge" or "exam guide" fail validation.
4. **Rich Media Integration:** Diagrams, network topologies, code snippets, and audio exhibits must be packaged directly as relative assets inside the assessment container.

---

## 2. Package Architecture (`.psmk` Container)

A Passimark Assessment Package (`.psmk`) is a standardized ZIP container with the following internal layout:

```
assessment-bundle.psmk
├── manifest.json              # Package metadata, version, producer, checksums
├── content.json               # Full question bank, blueprints, sessions, and outlines
├── assets/                    # Relative media files referenced by questions
│   ├── network-topology.svg
│   ├── firewall-config.txt
│   └── database-schema.png
└── signatures/
    └── manifest.sha256        # Cryptographic authenticity signature
```

### 2.1 `manifest.json` Specification

```json
{
  "format": "passimark",
  "format_version": "2.0",
  "package_id": "a4da22ab-9a4d-46ec-abcf-07ebbe3dd39c",
  "content_type": "course",
  "title": "AWS Certified Solutions Architect - Associate (SAA-C03)",
  "cert_key": "aws-saa-c03",
  "created_at": "2026-10-15T12:00:00Z",
  "updated_at": "2026-10-15T12:00:00Z",
  "producer": {
    "name": "Passimark Designer",
    "version": "2.0.0"
  },
  "compatibility": {
    "min_engine_version": "2.0.0",
    "supports_interactive": true
  }
}
```

---

## 3. Universal Question Taxonomy: JSON Schemas & Evaluation

Every question in `content.json` conforms to a unified schema with a polymorphic `interactive_payload`.

### 3.1 Common Question Envelope

```json
{
  "id": "item-uuid-or-slug",
  "type": "single_choice | multiple_choice | drag_drop | build_list | hotspot | cloze | create_tree | fill_blank | case_study | simulation",
  "domain": "Design Resilient Architectures",
  "objective": "1.2",
  "stem": "Markdown formatted stem supporting equations: $\\sigma = \\sqrt{\\frac{1}{N}\\sum_{i=1}^N (x_i - \\mu)^2}$",
  "exhibits": [
    {
      "id": "exhibit-1",
      "type": "image | pdf | code | table",
      "title": "VPC Peering Architecture",
      "src": "assets/network-topology.svg"
    }
  ],
  "irt_3pl": {
    "difficulty": 0.45,
    "discrimination": 1.25,
    "guessing": 0.25
  },
  "explanation": "Detailed explanation addressing the correct answer and debunking distractors.",
  "reference": "AWS Well-Architected Framework: Reliability Pillar, Section 3.1"
}
```

---

### 3.2 Question Type Specifications

#### Type 1: Single Choice (`single_choice`)
Standard 1-of-N selection.

```json
{
  "type": "single_choice",
  "options": [
    { "key": "A", "text": "Amazon Aurora Multi-Master" },
    { "key": "B", "text": "Amazon DynamoDB Global Tables" },
    { "key": "C", "text": "Amazon RDS Multi-AZ Deployment" },
    { "key": "D", "text": "Amazon Redshift Cross-Region Replication" }
  ],
  "correct_key": "B"
}
```

#### Type 2: Multiple Choice (`multiple_choice`)
K-of-N selection with exact bounds.

```json
{
  "type": "multiple_choice",
  "min_selections": 2,
  "max_selections": 2,
  "options": [
    { "key": "A", "text": "Enable S3 Cross-Region Replication", "is_correct": true },
    { "key": "B", "text": "Use AWS Storage Gateway", "is_correct": false },
    { "key": "C", "text": "Enable S3 Versioning", "is_correct": true },
    { "key": "D", "text": "Configure S3 Lifecycle Rules to Glacier", "is_correct": false }
  ],
  "correct_keys": ["A", "C"],
  "scoring_strategy": "all_or_nothing | partial"
}
```

#### Type 3: Drag & Drop Matching (`drag_drop`)
Candidate matches draggable source tokens into designated target slots.

```json
{
  "type": "drag_drop",
  "interactive_payload": {
    "sources": [
      { "id": "src-1", "label": "Transport Layer" },
      { "id": "src-2", "label": "Network Layer" },
      { "id": "src-3", "label": "Data Link Layer" }
    ],
    "targets": [
      { "id": "tgt-1", "slot_label": "Segments / TCP & UDP", "correct_source_id": "src-1" },
      { "id": "tgt-2", "slot_label": "Packets / IPv4 & IPv6", "correct_source_id": "src-2" },
      { "id": "tgt-3", "slot_label": "Frames / MAC Addresses", "correct_source_id": "src-3" }
    ]
  }
}
```

#### Type 4: Build List & Reorder (`build_list`)
Candidate selects items from a pool and arranges them into the correct procedural sequence.

```json
{
  "type": "build_list",
  "interactive_payload": {
    "available_items": [
      { "id": "step-a", "text": "Verify identity credentials" },
      { "id": "step-b", "text": "Issue cryptographic session token" },
      { "id": "step-c", "text": "Audit logging of authentication event" },
      { "id": "step-d", "text": "Irrelevant distractor step" }
    ],
    "correct_sequence": ["step-a", "step-b", "step-c"],
    "scoring_strategy": "exact_order | kendall_tau_partial"
  }
}
```

#### Type 5: Point & Click / Hotspot (`hotspot`)
Candidate clicks a specific target region on an exhibit diagram.

```json
{
  "type": "hotspot",
  "interactive_payload": {
    "image_src": "assets/firewall-topology.png",
    "target_shape": "circle | rect | polygon",
    "target_bounds": {
      "center_x": 340,
      "center_y": 215,
      "radius": 25
    },
    "coordinate_system": "pixels | percentage"
  }
}
```

#### Type 6: Drop-Down Cloze (`cloze`)
Inline dropdown selectors embedded directly within paragraph text.

```json
{
  "type": "cloze",
  "interactive_payload": {
    "template": "To ensure fault tolerance, an engineer should deploy instances across at least {{blank_1}} Availability Zones and configure an {{blank_2}} to distribute incoming traffic.",
    "blanks": {
      "blank_1": {
        "options": ["One", "Two", "Four"],
        "correct_answer": "Two"
      },
      "blank_2": {
        "options": ["Application Load Balancer", "Internet Gateway", "NAT Instance"],
        "correct_answer": "Application Load Balancer"
      }
    }
  }
}
```

#### Type 7: Create-a-Tree (`create_tree`)
Candidate constructs a hierarchical parent-child node structure.

```json
{
  "type": "create_tree",
  "interactive_payload": {
    "root_label": "Enterprise Security Architecture",
    "available_nodes": [
      { "id": "n1", "label": "Perimeter Security" },
      { "id": "n2", "label": "Web Application Firewall" },
      { "id": "n3", "label": "Endpoint Detection" },
      { "id": "n4", "label": "EDR Agent" }
    ],
    "correct_edges": [
      { "parent": "root", "child": "n1" },
      { "parent": "n1", "child": "n2" },
      { "parent": "root", "child": "n3" },
      { "parent": "n3", "child": "n4" }
    ]
  }
}
```

#### Type 8: Fill in the Blank (`fill_blank`)
Free-form text input evaluated with normalization rules.

```json
{
  "type": "fill_blank",
  "interactive_payload": {
    "input_type": "text | number",
    "accepted_answers": ["192.168.1.1", "192.168.1.1/24"],
    "case_sensitive": false,
    "trim_whitespace": true,
    "regex_pattern": "^192\\.168\\.1\\.1(\\/24)?$"
  }
}
```

#### Type 9: Case Study / Testlet (`case_study`)
Multi-tab scenario dossier followed by linked questions.

```json
{
  "type": "case_study",
  "scenario": {
    "title": "Global FinTech Cloud Migration",
    "tabs": [
      { "title": "Overview", "content": "Company Background and Compliance Requirements..." },
      { "title": "Network Diagram", "src": "assets/fintech-vpc.svg", "type": "image" },
      { "title": "Audit Logs", "content": "2026-10-15 08:23:12 TLS Handshake failure...", "type": "code" }
    ]
  },
  "sub_items": [
    { "id": "cs-q1", "type": "single_choice", "stem": "Based on the audit logs...", "options": [...] },
    { "id": "cs-q2", "type": "drag_drop", "stem": "Map the required security controls...", "interactive_payload": {...} }
  ]
}
```

---

## 4. Psychometric IRT 3PL Parameter Guidelines

For items integrated into Computer Adaptive Testing (CAT) sessions, the following parameter bounds are enforced:

- **Difficulty ($b$):** Range $[-3.0, +3.0]$. Standard scale where $0.0$ represents median candidate ability.
- **Discrimination ($a$):** Range $[0.2, 2.5]$. Values below $0.5$ provide low psychometric information; values above $2.0$ represent highly discriminating threshold items.
- **Guessing ($c$):** Range $[0.0, 0.35]$. For standard 4-option single-choice items, default to $0.25$. For performance items (Hotspot, Drag & Drop), default to $0.0$.

---

## 5. Third-Party Format Conversion Pipeline

Passimark provides native source converters (`app/Services/PassimarkImport/`) to ingest legacy formats:

- **Visual CertExam (`.vce`):** Binary container parsing extracting stems, options, exhibits, and question types directly into native `.psmk` structures.
- **QTI 2.1 / 3.0 XML:** Standard IMS Global assessment format normalization.
- **Moodle XML & GIFT:** Ingests academic question banks.
- **Structured CSV / XLSX:** Rapid bulk ingestion using the standard column template:
  `stem | type | option_a | option_b | option_c | option_d | correct | explanation | reference | domain | objective`
