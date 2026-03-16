# Work Log - IQA Metal Inventory & Label System

## [2026-03-16] ODF Structural Stability & Zero-Storage Printing

### ODF Integrity Solved (The "No Corruption" Fix)
- **Structural XML Surgery**: Refactored `generate_odt.ps1` and `generate_ots.ps1`. Instead of replacing files, the system now extracts the live `content.xml`, uses Regex to graft new data into the native `<office:text>` or `<office:spreadsheet>` tags, and preserves all original styles and namespaces.
- **Security Artifact Cleanup**: 
    - Discovered that LibreOffice's "Corrupt/Macros Disabled" warning was triggered by the presence of `Configurations2/` and `manifest.rdf` in externally generated files.
    - Scripts now surgically delete these macro-triggering entries and **rebuild `manifest.xml` from scratch** to ensure strict ODF validator compliance.
- **Pre-Injection Validation**: Added `DOMDocument` validation in PHP documenting APIs; illegal characters or malformed fragments are caught *before* reaching the document engine.

### High-Speed Label Printing (Option A)
- **Zero-Storage Browser Flow**: Implemented `print_label.php`. This allows technicians to print hardware labels directly from the browser without generating any `.odt` files in the `exports/` folder.
- **Exact Dimension Rendering**: Used `@page` CSS to map labels to exact **3" x 1.74"** dimensions. 
- **Auto-Lifecycle**: The print tab auto-triggers `window.print()` and auto-closes after the job is finished or cancelled.

### Workspace Cleanup
- Permanently cleaned the `/debug/`, `/templates/`, and `/exports/` folders of all legacy artifacts and one-shot test scripts.
- Retained `debug/verify_doc.ps1` and `debug/inspect_zip.ps1` as the permanent system diagnostic suit.

## [2026-03-16] Native Printing & ODF Structural Stability (Direct Launch)

### Features & Infrastructure
- **Native Windows Print Bridge**: Created `api/open_windows_file.php`. This endpoint acts as a secure intermediary that allows the browser to trigger a "Direct Launch" of ODT and OTS files in their default Windows applications (LibreOffice Writer/Calc).
- **Global Print Config Engine**: 
    - Built `assets/js/print_engine.js` to manage a new, premium print modal.
    - Technicians can now select specific label pages (Branding/Hardware Specs) and set quantities before generating the file.
    - Integrated the configuration workflow into `labels.php`, `new_label.php`, and dashboard inventory search.
- **Order Engine Upgrade**: Modified `orders.php`, `new_order.js`, and `index.php` to replace browser downloads with high-speed "🚀 Open in Windows" buttons for B2B Purchase Orders.

### ODF Integrity Overhaul (Bug Fixes)
- **Generation Engine v2 (.NET ZipAware)**: 
    - Completely rewrote `generate_odt.ps1` and `generate_ots.ps1` to replace the unreliable `Compress-Archive` core.
    - Switched to the **.NET `ZipArchive` Class** to ensure the `mimetype` file remains uncompressed and correctly ordered, meeting strict ISO OpenDocument requirements.
    - Implemented **Manual Stream Writing** for `content.xml` to prevent the injection of UTF-8 Byte Order Marks (BOM), which previously caused "File Corrupt" warnings.
- **Schema-Compliant XML Injection**:
    - **XML Escaping**: Wrapped all hardware metadata in `htmlspecialchars(..., ENT_XML1)` to prevent special characters (likes `&`, `<`) from breaking the technical document structure.
    - **Namespace Fortification**: Expanded the XML headers in all Document APIs to include 30+ standard ODF namespaces, improving cross-version compatibility.
    - **Resource Lock Prevention**: Added "File in Use" detection to the PowerShell logic; the system now gracefully warns the user if a label is already open in LibreOffice before attempting an update.

## [2026-03-15] UX Overhaul & Intuition Upgrade (Mobile Robustness)

### UI & UX (iPhone First)
- **Robust Navigation Engine**: Implemented a CSS-only hamburger menu using the "Checkbox Hack". This ensures zero JavaScript dependencies for core navigation on iPhone/Safari.
- **Action-First Dashboard**: Restructured `index.php` to place high-frequency actions (Quick Locate & Quick Intake) above system statistics, reducing need for scrolling in the warehouse.
- **Searchable Inventory Cards**: Transformed the horizontal inventory table into vertical, labeled cards for mobile users. Each data point (CPU, Location, Specs) is clearly identified for rapid scanning.
- **Sidebar Stacking**: Updated `new_label.php` to move the "Recently Added" sidebar to the bottom of the page on mobile devices to maximize vertical form space.
- **Touch-Friendly Standard**: Enforced a minimum 48px height on all buttons and inputs (Apple Human Interface Guidelines) to ensure reliability for technicians wearing gloves or working on the move.
- **Safe Area Support**: Added `env(safe-area-inset-top)` support to ensure headers and menus are never obscured by the iPhone notch.

### System Stability (File System Integrity)
- **Folder Correction Engine**: Enhanced "Deep Integrity Repair" to automatically verify and recreate the entire export folder structure (`exports/labels`, `exports/orders`, etc.).
- **Proactive Directory Check**: Hardened `api/add_label.php` and `api/reprint_label.php` to verify directory existence before ODT generation, preventing path resolution crashes.
- **Security Hardening**: Auto-injects `.htaccess` into the `exports/` directory to block directory indexing and protect B2B data.

## [2026-03-15] Phase 7 — System Fortification & Auto-Recovery

### Features & Reliability
- **Self-Healing Schema Guard**: Implemented `includes/schema_guard.php`. The system now automatically detects missing database tables and rebuilds them on the fly, preventing "Table not found" crashes.
- **Proactive Health Monitor**: Created `includes/status_functions.php` to perform deep `PRAGMA integrity_check` scans on SQLite files.
- **Live Dashboard Alerts**: Upgraded `index.php` to display a high-visibility **Red Alert** if any database corruption or file loss is detected.
- **Recovery Hub (`settings.php`)**:
    - **Deep Repair**: Manual trigger to verify and fix database integrity.
    - **One-Click Backup**: Instantly snapshots all system databases into `/db/backups/`.
    - **Manual Re-Init**: Safer shortcut to the database builder for emergency use.
- **UI Navigation**: Wired "⚙️ System Settings" into the global sidebar for 24/7 access to health tools.

## [2026-03-15] Final Workspace Polish — Codebase Reorganization

### Features & Infrastructure
- **Documentation Centralization**: Moved all high-level docs (`ARCHITECTURE.md`, `WorkLog.md`, `ROADMAP.md`) into a dedicated `/DOCS/` folder for better workspace organization.
- **Maintenance Sandbox**: Established a `/debug/` directory for schema verification and API test scripts, preventing root folder clutter.
- **Migration Tracking**: Centralized all SQLite schema evolution scripts into `/migrations/`.
- **Project Context Sync**: Synchronized `PROJECT_CONTEXT.md` to reflect the new file structure and updated technical schemas.

## [2026-03-15] Last Phase Continued — Warehouse Revamp (Connected Experience)

### Features Implemented
- **Unified Warehouse Workflow**: Interconnected the **Add to Warehouse** (`new_label.php`) and **Inventory Tracker** (`labels.php`) for a cohesive technician experience.
- **Rapid Reprint Tool**:
    - Created a dedicated `api/reprint_label.php` endpoint.
    - Added **🖨️ Print** buttons directly to the inventory rows for instant label reproduction.
- **Multi-Page Label Layout**: 
    - Redesigned `.odt` generation to split Brand/Model and Specs into two separate pages.
    - Removed internal "ID: #" strings from the printed output for a cleaner customer-facing look.
    - Implemented `fo:break-before="page"` XML injection to force technical specs onto a secondary label/page.
- **Flexible Search Engine**: 
    - Implemented multi-keyword "AND" logic across all hardware search APIs. 
    - Technicians can now combine Brand, Model, Series, and Specs (e.g., "HP 840 i5") to pinpoint results.
    - Fixed "widening" bug; results now correctly expand in real-time as keywords are deleted.
- **Dashboard Search Hub**: 
    - Transformed "Quick Locate" into a live-search widget. 
    - Supports both numeric ID lookups (with deep sales data) and general keyword searches.
- **"Smart Hub" Intake Enhancements (`new_label.php`)**:
    - **Intelligent Defaults**: 8GB RAM and 256GB NVMe are automatically suggested only when a technician checks the component box.
    - **Context-Aware BIOS**: Automatically sets BIOS to "Unknown" for Untested units and "Unlocked" for Refurbished units.
    - **"Pin Location" Feature**: Allows technicians to lock a warehouse bin/shelf across multiple entries for rapid batch processing.
    - **Searchable CPU Widget**: Replaced the erratic browser datalist with a custom strictly-narrowing search tool for CPU generations.
- **CPU Data Model Refactoring**:
    - Split the consolidated `cpu_details` into three granular fields: **Processor Specs** (e.g. i7-11850H), **Cores**, and **Speed**.
    - Updated the database schema and all associated Label APIs/Editors to support this high-accuracy technical tracking.
- **UI & Interaction Polish**:
    - **Visual Stability**: Corrected button hover behaviors in `style.css` to prevent distracting jumps between colors (Navy to Green).
    - **Premium Action Hub**: Replaced dashboard list items with large, actionable "Consoles" for faster navigation.
    - **Table Ergonomics**: Fixed light-mode hover visibility for rows in the Inventory list.
- **API Hardening & Stability**:
    - Migrated all API dependencies to **Absolute Path Resolution** (`__DIR__`) to resolve path-related errors in XAMPP.
    - Fixed an "Edit" button mapping bug caused by the new multi-result search response.

### Architecture Changes
- **Multi-Page ODT Engine**: Updated XML generation to include `<office:automatic-styles>` with page break properties.
- **Tokenized Search Engine**: SQL logic in `api/get_labels.php` and `api/search_item.php` now splits queries into tokens for multi-field indexing.
- **Reprint API**: Decoupled label generation (PowerShell) from database insertion, allowing for multiple prints of a single SKU/ID.

---

## [2026-03-15] Last Phase Continued — SKU Architecture & Refurbished Sheets

## [2026-03-15] Last Phase — Polish & CRUD Operations

### Features Implemented
- **Dashboard Quick Locate Wired:** Updated `index.php` Quick Search to query `api/search_item.php`. Renders full specs, location, and Sold status (including Buyer details + download link for linked `.ots` orders).
- **Inventory Search & Filtering:**
    - Created `api/get_labels.php` (GET) supporting debounced text search and status filtering.
    - Integrated `assets/js/labels.js` for real-time DOM updates.
- **Hardware & CRM CRUD (Edit/Delete):**
    - Implemented **Inline Editing** across `labels.php` and `rolodex.php`.
    - Safety guards: Blocks deletion of Sold items or Customers with linked orders.
- **Customer Card & Premium CRM:**
    - Expanded Rolodex schema (`address`, `tax_id`, `website`).
    - Created `customer_view.php`: A 360-view profile card showing purchase history.
    - Created `edit_customer.php`: Dedicated full-page form for deep editing.
    - **UI Customization:** Replaced "Tax ID" labels with "Address" per user preference to match business flow.
- **Detailed Order Management:**
    - Created `order_view.php`: A digital receipt showing exactly which Machine IDs were included in a sale.
    - Implemented **Order Rollback** API (`api/delete_order.php`): Fully reverses a sale by deleting the PO record and returning items to warehouse inventory.
- **System Polish:**
    - Created a custom **404 Page** (`404.php`) with smart "Back to Last Page" logic.
    - Configured `.htaccess` for automatic error routing.
    - Switched to Light "Safety Green" theme across all views.

### Architecture Changes
- **Single Source of Truth (DOM):** Adopted a pattern where API successful POSTs return the fully updated item row data, which is then re-rendered in the browser without a full page refresh.
- **Referential Integrity Guards:** Implemented logical checks in API layer to prevent orphaned orders or inventory inconsistency (e.g., blocking deletion of linked items).

---

## [2026-03-15] Phase 5 — The Ordering Engine

### Features Implemented
- **`api/search_inventory.php`** (GET) — Live warehouse search endpoint.
- **`api/orders_api.php`** (POST JSON) — Full order creation backend.
- **`templates/scripts/generate_ots.ps1`** — PowerShell OTS injector.
- **`new_order.php`** — 4-step cart UI page.
- **`assets/js/new_order.js`** — Complete cart engine: fingerprint grouping, live search, subtotals.
- **`orders.php`** — Purchase order history list.

---

## [Legacy] Phase 3 & 4 Execution
- **Phase 3 Complete (Label Engine):** Hardware metrics forms, async printing, `api/add_label.php`, ODT injection.
- **Phase 4 Complete (CRM / Rolodex):** `new_customer.php`, `rolodex.php`, `api/add_customer.php`.

---

## [Legacy] Phase 1 & 2 Execution
- **Phase 1 Complete (Setup):** Folders, PDO, `init_db.php`.
- **Phase 2 Complete (UI Shell):** Dark theme CSS, Sidebar Nav, Dashboard stats.
