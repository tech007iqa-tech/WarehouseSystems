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

### 2. Cross-DB Joining
**Pattern**: Joins across `customers.db` and `orders.db`.
**Implementation**: Use `Database::attach($pdo, 'other_db', 'alias')`. 
**Example**: `SELECT * FROM orders o LEFT JOIN cust_db.customers c ON ...`

### 3. AJAX Live Sync
**Pattern**: Edit modals that save without refresh.
**Implementation**: Check `assets/js/checkout.js` and `api/update_order_status.php`. Use `fetch()` with `FormData` and update DOM nodes directly on success.

### 4. iOS Safari Constraints
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
- **Paths**: `index.php` handles inclusions, so relative paths in sub-files can be tricky. Use `core/database.php` as the reference for relative paths.
- **Concurrency**: Warehouse items use `updated_at` for optimistic locking. Always check for `CONCURRENCY_ERROR` in JS.

## 🚀 Efficiency Tip
When modifying views, check if a corresponding `assets/js/[view].js` and `assets/styles/[view].css` exist. `index.php` loads these automatically if they are defined in the `$routes` array.
