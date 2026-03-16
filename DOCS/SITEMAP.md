# Sitemap & Directory Structure

## 1. Directory Structure
```text
/LabelAPP/
│
├── /assets/                # Static frontend assets
│   ├── /css/
│   │   └── style.css       # Global dark-theme stylesheet
│   └── /js/
│       ├── forms.js        # Logic for new_label/new_customer forms
│       ├── new_order.js    # Logic for the 4-step ordering cart
│       ├── labels.js       # CRUD & Filtering for warehouse tracking
│       ├── rolodex.js      # CRUD for customer list
│       └── api.js          # Shared fetch helpers (Legacy)
│
├── /db/                    # SQLite Database Files
│   ├── labels.sqlite       # Items & Inventory
│   ├── orders.sqlite       # Purchase Orders
│   └── rolodex.sqlite      # Customers & Leads
│
├── /templates/             # Master files for PowerShell Injection
│   ├── label_template.odt  # Master hardware label
│   ├── order_template.ots  # Master purchase order sheet
│   └── /scripts/           
│       ├── generate_odt.ps1
│       └── generate_ots.ps1
│
├── /includes/              # Reusable PHP backend components
│   ├── db.php              # PDO Shared Connections
│   ├── header.php          # Sidebar Nav & HTML Head
│   ├── footer.php          
│   └── functions.php       # Formatting & Sanitization
│
├── /api/                   # API Endpoints (All return JSON)
│   ├── add_label.php       
│   ├── edit_label.php      
│   ├── delete_label.php    
│   ├── get_labels.php      # Search/Filter warehouse
│   ├── search_item.php     # Quick Locate lookup
│   ├── add_customer.php    
│   ├── edit_customer.php   
│   ├── delete_customer.php 
│   ├── get_customer.php    
│   ├── search_inventory.php # Search for cart items
│   └── orders_api.php      # Process PO & OTS generation
│
├── /exports/               # Generated .odt / .ots files
│   ├── /labels/
│   └── /orders/
│
├── index.php               # Dashboard (Stats & Search)
├── labels.php              # Warehouse Table View
├── new_label.php           # Add Item Form
├── orders.php              # PO List View
├── order_view.php          # Detailed PO Card / Item List
├── new_order.php           # Shopping Cart View
├── rolodex.php             # CRM List View
├── new_customer.php        # Add Customer Form
├── customer_view.php       # Detailed card & PO history
└── edit_customer.php       # Full-page edit form
```

## 2. UI View Hierarchy
- **Dashboard (`index.php`):** High-level metrics + **Quick Locate** barcode/ID lookup.
- **Hardware (`labels.php`):** Filterable table with **Inline Editing** and **Deletion**.
- **Orders (`orders.php`):** History of all sales with download links for OTS files.
- **Rolodex (`rolodex.php`):** CRM list with **Inline Editing** for customer data.
- **Customer Card (`customer_view.php`):** Profile overview, Shipping label, and specific PO history.
