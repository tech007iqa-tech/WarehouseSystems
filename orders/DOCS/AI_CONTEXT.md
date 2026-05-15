# 🤖 AI Agent Context: IQA Warehouse System

## 🎯 Quick Start for Agents
This codebase is a **PHP/SQLite** monolith with a custom routing engine. Focus on these files for core logic:
- **Router**: `index.php` (Uses `$_GET['view']` for page fragments in `pages/`)
- **Database**: `core/database.php` (Centralized PDO Singleton)
- **Auth**: `core/auth.php` (RBAC enforcement)

## 🏗️ Architectural Patterns

### 1. State Injection (PHP -> JS)
**Pattern**: Do NOT use global JS variables.
**Implementation**: Look for `<script type="application/json" id="...State">` in PHP files. Use `JSON.parse(document.getElementById('...State').textContent)` in JS.

### 2. Integrated Cross-DB Queries
**Pattern**: Efficiently querying across multiple databases (e.g., `customers` + `orders`).
**Implementation**: Prefer `Database::queryIntegrated($primary, $attachments, $sql, $params)` over manual `ATTACH`. 
**Example**: `Database::queryIntegrated('customers', ['o' => 'orders'], "SELECT ... FROM customers LEFT JOIN o.orders ...")`.

### 3. Centralized Schema Management (Self-Healing)
**Pattern**: Do NOT write `CREATE TABLE` or `ALTER TABLE` in individual pages.
**Implementation**: Add the blueprint to `core/Schema.php`. `Database::getConnection()` calls `Schema::ensure()` automatically. 
**Migrations**: Add new columns to `Schema::runMigrations()`. This ensures all DBs are synchronized on the next load.

### 4. Audit & Accountability
**Pattern**: Log all sensitive actions (deletes, status changes, transfers).
**Implementation**: Use `Audit::log($action, $target_id, $details, $module)`. 
**Visibility**: Admins can view these in Settings via `Audit::getRecent()`.

### 5. Data Security & Backups
**Pattern**: One-click ZIP export of all system databases and robust input sanitization.
**Implementation**: 
- **Backups**: Handled by `api/generate_backup.php`. 
- **Sanitization**: Use `Security::sanitize_float($val)` and `Security::sanitize_int($val)` for all numeric/currency inputs to handle "dirty" data ($, commas).
- **Auditing**: Every backup and sensitive change MUST be logged. `Audit::log()` now includes a **File-based Fallback** in `orders/logs/` if the database is locked.

### 6. Intelligent Vocabulary & Caching
**Pattern**: Auto-completing intake based on historical data with instant UI feedback.
**Implementation**: 
- Use `api/get_vocabulary.php` for data.
- **Caching**: `assets/js/vocabulary.js` uses `sessionStorage` to cache vocabulary, ensuring instant population of datalists without waiting for the network.

### 7. AJAX Live Sync & High-Speed Intake
**Pattern**: Instant data entry without page reloads.
**Implementation**: 
- **Batch Intake**: See `api/add_order_item.php` and `handleBatchSubmit` in `pages/new_order.php`. 
- **Feedback**: AJAX responses should return both the updated data and the HTML fragment for the new row to minimize client-side logic.
- **Concurrency**: Warehouse items use `updated_at` for optimistic locking. Always check for `CONCURRENCY_ERROR` in JS.

### 8. iOS Safari Constraints
**Pattern**: Mobile-first premium feel.
**Implementation**: 
- Use `16px` font on all inputs to avoid zoom.
- Use `100dvh` for full-screen modals.
- Ensure touch targets are 44px+.

## 📅 Calendar Module Logic
- **DB**: `calendar.db`, table `events`.
- **Sync**: Pulls `callback_date` from `customers.db` and `created_at` from `orders.db`.
- **Business Logic**: Enforcement of 08:00 - 17:00 window in both `pages/calendar.php` (UI) and `api/calendar/save.php` (Validation).

## ⚠️ Common Gotchas
- **Permissions**: `assets/db/` must be writable.
- **CSRF**: Ensure `<?= UI::csrf_field() ?>` is present in forms. AJAX calls must include the `csrf_token` in POST body/FormData.
- **Paths**: `index.php` handles inclusions, so relative paths in sub-files can be tricky. Use `core/database.php` as the reference for relative paths.
- **Concurrency**: Warehouse items use `updated_at` for optimistic locking. Always check for `CONCURRENCY_ERROR` in JS.

## 🚀 Efficiency Tip
When modifying views, check if a corresponding `assets/js/[view].js` and `assets/styles/[view].css` exist. `index.php` loads these automatically if they are defined in the `$routes` array.
