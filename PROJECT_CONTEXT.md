# 🤖 AI Agent Project Context
**File:** `PROJECT_CONTEXT.md`
**Purpose:** Read this single file to understand the entire application without needing to scan the whole codebase. Update this file as the project evolves.

---

## 🏗️ 1. Project Overview & Tech Stack
**App:** IQA Metal Inventory, Label Printer & Purchase Order System.
**Goal:** Track physical hardware, print `.odt` labels, and basket them into `.ots` Purchase Forms.
**Tech Stack:**
- **Frontend:** Vanilla HTML5, Vanilla CSS3, Vanilla JS.
- **Backend:** PHP 8+ handling API endpoints in `/api/`.
- **Database:** SQLite3 using PDO (`includes/db.php`). Three separate `.sqlite` files.
- **File Generation:** Native PowerShell injection of `content.xml` into Master Templates.

---

## 🗄️ 2. Database Architecture (3 Separate SQLite Files)

### A. `labels.sqlite` — Hardware Label Master
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK AUTOINCREMENT | Master Template ID (Hidden in UI) |
| `brand` | TEXT NOT NULL | e.g. `'HP'` |
| `model` | TEXT NOT NULL | e.g. `'EliteBook'` |
| `series` | TEXT | e.g. `'840 G3'` |
| `cpu_gen` | TEXT | e.g. `'11th Gen'` |
| `cpu_specs` | TEXT | Processor name e.g. `'i7-11850H'` |
| `cpu_cores` | TEXT | Physical Core count |
| `cpu_speed` | TEXT | Clock speed e.g. `'2.40GHz'` |
| `cpu_details` | TEXT | DEPRECATED (Legacy tech info) |
| `ram` | TEXT | Memory capacity |
| `storage` | TEXT | Drive capacity |
| `battery` | BOOLEAN | Battery included (1/0) |
| `bios_state` | TEXT | Locked/Unlocked/Unknown |
| `description` | TEXT | Master condition: `'Untested'`, `'Refurbished'` |
| `warehouse_location` | TEXT | Physical shelf location |

---

## 🗺️ 3. Folder & File Sitemap

```
/LabelAPP/
│
├── /DOCS/                      ← Centralized System Documentation
│   ├── ARCHITECTURE.md
│   ├── ROADMAP.md
│   ├── WorkLog.md
│   └── SITEMAP.md
│
├── /assets/
│   ├── /css/style.css          ← Single global dark-theme stylesheet
│   └── /js/
│       ├── forms.js            ← Handles hardware intake
│       └── labels.js           ← Inventory management logic
│
├── /db/                        ← SQLite3 Databases
│
├── /debug/                     ← Internal Testing & Schema Verification
│   ├── debug_api_call.php
│   └── debug_schema.php
│
├── /migrations/                ← Schema Evolution Scripts
│
├── /templates/                 ← ODT/OTS Master Templates
│
├── /exports/                   ← Generated ODT/OTS documents
│   ├── /labels/                ← Individual printer files
│   ├── /orders/                ← Customer B2B Forms
│   └── .htaccess               ← Security: Block direct directory browsing
│
├── /api/                       ← JSON-only endpoints
│   ├── add_label.php           ← POST: Insert + generate label
│   ├── reprint_label.php       ← POST: Regenerate ODT
│   └── get_labels.php          ← GET: Inventory Search
│
├── index.php                   ← Dashboard (Live stats & Action-First Search)
├── labels.php                  ← Warehouse Inventory Tracker (Searchable Cards)
└── new_label.php               ← Rapid Intake Profile Form (Sidebar Layout)
```

---

## 🎨 4. Design System / UI Vibe
- **Theme:** Robust Light Mode (High Contrast). Background `#fdfdfd`, panels `#ffffff`.
- **Accent Color:** Safety Green (`#8cc63f`).
- **Mobile First:** iPhone/Safari optimized via CSS Checkbox Hack (sidebar) and 48px touch targets.
- **Interactivity:** All forms use `fetch()` APIs; no full-page reloads.

---

## 🚀 5. Roadmap Status
- [x] **Phase 1-5**: Core infrastructure & Order Engine.
- [x] **Phase 6**: Warehouse Tracking Revamp & SKU Logic.
- [x] Phase 6B: Refurbished Tech Sheets (CPU/GPU/Battery Specs).
- [x] Phase 6C: Warehouse Revamp (Rapid Reprint, CPU Split, API Hardening).
- [x] **Phase 7: System Settings & Auto-Recovery** — Implemented `Schema Guard` for self-healing databases, `System Health` monitoring on Dashboard, and dedicated `settings.php` for backups and **Deep Integrity Repairs** (Database + Export Folder Structure).
- [ ] Phase 8: Analytics & Reporting (Inventory aging, sales trends).
