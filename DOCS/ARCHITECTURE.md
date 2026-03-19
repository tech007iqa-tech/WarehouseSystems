# System Architecture & Database Schema

## 1. Overview
This document outlines exactly how the IQA Metal Label & Inventory app manages its data. 
To ensure maximum stability, we utilize **three separate SQLite 3 database files** (`.sqlite`) located in the `/db/` directory.

The application relies on PHP Data Objects (PDO) for all database interactions. Agents should **always** use Prepared Statements (`$stmt->prepare()`) to prevent SQL Injection and ensure data types are handled correctly.

---

## 2. Database 1: `labels.sqlite`
This database acts as the single source of truth for **Hardware Label Profiles**. Each row represents a master configuration (Template) for a device.

### Flexible Search Logic
The application implements a multi-keyword search engine. When a technician searches, the query is split into individual tokens (keywords). An item is only returned if **every keyword** is found in at least one of its major technical fields (Brand, Model, Series, CPU, etc.). This "AND" logic allows for highly precise filtering, such as searching "HP 840 i5" to narrow down hundreds of items instantly.

### Table: `items` (The Label Master List)
* **`id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`): System ID for the template.
* **`type`** (`TEXT`): Hardware category (e.g., 'Laptop', 'Desktop', 'Gaming Console').
* **`brand`** (`TEXT NOT NULL`): Manufacturer.
* **`model`** (`TEXT NOT NULL`): Primary model name.
* **`series`** (`TEXT`): Exact series details.
* **`cpu_gen`** (`TEXT`): Processor generation (e.g., '11th Gen').
* **`cpu_specs`** (`TEXT`): Exact processor model (e.g., 'i7-11850H').
* **`cpu_cores`** (`TEXT`): Number of physical cores.
* **`cpu_speed`** (`TEXT`): Clock frequency (e.g., '2.40GHz').
* **`cpu_details`** (`TEXT`): DEPRECATED (Stored consolidated cores/speed).
* **`ram`** (`TEXT`): Memory capacity.
* **`storage`** (`TEXT`): Drive capacity.
* **`battery`** (`BOOLEAN`): Battery included (1/0).
* **`battery_specs`** (`TEXT`): Health % and cycle counts.
* **`gpu`** (`TEXT`): Graphics processing details.
* **`screen_res`** (`TEXT`): Screen Resolution / Size.
* **`webcam`** (`TEXT`): Camera specs.
* **`backlit_kb`** (`TEXT`): Backlit keyboard status (Yes/No).
* **`os_version`** (`TEXT`): Operating system details.
* **`cosmetic_grade`** (`TEXT`): Grade A, B, or C.
* **`work_notes`** (`TEXT`): Detailed technical/reparation notes for refurbished units.
* **`description`** (`TEXT`): Internal condition (e.g., 'Untested', 'Refurbished').
* **`warehouse_location`** (`TEXT`): Physical location of the master batch or prototype.
* **`created_at`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)

---

## 3. Database 2: `orders.sqlite`
This database tracks **B2B Purchase Orders**. It utilizes a Header-Line architecture to allow multiple instances of a Label Profile to be sold in a single transaction.

### Table: `purchase_orders` (Header)
* **`order_number`** (`INTEGER PRIMARY KEY AUTOINCREMENT`): Unique invoice number.
* **`customer_id`** (`INTEGER NOT NULL`): Foreign Key to `rolodex.sqlite`.
* **`order_date`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)
* **`total_qty`** (`INTEGER`): Sum of all quantities on the order.
* **`total_price`** (`NUMERIC`): Total dollar value.
* **`document_path`** (`TEXT`): Relative path to the generated `.ots` file.

### Table: `order_items` (Lines)
* **`line_id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`)
* **`order_number`** (`INTEGER NOT NULL`): Link to `purchase_orders`.
* **`item_id`** (`INTEGER NOT NULL`): Link to the master Label template in `labels.sqlite`.
* **`brand`** / **`model`** (`TEXT`): Snapshotted display names.
* **`specs_blob`** (`TEXT`): A snapshot of the exact specs at time of sale.
* **`qty`** (`INTEGER`): Number of units sold.
* **`unit_price`** (`NUMERIC`): Price per unit.
* **`total_price`** (`NUMERIC`): `qty * unit_price`.

---

## 4. Database 3: `rolodex.sqlite`
This acts as a lightweight CRM (Customer Relationship Management) system. It stores the Buyers, Leads, and Vendors.

### Table: `customers`
| Column | Type | Notes |
|---|---|---|
| `customer_id` | INTEGER PK AUTOINCREMENT | |
| `company_name` | TEXT | |
| `contact_person` | TEXT NOT NULL | |
| `email` | TEXT | |
| `phone` | TEXT | |
| `address` | TEXT | Primary shipping address |
| `tax_id` | TEXT | Linked to "Address" field in UI (per user request) |
| `website` | TEXT | Official company URL |
| `lead_status` | TEXT | `'Active Customer'`, `'New Lead'`, `'Inactive'` |
| `notes` | TEXT | Internal background notes |
| `created_at` | DATETIME | Default `CURRENT_TIMESTAMP` |

---

## 5. Document Generation (Structural Surgery & Printing)
Because we strictly avoid massive PHP frameworks or Composer bundles, we utilize a native **"Structural Surgery"** approach to generate high-fidelity OpenDocument files (`.odt`, `.ots`).

### The Generation Ecosystem:
1. **Master Templates**: Clean ODF files (`templates/label_template.odt`, `order_template.ots`) serve as the structural backbone.
2. **Structural XML Surgery**: 
   - Instead of replacing the entire `content.xml`, the PowerShell engine (`templates/scripts/generate_*.ps1`) extracts the original XML from the template.
   - It uses **Regex grafting** to inject dynamic data specifically into the `<office:text>` (Writer) or `<office:spreadsheet>` (Calc) containers.
   - This approach preserves 100% of the original metadata, font face-declarations, and namespaces embedded in the master template.
3. **Security & ODF Compliance**:
   - The generation engine surgically deletes the `Configurations2/` directory and `manifest.rdf` entry. These are macro-bearing config files that trigger LibreOffice's "Macros Disabled" or "File Corrupt" warnings on externally generated documents.
   - The system **rebuilds the `manifest.xml`** from scratch to ensure strict conformance with ODF 1.2 ISO schemas.

### Hybrid Printing Approach:
  - **Browser Direct:** Instant, zero-file labels for rapid warehouse use. Strictly **2" x 1"** dimensions via margin-less CSS. Optimized for **1-PDF-file, 2-page** output (Label A: Branding + Label B: Specs).
  - **Windows Launch:** Precise, persistent document generation for official forms (.odt / .ots).
- **Smart Panels:** Sidebar widgets in `hardware_view.php` utilize `<details>` toggles to hide deep technical specs while keeping critical info (**CPU/Series/RAM/Storage**) visible.

### Data Maintenance Policy
- **Sold Records**: Unlike order history which is protected, individual hardware profiles in `labels.sqlite` can be deleted regardless of status (including 'Sold') to allow for warehouse database maintenance.
- **Default Status**: New hardware intake defaults to `'In Warehouse'` status when conditions are marked as 'Untested'.

### Technical Implementation:
- **XML Escaping**: All dynamic strings are sanitized using `htmlspecialchars(..., ENT_XML1)` to ensure valid technical XML.
- **Resource Lock Guard**: PowerShell logic detects if a file is already open in a separate application and warns the user before attempting an update.

---

## 6. API Hardening (Path Resolution)
Because the app runs on local Windows/XAMPP environments, relative paths (`../includes/`) can occasionally fail. All API endpoints in `/api/` now use absolute directory resolution:
```php
require_once __DIR__ . '/../includes/db.php';
```
This ensures that `shell_exec` and file writes always resolve to the correct project root regardless of the current working directory.

## 7. Order Rollback (Data Integrity)
To handle cancelled deals, the system supports a **Rollback** mechanism:
1. The physical `.ots` file is permanently deleted from `/exports/orders/`.
2. All line-item records linked to the `order_number` are deleted from `order_items`.
3. The main Order header is deleted from `purchase_orders`.
*Note: Because Label Profiles are reusable templates, they do not need to be "returned" to inventory status.*
## 8. System Fortification (Self-Healing)
To ensure the application remains operational even after accidental file deletion or potential corruption, the system implements a "Self-Healing" architecture.

### Schema Guard
Located in `includes/schema_guard.php` and integrated into the core `includes/db.php` connection logic. Every time the application connects to a database, the Guard verifies the existence of all tables and columns. If a database file is missing or a table is deleted, the engine **silently rebuilds the empty schema** on the fly, preventing application crashes.

### Proactive Health Monitoring
The dashboard (`index.php`) utilizes `includes/status_functions.php` to perform a `PRAGMA integrity_check` on all SQLite files. If the physical file is damaged or unreadable, a **Red Alert** is triggered on the dashboard to notify the technician immediately.

### Duplicate Prevention Engine
The API layer (`api/add_label.php`) and frontend (`assets/js/forms.js`) work together to prevent inventory clutter:
1. **Technical Fingerprinting**: When adding hardware, the system scans for exact matches in Brand, Model, Series, CPU Specs, RAM, and Storage.
2. **Contextual Reuse**: If a match is found in the same warehouse location, the system reuses the existing ID and refreshes the ODT label instead of creating a redundant record.
3. **Technician Notification**: The UI provides a high-transparency confirmation whenever a profile is reused rather than created fresh.
## 9. Robust UX Patterns (Mobile-First)
To ensure the application is usable in a warehouse environment (gloves, moving equipment, small screens), we adhere to several robust design patterns:

### CSS Checkbox Hack (Menu)
Instead of relying on JavaScript for the mobile hamburger menu which can fail in low-memory states or high-latency environments, we use a hidden `<input type="checkbox">` and the sibling selector (`~`) to toggle sidebar visibility. This ensures the UI remains interactive even if scripts are blocked or fail to load.

### Table-to-Card Transformation
On screens smaller than 900px, standard data tables use a CSS transformation:
1. `display: none` is applied to the `<thead>`.
2. `display: block` is applied to `<tr>` and `<td>`.
3. `data-label` attributes on `<td>` elements are injected via CSS `content: attr(data-label)` to create labels for the vertical card layout.

## 10. File System Integrity
The system treats the folder structure as part of its "state". The `ensure_system_folders()` function in `includes/functions.php` is responsible for:
- Creating missing export directories (`exports/labels`, `exports/orders`).
- Setting up the `db/backups` directory.
- Injecting security `.htaccess` files to prevent directory indexing and protect sensitive B2B documents.
- Resolving absolute paths for the PowerShell engine to ensure ODT generation succeeds across different local environments.
