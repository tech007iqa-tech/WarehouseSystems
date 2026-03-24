# IQA Metal Inventory & Label System

## 1. Project Overview
This application is a local-network inventory management, hardware label printer, and B2B ordering system. It was built using a "Vibe Coding" philosophy: meaning the architecture prioritizes clean, direct, and unbloated foundations that any future developer or AI agent can immediately understand and build upon without heavy setup overhead.

At its core, the app does three things:
1. **Labels:** Generates `.odt` printable physical labels for single hardware units (Laptops, Gaming Consoles) and tracks their exact physical location in the warehouse.
2. **Rolodex:** Manages B2B customer and lead profiles.
3. **Purchasing & Orders:** Baskets existing hardware units into B2B Purchase Orders, updates their status to "Sold", and generates an `.ots` (OpenDocument Spreadsheet Template) form to send to buyers.

---

## 2. The Tech Stack (Strict Rules)
To maintain the "Vibe", this project strictly adheres to a native, lightweight stack.

* **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom roots, flex/grid layouts), and Vanilla JavaScript (ES6+).
* **Backend:** PHP 8+
* **Database:** SQLite3 (Using PDO via `includes/db.php`).
* **Zero Bloat:** **NO** `node_modules`, **NO** Tailwind CSS or Bootstrap, **NO** PHP Frameworks (Laravel), and **NO** Composer packages unless absolutely critical.

### Why No Composer? (The PowerShell Injection Pattern)
Handling document generation natively in PHP usually requires heavy libraries like `PhpSpreadsheet`, which demand Composer and create dependency bloat.
Instead, this project uses **PowerShell Template Injection**. PHP simply generates raw `content.xml` strings containing the user's data and calls local Windows PowerShell scripts to inject that XML securely inside a boilerplate template file (`.odt` or `.ots`). This keeps the repository lightweight and heavily optimized for the host Windows environment.

---

## 3. Core Architecture
The system intentionally splits data across three completely sandboxed SQLite files to ensure separation of concerns and limit the risk of total data corruption:

1. `db/labels.sqlite`: The master inventory of every individual physical item in the warehouse.
2. `db/orders.sqlite`: The high-level records of B2B purchase forms and their financial totals.
3. `db/rolodex.sqlite`: The CRM database storing leads, customers, and contact records.

*(See `ARCHITECTURE.md` for the exact schema structures).*

---

## 4. UI Philosophy
*(See `DESIGN_SYSTEM.md` for exact variables and rules).*
* The interface should feel like premium software: Dark mode, minimalistic, modern, and highly functional.
* Forms should be dynamic. If a feature isn't selected, its sub-options shouldn't clog the screen.
* Operations like database searches (filtering the warehouse) or submitting background forms should utilize JavaScript `fetch()` to prevent full-page reloads and maintain a snappy, native app-like experience.

---

##  Agent Instructions (Read Before Coding)
If you are an AI Agent waking up in this repository, follow these steps before writing code:
1. **Read `ARCHITECTURE.md`** heavily so you don't write bad SQL queries.
2. **Read `SITEMAP.md`** to know the folder structure and where your new file should go.
3. **Read `DESIGN_SYSTEM.md`** to ensure your CSS matches the exact vibes of the existing UI.
4. **Do not install npm or composer packages.** Everything is vanilla.
