# 🤖 AI Agent Project Context
**File:** `PROJECT_CONTEXT.md`
**Purpose:** Read this single file to understand the entire application without needing to scan the whole codebase. Update this file as the project evolves.

---

## 🏗️ 1. Project Overview & Tech Stack
**App:** IQA Metal Inventory, Label Printer & Purchase Order System.
**Goal:** Track physical hardware, print `.odt` labels for them, and basket them into `.ots` Purchase Forms for B2B customers.
**Tech Stack:**
- **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom variables, Flex/Grid), Vanilla JS (ES6+ `fetch()` APIs).
- **Backend:** PHP 8+ handling routing and API endpoints in `/api/`.
- **Database:** SQLite3 using PDO (`includes/db.php`).
- **Strict Rule:** NO `node_modules`, NO Tailwind, NO Bootstrap, NO Composer packages.
- **File Generation:** Do not use `PhpSpreadsheet` or zip extensions. Generate raw `content.xml` arrays in PHP, save them as `.xml`, and use native Windows PowerShell (`Compress-Archive -Update`) to securely inject the `.xml` natively into empty `.odt` and `.ots` MS template files.

---

## 🗄️ 2. Database Architecture (3 Separate SQLite Files)

### A. `labels.sqlite` (The Hardware/Inventory)
Table `items`: `id` (PK), `type` ('Laptop'), `brand`, `model`, `series`, `cpu_gen`, `cpu_details`, `ram`, `storage`, `battery` (BOOL), `bios_state`, `description` (Condition), `status` ('In Warehouse', 'Sold'), `warehouse_location`, `order_id` (FK to orders), `created_at`.

### B. `orders.sqlite` (The Purchase Forms)
Table `purchase_orders`: `order_number` (PK), `customer_id` (FK to rolodex), `order_date`, `total_qty`, `total_price`, `document_path` (Path to generated `.ots`).

### C. `rolodex.sqlite` (The CRM / Leads)
Table `customers`: `customer_id` (PK), `company_name`, `contact_person`, `email`, `phone`, `lead_status`, `notes`, `created_at`.

---

## 🗺️ 3. Folder & UI Sitemap
- `/assets/`: `css/style.css` (Global styles), `js/api.js` (Fetch callbacks).
- `/db/`: The SQLite files.
- `/templates/`: The `.odt`/`.ots` master templates and `.ps1` PowerShell scripts.
- `/includes/`: `db.php` (PDO connections), `header.php`, `footer.php`.
- `/api/`: PHP Endpoints returning JSON for the JS frontend to consume.
- `/exports/`: Rendered labels and orders for download.
- **`designPatterns.md`**: Strict rules on how to write PHP endpoints, Javascript fetch APIs, and SQLite Prepared Statements. Read this before coding!
- **`DEPLOYMENT.md`**: Instructions for setting up the local XAMPP server, enabling SQLite, and configuring PowerShell.
- **`LICENSE.md`**: MIT License.
- **Views:**
  - `index.php`: Dashboard & Stats.
  - `labels.php` & `new_label.php`: Warehouse tracker & form to print physical `.odt`.
  - `orders.php` & `new_order.php`: B2B cart & form to print invoice `.ots`.
  - `rolodex.php` & `new_customer.php`: Leads / Customer tracker.

---

## 🎨 4. Design System / UI Vibe
- **Variables:** Use the roots in `style.css`: `--text-main: #333`, `--link-color: #007bff`, `--bg-page: #f8f9fa`, `--btn-primary-bg`, `--btn-success-bg`.
- **Layout:** Dark, modern, premium hardware aesthetic. Avoid inline blocks; rely heavily on `display: flex;` and `display: grid;`.
- **Interactivity:** Never use full-page POST reloads. All forms should use JS `e.preventDefault()`, show a loading spinner, send data via `fetch()` to `/api/`, and update the UI seamlessly.

---

## 🚀 5. Current Roadmap / Next Steps
* Update this block manually when a phase is complete so the next AI knows where to start.
- [x] **Phase 1: Setup:** Create the folder structure, `includes/db.php`, and initialize SQLite files.
- [x] **Phase 2: UI Shell:** Create `assets/css/style.css` and the Sidebar `header.php`.
- [x] **Phase 3: Label Engine:** Build `new_label.php` UI, JS toggles, and PowerShell ODt injection.
- [x] **Phase 4: CRM:** Build `rolodex.php`.
- [ ] **Phase 5: Ordering:** Build `new_order.php` cart, SQLite updates, and `.ots` injection.
