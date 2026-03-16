# Project Roadmap

This document tracks the high-level progress of the IQA Metal Label & Inventory system.

 ---

## ✅ Phase 1: Setup & Foundations
* [x] Create the folder structure and `.gitignore`.
* [x] Initialize `includes/db.php` with 3-database PDO logic.
* [x] Create `init_db.php` to generate SQLite tables natively with correct schemas.
* [x] Set up standard helper functions in `includes/functions.php`.

## ✅ Phase 2: Design & Templates
* [x] Write `assets/css/style.css` (Premium dark mode design system).
* [x] Build `includes/header.php` and `includes/footer.php` shell with sidebar navigation.
* [x] Overhaul `index.php` into a dynamic dashboard skeleton.

## ✅ Phase 3: The Label Engine (Inventory)
* [x] Build `new_label.php` with hardware specification forms.
* [x] Implement dynamic spec toggling and AJAX submission in `forms.js`.
* [x] Create `api/add_label.php` backend.
* [x] Implement PowerShell template injection via `generate_odt.ps1` for physical label generation.
* [x] Build `labels.php` to track warehouse stock live.

## ✅ Phase 4: Rolodex & CRM (Leads)
* [x] Build `rolodex.php` and `new_customer.php` UI.
* [x] Create `api/add_customer.php` to manage the Rolodex database.
* [x] Add customer status badges (Lead, Active, Inactive).

## ✅ Phase 5: The Ordering Engine (B2B)
* [x] Build `new_order.php` with a 4-step dynamic cart interface.
* [x] Implement **Fingerprint Grouping** in `new_order.js` to merge identical units into single line items.
* [x] Create `api/orders_api.php` to process JSON cart payloads.
* [x] Implement PowerShell injection for `.ots` Purchase Forms (`generate_ots.ps1`).
* [x] Link `labels.sqlite` to `orders.sqlite` (Mark items as 'Sold' and store `order_id`).
* [x] Create `orders.php` history view with download links.

## ✅ Phase 6: Polish & CRUD (Management)
* [x] **Quick Locate:** Wired up the Dashboard search bar to instantly find items by ID/Barcode.
* [x] **Warehouse Filters:** Added debounced search and status filtering to `labels.php`.
* [x] **Inventory CRUD:** Added Inline Editing and deletion for all hardware entries.
* [x] **CRM CRUD:** Added Inline Editing and deletion for customer records.
* [x] **Data Integrity:** Implemented logic guards to prevent deleting Sold items or customers with orders.
* [x] **UI Polish:** Consistent badging, loading states, and error messaging across all views.

## ✅ Phase 7: System Fortification & Robust UX
* [x] **Self-Healing DB**: Implemented `Schema Guard` to automatically rebuild missing/corrupted tables.
* [x] **Proactive Health**: Added Dashboard alerts and `settings.php` for PRAGMA integrity scans.
* [x] **File System Repair**: Integrated automatic folder reconstruction into the "Deep Integrity Repair" tool.
* [x] **iPhone Optimization**: Implemented CSS Checkbox Hack menu, 48px touch targets, and vertical "card" layouts for warehouse use.
* [x] **Navigation Polish**: Unified sidebars and sidebar-stacking for better intuition on mobile devices.

## ✅ Phase 7.5: ODF Stability & Hybrid Printing
* [x] **Native Launch Bridge**: Replaced browser downloads with direct Windows application launching via `api/open_windows_file.php` (Orders).
* [x] **Zero-Storage Label Printing**: Created `print_label.php` for high-speed, browser-native label output without disk writes.
* [x] **Document Engine v3 (Structural Surgery)**: Implemented Regex-based XML injection to preserve 100% of master template namespaces and styles.
* [x] **Security Hardening**: Surgically removed `Configurations2/` and `manifest.rdf` from generated ODF files to eliminate LibreOffice macro warnings.
* [x] **ODF Manifest Rebuild**: Implemented automatic `manifest.xml` reconstruction in PowerShell scripts for strict ISO schema compliance.

## 🚀 Phase 8: Analytics & Reporting (Planned)
* [ ] **Inventory Aging**: Track how long items sit in the warehouse before being sold.
* [ ] **Sales Trends**: Visualize top customers and most popular hardware models.
* [ ] **Thermal Optimization**: Investigating 4x6 margin-less label templates for thermal printers.

---

## 🚀 Status: PHASE 7.5 COMPLETE
The system now features a robust, hybrid printing experience: instant browser-native labels and precise, persistent B2B documents. All ODF corruption issues are resolved. Phase 8 (Analytics) is the next focus area.
