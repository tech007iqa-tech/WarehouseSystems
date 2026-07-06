# 🗺️ Global System Sitemap 7/6/2026 4:21 PM

This map outlines the tri-module structure of the IQA Warehouse Systems.

## 📍 Root `/WarehouseSystems-main/`
- `index.php`: Premium Portal / Landing Page.
- `DOCS/`: System-wide AI reviewer documentation.
    - `AI_AGENT_INSTRUCTIONS.md`: Core behavioral guidelines.
    - `AI_TECHNICAL_DEEP_DIVE.md`: Architectural shortcuts & token-saving map.
    - `GLOBAL_SITEMAP.md`: This document.
    - `CODE_REVIEW_CHECKLIST.md`: Quality control standards.
- `labels/`: [Inventory & Label Module]
- `orders/`: [Order & CRM Module]
- `sampleWHdata/`: [Offline Intake Terminal Module]

---

## 🏷️ Module: Labels (`/labels/`)
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

## 📊 Module: Orders (`/orders/`)
*Focus: B2B relationship management and batch fulfillment.*

- `index.php`: Application Router (all pages below are routed through here via `?view=` parameter).
- `pages/`:
    - `warehouse.php`: Stock & location management with nested working zones.
    - `inbound.php`: Embeds `sampleWHdata/audit.html` via seamless iframe for AI-powered intake (with parent-window navigation escaping).
    - `customer_registry.php`: B2B account list.
    - `leads.php`: CRM interaction hub with SSE real-time sync.
    - `new_order.php`: Batch builder.
    - `checkout.php`: B2B Manifest & Export.
    - `trends.php`: Historical BI analytics with CPU pricing modals.
    - `calendar.php`: Scheduler with lead conversion badging.
    - `settings.php`: Administrative control panel (schema repair, backup, audit logs).
    - `import_warehouse.php`: Warehouse batch import from intake CSV.
- `core/`:
    - `database.php`: Cross-DB PDO Singleton with self-healing Schema Guard.
    - `auth.php`: Role-based security (Admin, Operator, Front Desk).
    - `Schema.php`: All database table blueprints and migration rules.
    - `UI.php`: Server-side UI helpers (notifications, theme init).
- `api/`:
    - `get_cpu_pricing_details.php`: Price metrics and recent transactions for CPU families.
    - `get_order_details.php`: Item batch list and totals for a given order ID.
    - `sync_stream.php`: SSE stream for real-time DB change notification.
- `assets/`:
    - `styles/`: Per-view CSS files (`style.css`, `warehouse.css`, `inbound.css`, `calendar.css`, etc.)
    - `js/`: Per-view JS files (`warehouse.js`, `new_order.js`, `orders.js`, etc.)
- `db/`: SQLite databases (`customers.db`, `orders.db`, `users.db`, `warehouse.db`, `calendar.db`).

---

## 📥 Module: Inbound Intake Terminal (`/sampleWHdata/`)
*Focus: Offline-capable, AI-powered handwritten intake sheet digitization.*

- `audit.html`: Main intake terminal UI — drag-and-drop image upload, AI OCR mode, Manual Grid Overlay mode, image viewer controls, and audit table editor.
- `history.html`: Committed intake log viewer — hierarchical shelf/bin location breadcrumb filter, sortable table, search, CSV export, and link to warehouse import.
- `settings.html`: Configuration panel — Gemini API key, AI prompt settings (persona, field schema, abbreviation dictionary, normalization rules), compiled prompt preview, and **Database Management** (clear committed records).
- `process.php`: Backend API router — handles `extract`, `save`, `get_committed`, `clear_committed`, `get_config`, `save_config` actions.
- `config.json`: Persisted Gemini API key and all prompt settings.
- `src/`:
    - `OcrEngine.php`: Builds and sends Gemini Vision multipart API request.
    - `Normalizer.php`: Applies abbreviation expansion and formatting rules to each row.
    - `DbHandler.php`: PDO handler for `committed_intakes` table in `sample_data/intake.sqlite`.
    - `Config.php`: Reads/writes `config.json`.
- `assets/css/`:
    - `audit.css`: Full dark glassmorphic theme for the terminal.
    - `settings.css`: Settings page styles.
- `assets/js/`:
    - `api.js`: Fetch API wrapper for all process.php endpoints.
    - `audit.js`: File upload, OCR orchestration, undo, commit, and reset logic.
    - `grid.js`: Table row rendering, Manual Grid Overlay, CSV load/download.
    - `viewer.js`: Image pan, zoom, and rotation controls.
    - `dragdrop.js`: Drag-and-drop event handling.
- `sample_data/intake.sqlite`: Auto-created SQLite database for committed intake records.
