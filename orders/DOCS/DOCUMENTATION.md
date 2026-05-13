# 🏬 IQA Warehouse Inventory & Order Manager

## 📋 Project Overview
A high-performance B2B order management system optimized for rapid warehouse hardware intake, CRM relationship tracking, and administrative scheduling. The application focuses on speed, optimistic data consistency, and a premium "app-like" experience for both desktop and iOS Safari.

---

## 🚀 Core Features

### 1. 📅 Admin Calendar & Scheduling (`pages/calendar.php`)
The command center for administrative oversight and visit scheduling.
- **Dual-View Layout**: Toggle between a data-dense **Monthly Grid** and a focus-driven **Weekly Timeline** (Mon-Fri).
- **Smart Sync Engine**: 
    - **CRM Integration**: Automatically pulls callback dates from the Leads database as "Suggested Tasks."
    - **Conversion Intelligence**: Cross-references visit dates with order creation to tag events as **Converted ✅** (resulting in a sale) or **Window Shopping 👀**.
- **Interactive Timeline Picker**: A gamified, thumb-friendly time selector restricted to business hours (8 AM – 5 PM). 
- **Auto-Duration Logic**: Titles like "Meeting" or "Lunch" trigger automated duration suggestions (1hr-2hr blocks).
- **iOS Optimized**: Features a horizontal-scrolling weekly grid with fixed time labels for zero-friction mobile use.

### 2. 🎯 CRM & Relationship Hub (`pages/leads.php`)
A streamlined pipeline for managing prospects and logging customer interactions.
- **Executive Conversion Bar**: Real-time KPI tracking showing **Active Leads** count and total **Pipeline Value** (potential gross).
- **Priority Follow-ups**: A dedicated "Call Today" section that highlights overdue or immediate callbacks.
- **Visual Pulse Timeline**: A vertical history map using intuitive icons (📞, 📧, 💬) for phone calls, emails, and messages.
- **One-Tap Quick Actions**: Interaction modals allow for rapid logging of common outcomes, which automatically updates the next callback date.
- **Zero-Click Fulfillment**: Promoting a lead to a customer automatically initializes a new batch (order) and redirects the user directly to the intake terminal.

### 3. 🏬 Warehouse Control Center (`pages/warehouse.php`)
Comprehensive management of physical inventory zones and stock levels.
- **Zone Status Logic**: Every shelf/zone is assigned a state: `Working` (active intake), `Audit` (verification), `Warehoused` (long-term), or `Idle` (empty).
- **Advanced Sorting Engine**:
    - **Weighted Status**: Groups "Working" zones at the top for faster access.
    - **Density Sort**: Sort by "Most Items" or "Emptiest" to optimize storage footprint.
- **Optimistic Concurrency**: Prevents "last-writer-wins" data loss. The system verifies `updated_at` timestamps before every save to ensure multiple operators aren't overwriting the same zone.
- **Smart Mapping Exports**: CSV exports automatically map categories based on sector (e.g., Laptops include Battery Health metadata).

### 4. 📦 Batch Builder & Verification (`pages/new_order.php`, `checkout.php`)
The core hardware intake and manifest generation workflow.
- **Live Order Summary**: A searchable, real-time list of added hardware with full-entry inline ✏️ editing (Brand, Model, Series, CPU, etc.) and a **Batch Total QTY** counter for rapid stock verification.
- **Bulk Clipboard Import**: A high-speed intake tool allowing users to paste tab-separated rows directly from spreadsheets (Type, Brand, Model, Series, CPU, Price, QTY). Features automated header detection and price sanitization ($/comma stripping).
- **Checkout Manifest**: A professional verification screen with search-filtered item rows and editable **Order Dates** (backdating/postdating support).
- **Interactive Row Editor**: Clicking any manifest row opens a glassmorphism modal for full metadata editing with **AJAX Live Sync**—changes update the main UI instantly without a refresh.
- **Thermal Labeling**: Integrated generation of 2"×1" labels in Flat ODT format, compatible with standard thermal printers.

---

## 🛠 Technical Architecture

### 1. Database Singleton & Cross-DB Joining
Managed via `core/database.php`, the system uses modular SQLite databases to ensure portability and minimize file-lock contention.
- **ATTACH DATABASE Pattern**: The system performs engine-level joins between `customers.db` and `orders.db` for lightning-fast lookups of customer LTV and balance history.
- **PDO Singleton**: Ensures only one active connection per database file exists during the request lifecycle.

### 2. Frontend State & UI Design
- **Decoupled JSON State**: State is passed from PHP to JS via `<script type="application/json">` blocks, preventing global variable collisions and ensuring data integrity.
- **iOS Safari Optimizations**:
    - **Zoom Prevention**: All inputs use `16px` font enforcement to block iOS auto-zoom on focus.
    - **Dynamic Viewports**: Layouts use `100dvh` to fit perfectly behind the Safari toolbar.
    - **Momentum Scrolling**: Large lists and modals use native-feel scrolling.
- **Design System**: Built on Vanilla CSS variables with a focus on glassmorphism and modern typography (Outfit/Inter).

### 3. Security & Access Control
- **RBAC Guard**: Sessions are verified in `core/auth.php`. Administrators have full system access, while **Operators** are strictly locked to the Warehouse Portal.
- **PRG Pattern**: Implements the **Post/Redirect/Get** pattern to ensure page refreshes never trigger duplicate transactions or stock updates.

---

## 📂 Project Structure Map

- `api/`: AJAX endpoints (JSON) for status updates, transfers, and CRM interaction logging.
- `assets/db/`: Protected SQLite databases (blocked from HTTP access via `.htaccess`).
- `assets/js/`: View-specific logic (e.g., `checkout.js`, `warehouse.js`, `leads.js`).
- `core/`: Shared logic, auth guards, and the central database manager.
- `pages/`: View fragments included by the main `index.php` router.

---

> [!TIP]
> **Maintenance**: Use the **Cleanup Tool** in Settings to identify and remove unused customer profiles to keep the registry high-performance.
