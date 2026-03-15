# System Architecture & Database Schema

## 1. Overview
This document outlines exactly how the IQA Metal Label & Inventory app manages its data. 
To ensure maximum stability, we utilize **three separate SQLite 3 database files** (`.sqlite`) located in the `/db/` directory.

The application relies on PHP Data Objects (PDO) for all database interactions. Agents should **always** use Prepared Statements (`$stmt->prepare()`) to prevent SQL Injection and ensure data types are handled correctly.

---

## 2. Database 1: `labels.sqlite`
This database acts as the single source of truth for **individual hardware items** in the warehouse. Each row represents a physical laptop/device that has (or needs) a printed `.odt` label.

### Table: `items`
* **`id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`): System-generated unique identifier (Can be used as a barcode).
* **`type`** (`TEXT`): Hardware category (e.g., 'Laptop', 'Desktop', 'Gaming Console'). Default: 'Laptop'.
* **`brand`** (`TEXT NOT NULL`): Manufacturer (e.g., 'HP', 'Dell', 'Lenovo').
* **`model`** (`TEXT NOT NULL`): The primary model (e.g., 'EliteBook').
* **`series`** (`TEXT`): The exact series (e.g., '840 G3', 'ProBook 650 G2').
* **`cpu_gen`** (`TEXT`): Processer generation (e.g., 'i5-8th', '6th').
* **`cpu_details`** (`TEXT`): Full processor specs (e.g., '2 Cores @ 2.50GHz').
* **`ram`** (`TEXT`): Memory capacity (e.g., '8GB', '16GB', NULL).
* **`storage`** (`TEXT`): Drive capacity (e.g., '256GB SSD', NULL).
* **`battery`** (`BOOLEAN`): 1 = Yes, 0 = No.
* **`bios_state`** (`TEXT`): 'Unlocked' or 'Locked'.
* **`description`** (`TEXT`): Internal condition notes used on B2B forms (e.g., 'Untested', 'For Parts').
* **`status`** (`TEXT`): 'In Warehouse', 'Sold', or 'Pending'.
* **`warehouse_location`** (`TEXT`): Physical location (e.g., 'Shelf A', 'Bin 3').
* **`order_id`** (`INTEGER`): Foreign Key referring to `orders.sqlite` (NULL if not sold).
* **`created_at`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)

---

## 3. Database 2: `orders.sqlite`
This database tracks the high-level **B2B Purchase Forms**. When an order is created, it groups hardware from `labels.sqlite`, assigns them to this `order_number`, and generates the `.ots` spreadsheet.

### Table: `purchase_orders`
* **`order_number`** (`INTEGER PRIMARY KEY AUTOINCREMENT`): Automatically generated distinct invoice number.
* **`customer_id`** (`INTEGER NOT NULL`): Foreign Key referring to `rolodex.sqlite`.
* **`order_date`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)
* **`total_qty`** (`INTEGER`): Total number of individual items on this order.
* **`total_price`** (`NUMERIC`): Total monetary amount for the entire order.
* **`document_path`** (`TEXT`): The relative path to the generated `.ots` Purchase Form file (e.g., `/orders/Order_841748.ots`).

---

## 4. Database 3: `rolodex.sqlite`
This acts as a lightweight CRM (Customer Relationship Management) system. It stores the Buyers, Leads, and Vendors.

### Table: `customers`
* **`customer_id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`)
* **`company_name`** (`TEXT`): Business name (e.g., 'NRU Metals').
* **`contact_person`** (`TEXT NOT NULL`): Primary contact (e.g., 'Vincent', 'King').
* **`email`** (`TEXT`)
* **`phone`** (`TEXT`)
* **`lead_status`** (`TEXT`): 'Active Customer', 'New Lead', 'Inactive'.
* **`notes`** (`TEXT`): Custom background context on the buyer.
* **`created_at`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)

---

## 5. File Generation (The Injection Process)
Because we strictly avoid massive PHP frameworks and Composer bundles, we do **not** use `PhpSpreadsheet` to generate Excel or OpenDocument files.

**How to generate `.ots` and `.odt` files natively:**
1. Maintain "Master Template" files (`label_template.odt`, `order_template.ots`) in the `/templates/` folder.
2. An `.odt` or `.ots` file is inherently just a `.zip` archive containing a `content.xml` file.
3. PHP will calculate strings, generate the final XML output natively (`echo '<text:p>HP Laptop</text:p>'`, etc.), and save this as a `.xml` text file in a temporary folder.
4. PHP uses `shell_exec()` or `exec()` to call local PowerShell (`powershell.exe`).
5. The PowerShell script copies the Master Template to the destination folder, treats the copy as a `.zip` file natively using `Compress-Archive -Update`, and injects our newly generated `content.xml` inside it, over-writing the generic dummy text.

### Example PHP & PowerShell Interaction:
When an agent builds the `api/add_label.php` script, always leverage the existing `templates/scripts/generate_odt.ps1` script (or build a similar one) that uses native Windows tools to execute this "Template Injection". Do not hallucinate PHP ZipArchives if the extension is disabled over the local network.
