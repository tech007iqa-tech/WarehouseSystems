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

## ✅ Phase 7.8: Universal Hardware Pattern
* [x] **The Action Strip**: Standardized 🖨️, 📂, and ✏️ behavior across all inventory views.
* [x] **Flash Launch**: Implemented "Open Existing" logic to instantly launch ODTs via the Windows Bridge.
* [x] **Master Specification Sync**: Unified `new_label.php` and `refurbished_view.php` with a shared technical form.
* [x] **Profile Cloning**: Added "One-Click Duplicate" to the intake sidebar for rapid processing.
* [x] **CPU Intelligent Intake**: Added structured catalog for auto-filling processor specs based on Generation.

## ✅ Phase 8: Analytics & Reporting
* [x] **Inventory Aging**: Tracked items sitting >30 days in warehouse.
* [x] **Sales Trends**: Created `analytics.php` to visualize top customers and sales velocity.
* [x] **Reporting Engine**: Created `api/get_analytics.php` for high-performance metric delivery.

## ✅ Phase 8.5: Labels Page UX Overhaul
* [x] **Mobile-First Data Tables**: Implemented a responsive card-based layout using CSS techniques for warehouse technicians on phones.
* [x] **Desktop Table Optimization**: Slimmed down columns and streamlined the Action Strip to use icon-only buttons (`🖨️`, `📂`, `✏️`, `🗑`).
* [x] **Floating Action Button**: Added a fast "+ Create New Label Profile" sticky UI.
* [x] **Filter Engine Upgrade**: Readied the filter bar for advanced condition-based queries and search clearing.

## 🚀 Phase 8.6 & 8.7: Native Integrations & State Management (Planned)
* [ ] **Native File Launching**: Connect the `📂 Open` action in `labels.php` directly to Windows default applications.
* [ ] **Inventory Status Logic**: Default new intakes to "Untested" and implement robust cleanup/deletion of "Sold" inventory.

## ✅ Phase 9: Thermal Printer Optimization (Zebra 2x1)
* [x] **Strict 2x1 Sizing**: Enforced 2in x 1in dimensions with zero browser margins in `print_label.php`.
* [x] **Single-Sheet Hybrid**: Unified Branding (Label A) and Specs (Label B) into a single physical label output.
* [x] **Batch Labeling**: Ability to select multiple items and generate a single multi-page PDF/ODT.

## ✅ Phase 11 & 12: Unified Mapping & Logic
* [x] **Unified Mapping Layer**: Created `includes/hardware_mapping.php` as the single source of truth for database keys.
* [x] **Intelligent CPU Intake V2**: Added suffix-based auto-fill for Core Count and Clock Speed in `forms.js`.
* [x] **Dynamic Scaling Engine**: Text automatically wraps and shrinks (down to 4pt) to fit technical specs on the 2x1 label.

## ✅ Phase 13: 📦 The Dispatch Desk
* [x] **Sold Item Separation**: Created `dispatch.php` to handle physical logistics for items that have left the warehouse.
* [x] **Rolling Archival**: Implemented 90-day filter for active shipments while preserving full lifetime history.

## ✅ Phase 14: 🏷️ Tiered B2B Pricing
* [x] **Customer Tiers**: Integrated **Gold (10%)**, **Silver (5%)**, and **Bronze (0%)** discounts into the Rolodex.
* [x] **Auto-Discount Engine**: The Order Engine now automatically applies tier-based discounts to line items.

## ✅ Phase 15: 📊 Performance Dashboard V2
* [x] **Financial Reporting**: Detailed profitability reports and top buyer leaderboard in `analytics.php`.
* [x] **Logistical Decoupling**: Dispatched items now ignore physical backlog counts.

---

## 🚀 Status: PHASE 17 IN PROGRESS
The system has successfully implemented full Audit Logging for all hardware and order state changes. The next goal is **Phase 17: Bulk Batching Tool** to allow rapid multi-select warehouse moves and condition updates.
