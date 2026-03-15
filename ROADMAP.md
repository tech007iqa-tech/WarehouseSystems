# Project Foundations & Architecture Documentation

## 1. Documentation Map
Before writing any application code, we need a set of documentation files. These serve as the "brain context" for future agents to quickly jump in and start coding perfectly aligned with the project vision.

1. **`README.md`** - The Project Root Context.
   * What this project is: A local warehouse inventory, label printer, and B2B ordering system.
   * Tech Stack rules: Vanilla HTML/CSS, Vanilla JS, PHP 8, SQLite3. No node_modules, no heavy local frameworks.
   * Why we are building it this way (vibe/vision).

2. **`ARCHITECTURE.md`** - The Machine Logic.
   * Explanation of the 3-database system (`labels.sqlite`, `orders.sqlite`, `rolodex.sqlite`).
   * Schema breakdown with types and constraints for the AI to reference when writing SQL commands.
   * How the PowerShell template injection works for `.odt` and `.ots` files (so the AI doesn't try installing heavy Composer packages).

3. **`SITEMAP.md`** - The UI Structure.
   * The folder structure (e.g., `/assets/`, `/db/`, `/includes/`, `/templates/`).
   * The list of pages and their intended single responsibility (e.g., `index.php` is just the dashboard, `api_labels.php` handles all label DB calls).

4. **`DESIGN_SYSTEM.md`** - The Vibe Guidelines.
   * CSS Variables (`--bg-dark`, `--text-main`, `--accent-blue`).
   * The tone of the UI: Dark mode, minimalistic, modern, functional like a premium hardware interface.
   * Rules for future interactions (e.g., "Use fetch() for forms, don't use full page reloads").

---

## 2. Phase Roadmap (How to Build)

### Phase 1: Setup & Foundations (The Skeleton)
* [ ] Create the folder structure.
* [ ] Create standard `.gitignore`.
* [ ] Create standard `includes/db.php` which initializes our 3 SQLite files using PDO with strict error handling.
* [ ] Create dummy data scripts for the databases so we can test features right away.

### Phase 2: Design & Templates (The Vibe)
* [ ] Write `assets/css/style.css` based on `DESIGN_SYSTEM.md`.
* [ ] Set up the UI layout skeleton (Sidebar nav + Main content area) that all pages will use.

### Phase 3: The Label Engine
* [ ] Build the `newEntry.php` UI.
* [ ] Build the JavaScript to handle dynamic form dependencies (like toggling RAM/Storage specs based on checkboxes).
* [ ] Integrate the PowerShell scripts to generate the `.odt` label.
* [ ] Build the PHP backend to insert the form data into `labels.sqlite` and assign its warehouse location.

### Phase 4: Rolodex & CRM
* [ ] Build `rolodex.php` UI to view, add, and edit customers and B2B leads.
* [ ] Create the backend to manage `rolodex.sqlite`.

### Phase 5: The Ordering System
* [ ] Build `newOrder.php` UI.
* [ ] Create an interface to search our warehouse (`labels.sqlite`) and add available laptops to the order cart.
* [ ] Build the PHP logic to dynamically calculate the group rows, generate the `.ots` file via PowerShell, and log the order in `orders.sqlite`.
* [ ] Update the state of laptops in `labels.sqlite` to "Sold" when added to a form.

### Phase 6: Polish
* [ ] Refine the Dashboard (`index.php`) with statistical queries across the 3 databases.
* [ ] Test search filters and database integrity constraints.
