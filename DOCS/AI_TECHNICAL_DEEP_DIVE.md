# 🧠 AI Technical Deep Dive & Handover

This document serves as a "shortcut" for AI agents to understand the underlying logic of the IQA Warehouse Systems without reading every single file.

## 🏗️ Core Module Differences
1.  **Labels Module (`/labels`)**:
    *   **Logic**: Procedural PHP.
    *   **DB**: Uses `includes/db.php` which initializes 4 global PDO objects (`$pdo_labels`, `$pdo_orders`, `$pdo_rolodex`, `$pdo_audit`).
    *   **Auditing**: Uses `log_audit_event()` helper defined in `includes/audit.php`.
2.  **Orders Module (`/orders`)**:
    *   **Logic**: Semi-Object Oriented.
    *   **DB**: Uses a `Database` singleton class (`core/database.php`).
    *   **Routing**: View-based routing via `index.php?view=...`. Pages are located in `pages/`.
3.  **Marketing Module (`/marketing`)**:
    *   **Logic**: Modular Procedural.
    *   **DB**: Uses `get_marketing_db()`, `get_labels_db()`, and **`get_master_crm_db()`**.
    *   **Sync**: Directly synced with the Master CRM (`customers.db`).
    *   **Design**: Modern **Teal/Lime** design palette (`#007268`).

## 🛠️ Key Technical Patterns
### 1. Smart Sync (Master CRM)
The system uses a **Single Source of Truth** for people (Leads/Customers).
*   **Database**: `orders/assets/db/customers.db`.
*   **Format**: All new accounts must use the `CUST-XXXXXXXX` ID format (randomized string).
*   **Behavior**: A lead captured in Marketing is instantly visible in the Order Manager.

### 2. Self-Healing Schemas
Every module has a `schema_guard.php`.
*   **Trigger**: Executed on every database connection initialization.
*   **Logic**: Uses `CREATE TABLE IF NOT EXISTS` and `PRAGMA table_info()` to check for missing columns and run `ALTER TABLE` migrations automatically.

### 3. Flat XML ODT Generation
*   **Location**: `labels/api/reprint_label.php`.
*   **Method**: Uses `str_replace()` on a `.fodt` (Flat XML) template.
*   **Benefit**: No `ZipArchive` dependency. Files are portable and work immediately with LibreOffice.

### 4. iOS / Warehouse Optimization
*   **Touch Targets**: Buttons are strictly `48px` minimum height.
*   **Colors**: High-contrast light themes for operational modules; vibrant Teal/Lime for Marketing.

## ⚠️ Recent Critical Fixes (April 2026)
*   **CRM Sync**: Integrated Marketing Hub with the Master CRM (`customers.db`) using a dual-database singleton pattern.
*   **ID Integrity**: Fixed `NOT NULL` constraint issues by implementing custom `CUST-` ID generation in the Marketing leads module.
*   **Marketing Fixes**: Resolved pathing issues by switching assets to relative paths.

## 🔍 Token-Saving Tips
*   Don't read `hardware_form.php` unless editing labels; it's a massive UI component.
*   Check `GLOBAL_SITEMAP.md` first to find the correct `api/` or `core/` file.
*   Always use `__DIR__` for includes to maintain XAMPP portability.
