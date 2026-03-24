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

### A. `labels.sqlite` — Master Hardware Library (Templates)
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Master Template ID (Pristine Reference) |
| `status` | TEXT | ALWAYS `'In Warehouse'` (Decoupled from Sales) |
| `description` | TEXT | Condition: `'Untested'`, `'Refurbished'`, `'For Parts'` |
| `created_at` | DATETIME | Original intake timestamp |

### B. `orders.sqlite` — Sales & Financial Snapshots
| Column | Type | Notes |
|---|---|---|
| `invoice_status` | TEXT | **`Pending`, `Active`, `Paid`, `Dispatched`, `Canceled`** |
| `specs_blob` | TEXT | **Snapshot of item specs at time of sale** (Preserves history) |
| `unit_price` | NUMERIC | Individual sale price for the unit |
| `order_date` | DATETIME | Used for Dispatch Desk archival (90-day filter) |

---

### C. `rolodex.sqlite` — Customer & CRM
| Column | Type | Notes |
|---|---|---|
| `customer_id` | INTEGER PK | Master Contact ID |
| `company_name` | TEXT | Primary Billing Name |
| `tier` | TEXT | **Gold (10%)**, **Silver (5%)**, **Bronze (0%)** |
| `lead_status` | TEXT | `'Active Customer'`, `'New Lead'`, `'Inactive'` |

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
│   ├── get_labels.php          ← GET: Universal Search (with 90d archive)
│   ├── edit_customer.php       ← POST: Updates contact + B2B tier
│   └── orders_api.php          ← POST: Bulk ORD generation with auto-discount
├── index.php                   ← Dashboard (Live stats & Action Strip)
├── analytics.php               ← Detailed Performance Reports
├── labels.php                  ← Warehouse Tracker (Print, Open, Edit)
├── dispatch.php                ← 🚚 Dispatch Desk (Sold & Archived view)
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
- [x] **Phase 11: Unified Mapping Layer** — Implemented a single source of truth for all hardware field keys.
- [x] **Phase 12: Unified Enterprise Label Engine** — Merged Branding and Technical Specifications.
- [x] **Dynamic Scaling Engine**: Implemented "Compact Flow" scaling that shrinks and wraps text automatically.
- [x] **Phase 13: 📦 The "Dispatch Desk"** — Implemented `dispatch.php` with 90-day archival and sold-item separation.
- [x] **Phase 14: 🏷️ Tiered B22 Pricing** — Integrated Gold/Silver/Bronze tiers with auto-discounts in the Order Engine.
- [x] **Phase 15: 📊 Performance Dashboard V2** — Detailed profitability reports and top buyer leaderboard.
- [ ] Phase 16: 📝 Audit Logs — System-wide status change tracking.

---

---

## [2026-03-20] - Snapshot Architecture & Financial Status Refactor
### Added & Refactored
- **Snapshot Engine**: `order_items` now captures a `specs_blob` (stringified hardware profile) at the moment of sale. This decouples sales history from the master labels library.
- **Financial Status Workflow**: Orders now support a full lifecycle: **Pending ⏳**, **Active 🚀**, **Paid ✅**, **Dispatched 🚚**, and **Canceled ❌**.
- **Logistical Decoupling**: Items in `labels.php` now remain 'In Warehouse' permanently. They serve as master templates that stay "in the library" even after being sold.
- **Dispatch Desk 2.0**: The dispatch view now pulls data 100% from the **Orders Database**, ensuring historical accuracy (price/specs) even if the original label is deleted.
- **Analytics Sync**: The "Ready to Dispatch" counter now smartly ignores `Canceled` and `Dispatched` orders to show actual physical backlog.
- **Windows Path Fixes**: Resolved cross-database `ATTACH` bugs by implementing a PHP-level mapping engine for Windows/XAMPP compatibility.

---

### 🧪 Session Verification Case
*   **Last Tested ID:** `ORD-000001` (Miguel Garcia - Verified "Dispatched" status correctly clears the Analytics backlog).
*   **Hardware Snapshot:** Verified that editing a label in `labels.php` after a sale does **not** corrupt the historical record in `dispatch.php`.
*   **Next Verification Step:** Open `analytics.php` and verify that "Ready to Dispatch" shows a non-zero count for "Active" and "Paid" orders.

### ⏭️ Next 3 Steps (Session Start)
1.  **Phase 16: Audit Logs**: Implement `audit.sqlite` to track who changed an order status (e.g. from Paid to Dispatched) and when.
2.  **Bulk Batching Tool**: Add a multi-select mode to the Warehouse Tracker for updating locations or descriptions for 20+ items at once.
3.  **PDF/Print Manifests**: Generate a "Daily Dispatch Manifest" (PDF/HTML) for the shipping team listing all orders currently marked "Ready to Dispatch."
