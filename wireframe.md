# Label APP & Inventory System - Wireframe & Architecture

## 1. Overview
The **Label APP & Inventory System** is a robust, local-network application built with **HTML, CSS, JavaScript, PHP, and SQLite**. What started as a simple label generator now serves as a complete warehouse tracking system. It generates printable `.odt` hardware labels for individual machines, tracks their warehouse location, pairs them with customer orders, and generates OpenDocument Spreadsheet Templates (`.ots`) for Purchase Forms.

To meet the architectural requirements, the system uses **three separate SQLite databases** to ensure separation of concerns: one for Labels, one for Orders, and one for a Customer Rolodex.

---

## 2. Screens & User Flow

### Screen 1: Dashboard / Home (`index.php`)
This landing page provides an overview of the warehouse and links to the tools.
- **Header:** IQA Metal Inventory & Label APP
- **Navigation Menu:**
  - Create Machine Label
  - Create Purchase Form
  - Warehouse Search / Tracker
  - Customer Rolodex & Leads
- **Content:**
  - **Stats:** Total Laptops in Warehouse, Recent Orders, Active Leads.
  - **Quick Search:** Find a physical laptop by scanning its ID/Barcode.

### Screen 2: New Machine Entry / Create Label (`newEntry.php`)
Allows users to enter the specs of physical laptops, assign a warehouse location, generate an `.odt` label to stick on it, and log it in the `labels` database.

- **Input Section:**
  - `Brand`, `Model` & `Series`
  - `CPU / Gen`, `CPU Cores`, `CPU Speed`
  - `RAM` & `Storage`
  - `Battery` & `BIOS State`
  - `Description / Condition` (e.g., Untested, For Parts)
  - `Warehouse Location` (e.g., Shelf A, Bin 3)
- **Action Buttons:**
  - [ Create Label & Add to Inventory ] -> Generates `.odt` and inserts to `labels.sqlite`.

### Screen 3: New Purchase Form / Order (`newOrder.php`)
Consolidates sold laptops into a B2B Purchase Form, associates it with a customer from the Rolodex, and generates a printable `.ots` spreadsheet.

- **Order Details:**
  - Select `Customer` from Rolodex, `Date`, `Order #`
- **Line Items:** 
  - Allows the user to select laptops previously created in the `labels` database.
  - Grouping function: Multiple identical laptops can be grouped into one row with a specific `QTY`, `Price`, and `Total`.
- **Fields on the generated `.ots` Purchase Form:**
  - Type (Laptop, Gaming Console, etc.)
  - Brand, Model, Series, CPU / Gen
  - Description
  - Price, QTY, Total
  - Summary Line
- **Action Buttons:**
  - [ Generate .OTS Purchase Form ] -> Generates file, saves to `orders.sqlite`, updates the selected labels to "Sold".

### Screen 4: Warehouse Tracker / Search
View, filter, and track laptops.
- **Filters:** By Location (In Warehouse vs. Sold), Brand, Model, Condition.
- **Actions:** Update a laptop's location or link it manually to a Purchase Order.

### Screen 5: Customer Rolodex & Leads (`rolodex.php`)
Manage B2B customers and leads.
- **List View:** Table of leads and established customers.
- **Customer Profile:** View their contact details and a history of their Purchase Forms.
- **Actions:** Add New Customer, Edit Details, Change Lead Status.

---

## 3. Database Architecture (Three Separate Databases)

As requested, the data is distributed across three separate SQLite files. This creates a sandboxed ecosystem that handles leads, hardware items, and exact purchase structures separately.

### Database 1: `labels.sqlite`
Tracks **individual physical items** and their location.
- `id` (Primary Key, unique identifier that can be printed as a barcode/number)
- `type` (Default: Laptop)
- `brand`, `model`, `series`
- `cpu_gen`, `cpu_details`
- `ram`, `storage`, `battery`, `bios_state`
- `description` (Untested, For Parts)
- `status` (In Warehouse, Sold, Pending)
- `warehouse_location` (e.g., Bin 4)
- `order_id` (Linked to `orders.sqlite` when sold, otherwise NULL)
- `created_at`

### Database 2: `orders.sqlite`
Tracks the **high-level B2B Purchase Forms**.
- `order_number` (Primary Key)
- `customer_id` (Linked to `rolodex.sqlite`)
- `order_date`
- `total_price`
- `total_qty`
- `file_path` (Path to the generated `.ots` file)

### Database 3: `rolodex.sqlite`
The **Customer Profiles and Leads DB**.
- `customer_id` (Primary Key)
- `company_name` (e.g., NRU Metals)
- `contact_person` (e.g., King, Vincent)
- `email`
- `phone`
- `lead_status` (New Lead, Active Customer, Inactive)
- `notes`
- `created_at`

---

## 4. Workflows & File Generation

1. **`.odt` Label Generation:** Uses PowerShell template injection to inject the XML into the `.odt` label template based on the single-item details.
2. **`.ots` Purchase Form Generation:** PHP will format the order data, generate a new `content.xml`, and use PowerShell to update the `content.xml` inside a copy of `Purchase Form.ots`. The resulting file will accurately calculate and display the QTY, Price, Totals, and Summary strings for each row exactly like the "IQA Metal B2B Purchase Form".
