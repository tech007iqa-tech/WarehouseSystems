# 📦 IQA Warehouse Systems 6/16/2026 3:11 PM

[![Version](https://img.shields.io/badge/version-2.1.0-green.svg)](https://github.com/)
[![Tech](https://img.shields.io/badge/Stack-Vanilla_PHP_|_SQLite_|_JS-blue.svg)](https://github.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/)

A premium, high-performance warehouse management and sales logistics ecosystem designed for speed, reliability, and precision. Optimized for physical warehouse environments where quick hardware intake, accurate label logistics, and customer relation lifecycles are mission-critical.

---

## 🚀 The Core Modules

All modules are unified under a single responsive dashboard in `index.php`. Access controls restrict non-admin users to the Warehouse Portal.

### 🏬 Warehouse Control Center (`/prod/pages/warehouse.php`)
*Physical Stock Logistics & Density Tracking*
- **Sector Specifications**: Tailored data entry attributes based on hardware categories:
  - **Laptops**: Tracking of CPU model, generation, series, RAM, storage, battery status, operating system, and BIOS status.
  - **Gaming**: Track Console/Rig details, GPU specs, and RAM/storage.
  - **Desktops**: Direct CPU and workstation configuration.
  - **Electronics**: Simple general specification inventory.
- **Nested Working Zones & Locations**: High-level grid grouping shelves/areas into parent Working Zones (e.g., `Zone A`, `Zone B`, `Inbound`, `General`). Supports drill-down navigation to view and manage sub-locations/shelves.
- **Dynamic Add & Rename**: Add new working zones or sub-locations (with automatic code prefixing) and rename existing ones instantly via inline inputs.
- **Zone Status Logic**: Physical locations are assigned operational states like `Working` (active intake), `Audit` (verification), `Shipping`, `In-Review`, `Warehoused` (long-term), or `Idle` (empty).
- **Intake Optimizations**:
  - **Clone Last Entry**: Quick clone feature pre-fills the intake forms with specifications from the last entered unit.
  - **Bulk Clipboard Import**: Copy-paste tab-separated rows directly from spreadsheet files with auto-header matching and automatic input guards.
- **Labeling Integration**: Generation of 2"×1" Flat XML labels for physical thermal printing without external zip dependencies.

### 📊 Order Builder & Checkout Manifest (`/prod/pages/new_order.php` & `/prod/checkout.php`)
*B2B Batch Intake & Order Verification*
- **Batch Builder UI**: Real-time AJAX-powered intake of customer order items with inline editing, batch counts, and instant totals.
- **Final Checkout**: Verification view showing searchable item details, editable unit prices, editable order dates (postdating/backdating), and total balances.
- **Interactive Modals**: Instant AJAX metadata editing of manifest rows and order ownership transfer between client accounts.

### 🎯 CRM & Relationship Hub (`/prod/pages/leads.php`)
*Outreach Pipeline & Lead Status Tracking*
- **Executive Bar**: High-level real-time KPI overview showing Active Leads count and overall Pipeline Gross Value.
- **Priority Call Queue**: Highlights critical accounts needing callbacks today.
- **Activity Timeline**: Vertical stream mapping interactions using intuitive action triggers (📞 Call, 📧 Email, 💬 Chat).
- **Real-Time SSE Sync**: Uses a Server-Sent Events (SSE) database change stream (`api/sync_stream.php`) to synchronize changes across all workstations instantly under 500ms without client-side polling timers.
- **One-Tap Conversion**: Promotes high-priority leads to customers and automatically redirects to a fresh order batch intake sheet.

### 📅 Admin Calendar (`/prod/pages/calendar.php`)
*Scheduler & Lead Management Calendar*
- **Dual Layouts**: Toggle between Monthly Grid and Weekly Timeline (Mon-Fri) views.
- **Auto Sync**: Intergrates leads' callback dates as "Suggested Tasks."
- **Conversion Badging**: Tags calendar events as **Converted ✅** if visit dates correlate with successful sales orders, or **Window Shopping 👀** if not.
- **Timeline Picker**: Restricted to operational business hours (8 AM - 5 PM) with automated event duration presets (Meeting, Lunch, etc.).

### 📈 Historical Trends Engine (`/prod/pages/trends.php`)
*Business Intelligence Analytics*
- **BI Charts**: Uncapped historical queries parsing sales velocities, pricing curves, GPU/CPU generation dominance, and customer buying trends.
- **CPU Pricing details**: Interactive modal detailing price ranges, averages, and transaction history for CPU lines.
- **Order Preview Manifest**: Interactive inline modal allowing users to preview full order manifests directly from transactions inside trends.
- **Pure-CSS Visualization**: Outfitted with responsive CSS-based charts and multi-tab glassmorphic containers.

### ⚙️ System Settings & Tools (`/prod/pages/settings.php`)
*Administrative Control Panel*
- **Integrity Schema Repair**: Clears cached session structures and runs diagnostic checkups/repairs on all DB structures.
- **Backup Tool**: Stream-based archiving packages all SQLite databases into a secure ZIP file on the fly.
- **Audit Logs**: Secure viewer exposing recent records from the centralized system audit log.

---

## 🛠️ Technology Stack

| Layer | Tech | Description |
| :--- | :--- | :--- |
| **Backend** | PHP 8.1+ | Clean, procedural-routing engine, object-oriented database layers. |
| **Database** | SQLite 3 | Zero-configuration database storage with WAL journaling, optimistic locking, and busy-timeouts. |
| **Frontend** | Vanilla JS / CSS3 | Modern interface built on HSL variables, glassmorphic styles, and layout animations. No tailwind or compiler requirements. |
| **Documents** | Flat XML (FODT) | XML-based OpenDocument tags bypassing zip structures for LibreOffice-compatible label printing. |

---

## ⚙️ Getting Started & Setup

### 1. Pre-requisites
- **PHP 8.1+** with the `sqlite3` and `pdo_sqlite` extensions enabled in `php.ini`.
- **Apache/Nginx** with `.htaccess` support enabled (AllowOverride All) to secure databases.
- **LibreOffice** (optional) to view/print Flat XML `.odt` labels.

### 2. Project Installation
1. Move the `prod` directory to your webserver document root (e.g., `/var/www/html/` or `C:/xampp/htdocs/app`).
2. Move or map the `db` directory so it resides one level above the `prod/` folder (or adjust pathing in `prod/core/database.php`).
3. Set **Write Permissions** on the `db/` directory and `prod/assets/exports/` directories.
4. Open the application in your browser (e.g., `http://localhost/app/`).
5. Databases and tables will initialize and seed themselves automatically on first load via the **Schema Guard** self-healing logic.

---

## 🔍 Internal Guidelines

If you are an AI assistant or human programmer modifying this project, consult the following documents inside `prod/DOCS/` before editing:
- [🗺️ Global Sitemap](GLOBAL_SITEMAP.md): Complete directory map of all components.
- [🤖 AI Agent Context](AI_CONTEXT.md): Coding guidelines, routing patterns, and mobile optimization guidelines.
- [🧠 Technical Deep Dive](AI_TECHNICAL_DEEP_DIVE.md): Detailed information on database tables, schema guard migrations, and label template XML.
- [🔍 Reviewer Checklist](CODE_REVIEW_CHECKLIST.md): Quality control gates, security checks, and code hygiene rules.
