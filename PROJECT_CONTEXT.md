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
- **File Generation:** Native PowerShell "Structural Surgery" injecting content into original Master Templates.
- **Native Printing:** Multi-modal support.
  - **Orders (.ots):** Direct Windows Launching via `api/open_windows_file.php`.
  - **Labels (.odt):** High-speed Browser-Native printing via `print_label.php` (Zero-storage/HTML-based).

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
│       ├── labels.js           ← Inventory management logic
│       └── print_engine.js      ← Global Print Modal & Quantity Logic
│
├── /db/                        ← SQLite3 Databases
│
├── /debug/                     ← Internal Testing & Schema Verification
│   ├── verify_doc.ps1          ← ODF Structural Diagnosis Tool
│   └── inspect_zip.ps1         ← ZIP Manifest/Layout Inspector
│
├── /migrations/                ← Schema Evolution Scripts
│
├── /templates/                 ← ODT/OTS Master Templates
│   └── /scripts/               ← PowerShell "Structural Surgery" logic
│
├── /exports/                   ← Generated ODT/OTS documents
│   ├── /labels/                ← Individual printer files
│   ├── /orders/                ← Customer B2B Forms
│   └── .htaccess               ← Security: Block direct directory browsing
│
├── /api/                       ← JSON-only endpoints
│   ├── add_label.php           ← POST: Insert + generate label
│   ├── reprint_label.php       ← POST: Regenerate ODT
│   ├── open_windows_file.php   ← Bridge: Launches local files in Windows
│   └── get_labels.php          ← GET: Inventory Search
│
├── index.php                   ← Dashboard (Live stats & Action-First Search)
├── labels.php                  ← Warehouse Inventory Tracker (Searchable Cards)
├── print_label.php             ← High-Quality Browser-Native Print Page
└── new_label.php               ← Rapid Intake Profile Form (Sidebar Layout)
```

---

## 🎨 4. Design System / UI Vibe
- **Theme:** Robust Light Mode (High Contrast). Background `#fdfdfd`, panels `#ffffff`.
- **Accent Color:** Safety Green (`#8cc63f`).
- **Mobile First:** iPhone/Safari optimized via CSS Checkbox Hack (sidebar) and 48px touch targets.
- **Interactivity:** All forms use `fetch()` APIs; no full-page reloads.
- **Interactive Printing:** Global Print Config Modal allows page selection and quantity control.
- **Hybrid Printing Approach:** 
  - **Browser Direct:** Instant, zero-file labels for rapid warehouse use.
  - **Windows Launch:** Precise, persistent document generation for official forms.

---

## 🚀 5. Roadmap Status
- [x] **Phase 1-7**: Infrastructure, Ordering, SKU Logic, and System Health.
- [x] **Phase 7.5: Native Printing Workflow** — Replaced browser downloads with direct Windows launch.
- [x] **Phase 7.7: ODF Stability & Zero-Storage Print** — Solved LibreOffice "File Corrupt" warnings via Structural XML Surgery and implemented browser-native printing.
- [ ] Phase 8: Analytics & Reporting (Inventory aging, sales trends).
- [ ] Phase 9: Thermal Printer Optimization (4x6 Margin-less Templates).
