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
- **Mobile-First Hardware View:** Priority metadata display using CSS Grid `grid-template-areas` for small screens.

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
| `battery` | BOOLEAN | Battery included (1/0) |
| `battery_specs` | TEXT | Health % and cycle counts |
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
- **Smart Panels:** Sidebar widgets in `hardware_view.php` utilize `<details>` toggles to hide deep technical specs while keeping critical info (CPU/RAM/Storage) visible.

---

## 🚀 5. Roadmap Status
- [x] **Phase 1-8**: Infrastructure, Ordering, SKU Logic, Analytics, and Mobile-First Labels.
- [x] **Phase 8.5: Hardware View Overhaul** — Implemented mobile-first vertical stacking and "Quick Spec" summary widgets.
- [x] **Phase 8.6: Accessibility Audit** — Fixed `label for` mismatches and form field associations in the shared hardware engine.
- [x] Phase 8.7: 🚚 Sales & Dispatch Logic - Implemented "Sold" status handling and unlocked deletion of sold records as per warehouse maintenance requirements.
- [x] Phase 9: 🖨️ Thermal Printer Optimization (Zebra GX 430d) - Implemented 2" x 1" margin-less HTML printing. Unified Branding (Label A) and Specs (Label B) into a single 2-page print job for seamless PDF generation.
- [x] Phase 10: 🏗️ Scalability Foundation - Implemented temporal tracking (`updated_at` timestamps) and Visual Identification anchors on labels to enable future mobile scanning and inventory velocity analytics.
- [x] Phase 11: 🏗️ Hardware Mapping Layer - Implemented a single source of truth for all hardware field keys (`includes/hardware_mapping.php` & `assets/js/hardware_mapping.js`) to ensure site-wide stability and eliminate "variable guessing" across PHP and JS.
- [x] Phase 12: 🏗️ Enterprise Label Engine (4x2 / 2x1) - Implemented intelligent font-scaling, dual-column specification grids, and full Serial Number parity with factory standards.
- [ ] Phase 13: 🏷️ Tiered B2B Pricing (Sales Logic) - Integrate Customer Tiers (Gold/Silver) in `rolodex.sqlite` with automatic discount calculations in the Order Generator.
- [ ] Phase 14: 📦 The "Dispatch Desk" (Sold Item Separation) - Create a dedicated sub-view for archived sales to keep the primary inventory view performant and focused on available stock.

---

## [2026-03-19] - Enterprise Label Upgrade & Mapping Fortification
### Added & Refactored
- **Phase 12: High-Fidelity Labels (4x2)**: 
    - Switched default thermal label size to **4" x 2"** for improved data density and readability.
    - Implemented **Intelligent Font-Scaling**: Typography automatically adjusts (e.g., 24pt Branding / 11pt Specs vs 15pt/7.5pt) based on physical roll dimensions.
    - **Enterprise Grid (Label B)**: Transformed the technical specification layout into a dual-column flex grid (Processing vs Logic/State) to match official HP/Dell factory sticker standards.
- **Data Fidelity**: Integrated the **Serial Number / Asset Tag** and a high-contrast **Visual ID Anchor** (`ID: #xxxxx`) into the browser-native printing engine.
- **Phase 11: Unified Mapping Layer**: Verified and leveraged the single source of truth for all hardware field keys as defined in `dsa.md`.
- **Architectural Cleanup**: Centralized all dynamic printer CSS in `style.css` under the **Thermal Printer Engine (Dynamic)** section to prevent future logic regressions.
The system now features a robust **Hardware Mapping Layer**, ensuring that any change to the database schema or UI field names only needs to be updated in one place. Combined with the **Intelligent CPU Intake** and **Thermal Printer Optimization**, the intake process is now highly automated and stable for future AI-driven enhancements.
