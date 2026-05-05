# 🏬 IQA Warehouse Inventory & Order Manager

A modern, responsive PHP/SQLite application for managing warehouse hardware inventory, customer orders, and administrative scheduling. Optimized for high-speed "app-like" performance on both iOS Safari and desktop.

---

## ✨ Key Features

-   **📅 Integrated Admin Calendar**: Professional scheduling with weekly/monthly views, smart event suggestions from Leads/Orders, and business-hour enforcement (8 AM – 5 PM).
-   **🎯 CRM & Relationship Hub**: Lead management with automated balance tracking, interaction logging, and priority follow-up logic.
-   **🏬 Warehouse Control**: Real-time stock management with operational zone status tracking (Working, Audit, Idle) and optimistic concurrency control.
-   **📦 Batch Builder**: Hardware intake system with brand-specific series filtering and zero-click fulfillment workflows.
-   **🔒 Role-Based Access (RBAC)**: Secure partitioning between Administrators (Full Access) and Warehouse Operators (Inventory Only).
-   **📄 Thermal Labeling**: Native 2×1 ODT label generation using Flat XML (no external dependencies).
-   **🚀 High Performance**: Utilizes SQLite `ATTACH DATABASE` for engine-level cross-DB joins and session-based state persistence.

---

## 🛠️ Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Backend** | PHP 8.x (Custom Route Mapping) |
| **Database** | SQLite v3 (Modular: `customers`, `orders`, `users`, `warehouse`, `calendar`) |
| **Connection** | Centralized **PDO Singleton** with engine-level Cross-DB Joining |
| **Frontend** | Modern HTML5 & Vanilla CSS (CSS Variables, Glassmorphism) |
| **Logic** | Vanilla JS (ES6+) with decoupled JSON state injection |
| **Security** | CSRF-resistant PRG patterns, RBAC Session Guard, `.htaccess` DB protection |

---

## 📂 Project Structure

```text
├── index.php               # Central Router & Entry Point
├── checkout.php            # Manifest Editor & Export Hub
├── generate_odt.php        # ODT Label Generator (Flat XML)
├── api/                    # AJAX JSON Endpoints (Status, Logs, Calendar)
├── core/                   # Shared Logic (Database Singleton, Auth, Login)
├── pages/                  # View Fragments (Leads, Warehouse, Calendar, etc.)
├── assets/
│   ├── db/                 # SQLite .db files (Protected via .htaccess)
│   ├── styles/             # Modular CSS per view
│   ├── js/                 # Modular JS per view
│   └── icon/               # App assets
└── DOCS/
    ├── DOCUMENTATION.md    # Full technical breakdown
    └── AI_CONTEXT.md       # 🤖 HIGH-PRIORITY: Agent Architectural Overview
```

---

## 🔄 Operational Workflow

1.  **Onboarding**: Register a new B2B client via the **Customer Registry**.
2.  **Scheduling**: Use the **Admin Calendar** to book visits or follow-up tasks.
3.  **Intake**: Launch a "Fresh Batch" from the customer profile to start adding hardware in the **Batch Builder**.
4.  **Warehouse**: Operators track items in the **Warehouse Portal**, managing zone statuses and stock levels.
5.  **Fulfillment**: Verify the manifest in **Checkout**, print thermal labels, and export the finalized CSV/PDF for the client.
6.  **CRM**: Use the **Leads Hub** to nurture prospects and track lifetime value (LTV) and balance history.

---

## 🚀 Quick Start

1.  **Server**: PHP 8.x + SQLite3 extension enabled.
2.  **Permissions**: Ensure `assets/db/` is writable by the web server.
3.  **Authentication**: Use the login portal; operators are auto-redirected to the Warehouse Portal.
4.  **Labels**: Ensure the system has an `.odt` handler (e.g., LibreOffice) for label printing.

---

> [!TIP]
> **Developer Note**: State is passed from PHP to JS via `<script type="application/json">` blocks. Avoid global variable pollution. See `DOCS/AI_CONTEXT.md` for implementation patterns.

