# 🏬 IQA Warehouse Inventory & Order Manager
## 📋 Project Overview
A robust, high-performance order procurement application designed for building and managing complex inventory batches. Optimized for both rapid warehouse intake on mobile devices and professional desktop manifest generation.

---

## 🚀 Core Features

### 1. Active Customer Registry Dashboard
Manage the entire B2B database from a high-performance centralized interface.
- **Dual-Pane Independent Scrolling**: Optimized layout for all viewport sizes.
- **Intelligent Sorting & Persistence**: 
    - Sort customers by **Name**, **Total Orders**, **Lifetime Value**, or **Last Order Date**.
    - **Session Memory**: The system remembers your sort preference as you navigate.
    - **Default View**: Optimized to show **Recent Purchases** first.
- **Live Business Intelligence**:
    - **Lifetime Value (LTV)**: Automatically calculated total gross value displayed as a vibrant currency badge.
    - **Order History**: Real-time summary of active vs. completed batches with deep links.
- **CRM & Lead Hub** (`pages/leads.php`):
    - **Executive Conversion Bar**: Real-time tracking of Active Leads and total Pipeline Value.
    - **Priority Follow-ups**: A dedicated "Call Today" section that highlights leads with pending or overdue callback dates.
    - **Visual Pulse Timeline**: A date-separated logging system that maps interaction methods (Phone, Email, Message) to intuitive icons (📞, 📧, 💬).
    - **One-Tap Quick Actions**: Instantly log common interactions directly from the modal, auto-updating dates and history notes.
    - **Quick Add Lead**: A high-speed capture modal that allows for seamless lead registration without leaving the CRM pipeline.
    - **Automated Financial Intelligence**: Real-time calculation of **Total Balance** and **Last Order ID** from the orders database.
- **Account Management & Auto-Batch Workflow**:
    - **Zero-Click Fulfillment**: Registering a full customer via `pages/new_customer.php` automatically initializes a new "Fresh Batch" (order) and teleports the user directly to the Order Entry screen.
    - **Secure Cascading Deletion**: Permanently remove a customer and all their associated orders/items with a single action.

### 2. Batch Builder (Order Entry) — `pages/new_order.php`
The central tool for adding hardware to active orders.
- **Dynamic Logic**: Intelligent dropdowns that filter models and series based on the selected brand (Dell, HP, Apple, etc.).
- **Live Search**: Real-time search bar in the Order Summary panel filters all added items instantly without a page reload.
- **Inline Editing**: Each item row includes a ✏️ pencil icon to toggle Qty and Unit Price edit fields, with auto-submit on change and a smart `#order-summary` anchor on reload so the viewport never jumps.
- **No Limit**: The full order is always displayed (the previous 20-item cap has been removed), so the search box always scans the complete manifest.
- **Optimized for Mobile**: Touch-friendly inputs enforcing `16px` font sizes to prevent iOS Safari auto-zoom.

### 3. Verification & Checkout — `checkout.php`
The final stage before manifest delivery.
- **Live Manifest Search**: A flexible search bar below the "Final Batch Verification" header filters all item rows in real time.
- **Interactive Row Editing**: Click any item row to open a premium glassmorphism **Edit Item** modal with animated slide-up/scale-in transitions.
  - Full metadata editing: Brand, Model, Series, CPU/Gen, Condition/Comments, Qty, Unit Price.
  - **AJAX Live Sync**: Changes are persisted and immediately reflected in the main table UI without a page reload, preserving unsaved changes in other manifest rows.
  - **🖨️ Print Label (.odt)**: Generate a 2"×1" Thermal Label as a standards-compliant OpenDocument Text file directly from the modal.
- **Adaptive Layout**: Tables transform into mobile-friendly cards on small screens to prevent horizontal scrolling.
- **Live Recalculation**: Subtotals and grand totals update instantly as you adjust quantities or pricing.
- **Export Formats**:
  - 🖨 **Print Manifest**: Professional PDF-ready layout with approval signature lines.
  - 📊 **CSV Export**: Clean, Excel-ready data distribution with separate columns for Brand and Model.

### 4. Warehouse & Inventory Control — `pages/warehouse.php`
- **Location Status Tracking**:
    - **Operational States**: Every zone can be assigned a status like `Working`, `Audit`, `Warehoused`, or `Idle`.
    - **Color-Coded Badges**: Visual indicators across the gate and header provide immediate context of zone activity.
    - **Custom Statuses**: Users can define their own status types and associated colors.
- **Advanced Sorting Engine**:
    - **Weighted Status Sort**: Groups zones logically (Working first, Idle last) rather than just alphabetically.
    - **Density Sorting**: Sort by "Most Items" or "Emptiest" to optimize shelf space utilization.
    - **Persistent Preference**: The system remembers your chosen sort order using `localStorage`.
- **Working Zone Management**: 
    - **Bulk Rename**: Update all inventory items when a shelf is renamed via the Gate interface.
- **Enhanced CSV Export**: 
    - **Smart Mapping**: Automatically sets the "Type" column based on the sector.
    - **Metadata Rich**: Includes battery health status for laptop exports as requested.

### 5. Global Batch Registry (Orders) — `pages/orders.php`
Professional administrative oversight for all active and completed batches.
- **High-Density Table View**: Replaced legacy card layout with a data-dense, professional HTML table for better oversight.
- **Interactive Sorting**: Dynamic, client-side sorting for **Batch ID**, **Company/Account**, and **Date Created**.
- **Smart Date Sorting**: The system understands chronological dates (e.g., "Apr 22, 2026") ensuring accurate historical sorting.
- **Inline Status Management**: Update fulfillment states (Paid, Dispatched, etc.) directly from the table row via AJAX.
- **Individual Order Deletion**: Remove specific batches and their items from the registry with a dedicated trash icon and confirmation gating.
- **Order Transfer System**: Specialized utility for fixing assignment errors by relocating batches between accounts instantly.

### 6. ODT Label Generation — `generate_odt.php`

### 7. Role-Based Access Control (RBAC)
The application implements a secure, role-based permission system to partition administrative and operational tasks.
- **User Roles**:
    - **Admin**: Full access to all system modules, including Customer Registry, Leads, Orders, and Settings. Only the root `admin` account can manage other users.
    - **Operator**: Restricted access. Automatically redirected to the Warehouse Portal upon login. Cannot access CRM leads, full order registries, or system maintenance tools.
- **Enforcement**: Access is verified at the routing level in `index.php` and via the `core/auth.php` session guard.

### 8. Real-time Stock Alerts (Concurrency Control)
To prevent data loss in multi-user environments, the Warehouse module implements an optimistic locking strategy.
- **Timestamp Sync**: Every inventory item includes an `updated_at` timestamp.
- **Collision Detection**: When saving changes, the system compares the form's `last_updated_at` with the database's current state.
- **Collision Response**: If a mismatch is detected (meaning another user saved changes while you were editing), the save is blocked and a **CONCURRENCY_ERROR** alert is displayed.

---

## 🛠 Technical Architecture

### 1. Centralized Database Manager (`core/database.php`)
The system utilizes a **Singleton Pattern** to manage multiple SQLite connections efficiently.
- **Connection Pooling**: `Database::getConnection($db_name)` ensures only one PDO instance exists per database file during a request.
- **Cross-DB Joining**: The manager supports the `ATTACH DATABASE` command, allowing complex JOIN queries between `customers.db` and `orders.db` at the database engine level, eliminating slow PHP-side loops.

### 2. Session & Security Hardening
- **Session Fixation Protection**: The system rotates the session ID using `session_regenerate_id(true)` upon successful login to prevent hijacking.
- **Auto-Redirection**: Authenticated users are automatically redirected away from the login page to their appropriate dashboard based on their role.
- **Distributed SQLite**: Data is partitioned into modular `.db` files (Customers, Orders, Users, Warehouse) to minimize lock contention and improve portability.

### 3. Mobile-First & iOS Safari Optimizations
- **16px Input Enforcement**: All modal inputs use `font-size: 16px !important` to prevent iOS Safari auto-zoom on focus.
- **Dynamic Viewport Height**: Overlay uses `100dvh` so it fits correctly behind Safari's collapsible toolbar.
- **Momentum Scrolling**: Modal content uses `-webkit-overflow-scrolling: touch` for native-feel scrolling when the keyboard appears.
- **Clipboard Fallback**: Copy-to-clipboard uses `navigator.clipboard` with a hidden `<textarea>` fallback for non-HTTPS contexts (older Safari).

### JS Architecture & State Management
- **Decoupled JSON State**: The application no longer pollutes the global `window` namespace with PHP variables. Instead, state is injected into the DOM as `application/json` script blocks and parsed by dedicated getter functions in JS (e.g., `getCheckoutState()`).
- **Scalable Routing**: `index.php` utilizes a centralized route mapping array that manages both page inclusions and contextual CSS loading, eliminating large conditional blocks.
- **External Logic Modules**: All functional logic is encapsulated in external modules (e.g., `assets/js/checkout.js`) which are versioned dynamically via PHP `filemtime()` to ensure instant cache updates upon modification.
- **API Integration**: Core actions like order status updates and transfers are handled via asynchronous `fetch` calls to dedicated PHP scripts in the `/api/` directory.

---

## 📂 Project Structure

```bash
├── api/               # Decoupled JSON API endpoints
│   ├── get_warehouse_stock.php
│   ├── get_interaction_logs.php # Fetches CRM history for a customer
│   ├── save_lead.php            # Updates CRM metadata and logs interactions
│   ├── update_order_status.php
│   └── transfer_order.php
├── assets/
│   ├── db/            # SQLite databases (Blocked from public access via .htaccess)
│   ├── js/
│   │   ├── checkout.js   # All checkout manifest JS logic
│   │   ├── warehouse.js  # Warehouse inventory logic
│   │   └── new_order.js  # Batch builder JS logic
│   ├── styles/        # Per-page CSS files
│   └── ts/            # TypeScript source (Legacy/Dev reference)
├── core/              # Authentication and shared logic
│   ├── auth.php
│   ├── login.php
│   └── logout.php
├── pages/             # Page fragments included by index.php
│   ├── customer_registry.php
│   ├── new_customer.php
│   ├── new_order.php
│   ├── orders.php
│   ├── warehouse.php
│   └── settings.php
├── DOCS/
│   └── DOCUMENTATION.md
├── index.php          # Main application gateway & router
├── checkout.php       # Verification, modal editor & export hub
├── generate_odt.php   # 2×1 Thermal Label ODT generator
└── README.md
```

---

## ⚙️ Maintenance & Troubleshooting

### Database Cleanup
To keep the registry tidy, admins can use the **Cleanup Tool** in the Settings menu. This utility cross-references the `customers` and `orders` databases to identify and safely remove profiles that have never been used in a transaction.

### Label Generation
`generate_odt.php` uses PHP's native `file_put_contents()` and `tempnam()` — no extensions required. If a label downloads but doesn't open, ensure LibreOffice or compatible software is set as the default handler for `.odt` files on your device.

### Critical Paths
The application utilizes `realpath()` for all database attachments. This ensures that the SQLite `ATTACH DATABASE` commands resolve correctly, even when pages are included deep within the folder hierarchy.

### iOS Safari Notes
- The app is fully tested on Safari for iOS. Ensure the server is accessed over HTTPS or localhost for the `navigator.clipboard` API to be available; otherwise the fallback `execCommand('copy')` is used automatically.
