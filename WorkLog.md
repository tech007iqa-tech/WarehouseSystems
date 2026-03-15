# Work Log - IQA Metal Inventory & Label System

## [Current Session] Phase 3 & 4 Execution
- **Phase 3 Complete (Label Engine):** 
  - Built `new_label.php` with hardware metrics forms.
  - Implemented async javascript in `forms.js` for label printing submission.
  - Architected `add_label.php` API layer connecting to `labels.sqlite` using PDO.
  - Executed PowerShell template injection hook (`generate_odt.ps1`) for generating the actual `.odt` files natively.
  - Built `labels.php` warehouse live tracking dashboard.
- **Phase 4 Complete (CRM / Rolodex):**
  - Built `new_customer.php` for entering B2B lead info.
  - Added newCustomer async fetch handler in `forms.js`.
  - Built `add_customer.php` backend mapping to the `rolodex.sqlite` DB.
  - Built `rolodex.php` unified lead overview panel.
- **Next Steps:** Proceed entirely to Phase 5 of the Roadmap (`Ordering Engine`).

---

## [Legacy] Phase 1 & 2 Execution
- **Phase 1 Complete (Setup):** 
  - Generated all local folder trees (`/assets`, `/api`, `/db`, etc.).
  - Built `includes/db.php` initializing PDO connections.
  - Built `init_db.php` deployment script to generate the 3 SQLite files using the exact schema.
- **Phase 2 Complete (UI Shell):**
  - Built `style.css` matching Vibe Code rules (dark mode, grids).
  - Designed `header.php` containing a sticky Sidebar Nav and global vars.
  - Built `functions.php` for local server formatting.
  - Overhauled `index.php` into a fully functioning dynamic dashboard layout.
- **Next Steps:** Proceed entirely to Phase 3 of the Roadmap (`Label Engine`).

---

## [Legacy] Massive Architecture & Scoping Expansion
- **Shift in Scope:** Evaluated the codebase and expanded the requirements from a simple `.odt` label generator to a complete B2B Purchase Order & Warehouse tracking system.
- **Database Architecture:** Formalized a strict 3-database sandboxed approach (`labels.sqlite`, `orders.sqlite`, `rolodex.sqlite`) to track inventory, orders, and customer leads.
- **Vibe Code Doctrine:** Established the strict rule of *Zero Bloat*: using only Vanilla JS, Native PHP 8, custom CSS roots, and native Windows PowerShell scripting for `.odt` and `.ots` injection (banning Tailwind, node, and Composer packages).
- **Documentation Generation:** Created the ultimate foundational context pack: 
  - `PROJECT_CONTEXT.md` (The single Source of Truth master prompt)
  - `ARCHITECTURE.md` (Schema specs & logic)
  - `DESIGN_SYSTEM.md` (CSS styling)
  - `SITEMAP.md` (Directory mappings)
  - `designPatterns.md` (JS/PHP code patterns)
  - `ROADMAP.md` (Phase-by-phase build checks)
  - `DEPLOYMENT.md` (XAMPP installation guide)
- **Next Steps:** Proceed entirely to Phase 1 of the Roadmap (`Setup & DB Initialization`).

---

## [Legacy Session] ODT Label Generator

## Project Updates Milestones
  - Create labels for print


## Summary of Changes
We successfully implemented a system to generate `.odt` laptop labels from a web form.

## Future Updates
  - Add to database
  - Search database
  - Display labels
  - Edit
  - Delete

### 1. Backend Implementation (`test/add.php`)
- **Purpose**: Handles form submission and generates the label file.
- **Methodology**: 
  - Instead of using heavy libraries like `phpword` (which require Composer), we used a "Template Injection" method.
  - The script copies a master template (`assets/Data Sample Files/Dell Latitude 3520.odt`).
  - It generates a new `content.xml` file with your specific data (Brand, Model, CPU, etc.).
  - It uses a custom **PowerShell script** to inject this XML back into the `.odt` file (treating it as a zip archive).
  - This ensures compatibility on your Windows machine without needing extra PHP extensions like `ZipArchive`.

### 2. PowerShell Helper (`test/update_odt.ps1`)
- **Purpose**: Safely updates the ODT file.
- **Details**: 
  - Called by `test/add.php`.
  - Uses `Compress-Archive -Update` to overwrite the internal XML of the ODT file.
  - This solved the issue where PHP's native zip functions were missing or failing.

### 3. Frontend (`test/newEntry.php`)
- **Purpose**: The user interface for data entry.
- **Changes**:
  - Linked to `assets/css/style.css` for proper styling.
  - Confirmed all fields (Battery, RAM, Storage, CPU Cores, OS, Bios State) are correctly named and sent to the backend.

### 4. Result
- You can now fill out the form at `test/newEntry.php`.
- Clicking "Add Laptop" downloads a formatted `.odt` file ready for printing/editing.
- The label includes complex data like "Battery ✅" and full CPU specs.

