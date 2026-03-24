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
- **Database:** SQLite3 using PDO (`includes/db.php`). Four separate `.sqlite` files (labels, orders, rolodex, audit).
- **File Generation:** Native PowerShell "Structural Surgery" injecting content into original Master Templates.
- **Native Printing:** Multi-modal support.
  - **Orders (.ots):** Direct Windows Launching via `api/open_windows_file.php`.
  - **Labels (.odt):** High-speed Browser-Native printing via `print_label.php` (Zero-storage/HTML-based).
- **Mobile-First Hardware View:** Priority metadata display using CSS Grid `grid-template-areas` for small screens.

---

## 🗄️ 2. Database Architecture (4 Separate SQLite Files)

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

### D. `audit.sqlite` — System-Wide Audit Trails
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Audit Log ID |
| `target_id` | INTEGER | ID of the Label/Order affected |
| `action` | TEXT | `'CREATED'`, `'UPDATED'`, `'DELETED'`, `'STATUS_CHANGE'` |
| `old_data` | TEXT (JSON) | State before the action |
| `new_data` | TEXT (JSON) | State after the action |

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
│   ├── get_labels.php          ← GET: Universal Search (with 90d archive)
│   ├── edit_customer.php       ← POST: Updates contact + B2B tier
│   └── orders_api.php          ← POST: Bulk ORD generation with auto-discount
├── index.php                   ← Dashboard (Live stats & Action Strip)
├── analytics.php               ← Detailed Performance Reports
├── labels.php                  ← Warehouse Tracker (Print, Open, Edit)
├── dispatch.php                ← 🚚 Dispatch Desk (Sold & Archived view)
├── hardware_view.php           ← Shared Technical Sheet editor (Refurb/Parts)
├── audit_logs.php              ← System-Wide Tracking with JSON payloads
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
- [x] **Phase 16: 📝 Audit Logs** — System-wide status change tracking with `audit.sqlite`.
- [ ] **Phase 17: 📦 Bulk Batching Tool** — Multi-select batch upgrades for warehouse moves.

---

---

## [2026-03-24] - Audit Logs, iOS CSS Optimization & Battery Logic
### Added & Refactored
- **Audit Logs (Phase 16)**: Implemented complete system-wide tracking in `audit.sqlite` via `log_audit_event()`. Integrated seamlessly across `add_label`, `edit_label`, `delete_label`, and `orders_api`. Built `audit_logs.php` with a gorgeous JSON terminal-inspector capability.
- **Deep Technical Sheet Hierarchy**: Moved the `Battery Status` selection field entirely out of basic intake and directly into the Deep Technical Sheet (Refurbished View). This prevents false data drops for strictly untested intakes.
- **NULL Battery States**: Upgraded database saving logic to cleanly preserve `NULL` ('Pending/Unknown') for items that have not yet been evaluated, instead of forcing a 0 boolean state.
- **Fluid iOS Optimizations**: Re-engineered core CSS using `100dvh`, `-webkit-overflow-scrolling`, viewport `cover`, and `.label-mockup` fluid widths (`80vw`), ensuring the app feels native and unbroken on Mobile Safari and iPhone notches.

---

### 🧪 Session Verification Case
*   **Verification Case**: Open `hardware_view.php` for a fresh untested unit. Switch condition to **"Refurbished"**.
*   **Success Metric**: Verify that the newly opened **Deep Technical Sheet** contains the **Battery Status** dropdown defaulting to `— Unknown / Pending —`.

### ⏭️ Next 3 Steps (Session Start)
1.  **Bulk Batching Tool (Phase 17)**: Add multi-select checkboxes to `labels.php` rows to create a "Batch Selection".
2.  **Batch Status Updating**: Create an Action Strip payload to update Location/Status for multiple items at once (e.g., grading an entire palette).
3.  **Audit Integration**: Ensure the new Bulk Batching tool sends accurate parallel records to `audit.sqlite`.
