# 🗺️ Global System Sitemap

This map outlines the dual-module structure of the IQA Warehouse Systems.

## 📍 Root `/app/`
- `index.php`: Premium Portal / Landing Page.
- `DOCS/`: System-wide AI reviewer documentation.
- `labels/`: [Inventory & Label Module]
- `orders/`: [Order & CRM Module]

---

## 🏷️ Module: Labels (`/app/labels/`)
*Focus: Individual unit intake and high-fidelity thermal printing.*

- `index.php`: Dashboard (Stats & Quick Search).
- `labels.php`: Main Inventory Tracker.
- `new_label.php`: Rapid Intake Form.
- `hardware_view.php`: Technical Sheet Editor.
- `api/`:
    - `add_label.php`: Database insertion.
    - `reprint_label.php`: Flat XML ODT generation.
    - `open_windows_file.php`: Native shell launch helper.
- `db/`: SQLite databases (`labels`, `audit`, `orders`, `rolodex`).
- `templates/`: ODT master templates.
- `exports/`: Storage for generated labels.

---

## 📊 Module: Orders (`/app/orders/`)
*Focus: B2B relationship management and batch fulfillment.*

- `index.php`: Application Router (Pages below are routed here).
- `pages/`:
    - `warehouse.php`: Stock & location management.
    - `customer_registry.php`: B2B account list.
    - `leads.php`: CRM interaction hub.
    - `new_order.php`: Batch builder.
    - `checkout.php`: B2B Manifest & Export.
- `core/`:
    - `database.php`: Cross-DB PDO Singleton.
    - `auth.php`: Role-based security.
- `assets/db/`: SQLite databases (`customers`, `orders`, `users`, `warehouse`).

---

## 📣 Module: Marketing (`/app/marketing/`)
*Focus: Lead generation, campaign tracking, and outreach automation.*

- `index.php`: Module Router & Dashboard.
- `config.php`: Local environment settings.
- `modules/`:
    - `leads/`: Prospect tracking and status management.
    - `campaigns/`: Outreach coordination.
- `includes/`: Shared UI components (header, footer).
- `data/`: SQLite database and schemas.
- `docs/`: Technical roadmap and development guidelines.
