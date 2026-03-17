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
| `type` | TEXT | Hardware category (e.g. 'Laptop') |
| `brand` | TEXT NOT NULL | e.g. `'HP'` |
| `model` | TEXT NOT NULL | e.g. `'EliteBook'` |
| `series` | TEXT | e.g. `'840 G3'` |
| `cpu_gen` | TEXT | e.g. `'11th Gen'` (Triggers Auto-Spec) |
| `cpu_specs` | TEXT | Technical model e.g. `'i7-11850H'` |
| `cpu_cores` | TEXT | Core count (Auto-filled) |
| `cpu_speed` | TEXT | Clock speed (Auto-filled) |
| `ram` | TEXT | Memory capacity |
| `storage` | TEXT | Drive capacity |
| `gpu` | TEXT | Graphics controller |
| `bios_state` | TEXT | Locked/Unlocked/Unknown |
| `description` | TEXT | Condition/Internal Note: `'Untested'`, `'Refurbished'`, `'For Parts'` |
| `status` | TEXT | Display status: `'In Warehouse'`, `'Grade A/B/C'`, `'Tested'`, `'No Post'`, `'No Power'` |
| `warehouse_location` | TEXT | Physical shelf location |

---

## 🗺️ 3. Folder & File Sitemap

```
/LabelAPP/
├── /DOCS/                      ← Architecture, Roadmap, WorkLog
│   └── FUTURE_UPGRADES.md      ← Brain-dump of AI improvements & features
├── /assets/js/
│   ├── forms.js            ← Intelligent CPU Intake logic
│   ├── actions.js          ← Global Technical Action Bridge (Flash Launch)
│   ├── labels.js           ← Inventory management logic
│   └── print_engine.js      ← Global Print Modal & Quantity Logic
├── /includes/
│   ├── hardware_form.php   ← Shared Technical Form component (Intake & Refurb)
│   ├── schema_guard.php    ← Self-healing database logic
│   └── db.php              ← PDO Master Connection
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
├── /api/                       ← JSON endpoints
│   ├── add_label.php           ← POST: Insert + generate label
│   ├── reprint_label.php       ← POST: Regenerate/Open ODT
│   ├── check_file_exists.php    ← GET: Utility for Flash Launch
│   ├── get_analytics.php       ← GET: Dashboard performance metrics
│   └── get_labels.php          ← GET: Universal Search Engine
├── index.php                   ← Dashboard (Live stats & Action Strip)
├── analytics.php               ← Detailed Performance Reports
├── labels.php                  ← Warehouse Tracker (Print, Open, Edit)
├── hardware_view.php           ← Shared Technical Sheet editor (Refurb/Parts)
├── print_label.php             ← High-Quality 2" x 1" HTML Printing
└── new_label.php               ← Rapid Intake & Profile Cloning Form
```

---

## 🎨 4. Design System / UI Vibe
- **Theme:** Robust Light Mode (High Contrast). Background `#fdfdfd`, panels `#ffffff`.
- **Accent Color:** Safety Green (`#8cc63f`).
- **Mobile First:** iPhone/Safari optimized via CSS Checkbox Hack (sidebar) and 48px touch targets.
- **Interactivity:** All forms use `fetch()` APIs; no full-page reloads.
- **Interactive Printing:** Global Print Config Modal allows page selection and quantity control.
- **Hybrid Printing Approach:** 
  - **Browser Direct:** Instant, zero-file labels for rapid warehouse use. Exactly **2" x 1"** dimensions.
  - **Windows Launch:** Precise, persistent document generation for official forms.

---

## 🚀 5. Roadmap Status
- [x] **Phase 1-7**: Infrastructure, Ordering, SKU Logic, and System Health.
- [x] **Phase 7.5: Native Printing Workflow** — Replaced browser downloads with direct Windows launch.
- [x] Phase 7.7: ODF Stability & Zero-Storage Print — Solved LibreOffice "File Corrupt" warnings.
- [x] Phase 7.8: Universal Hardware Pattern (Form Unification + Flash Launch)
- [x] Phase 8: Analytics & Reporting (Inventory aging, sales trends)
- [x] Phase 8.5: Labels Page UX Overhaul (Card-based mobile layout, dynamic filtering, optimized action strips).
- [x] Phase 8.6: ⚙️ File Printing / Opening - Implement precise native folder/file launching directly from `labels.php`.
- [ ] Phase 8.7: 🚚 Sales & Dispatch Logic - Develop a system location or workflow to handle "Sold" items and gracefully filter them from the primary warehouse view.
- [ ] Phase 9: Thermal Printer Optimization (4x6 Margin-less Templates).
