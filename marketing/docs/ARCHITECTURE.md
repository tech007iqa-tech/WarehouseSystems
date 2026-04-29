# Marketing App Architecture

## Overview
This application is designed to be modular, lightweight, and easy to maintain. It follows a clean separation of concerns to ensure that logic, presentation, and data management are decoupled.

## Directory Structure
- `/assets`: Static files (CSS, JS, Images).
- `/includes`: Reusable UI components (header, footer, nav).
- `/modules`: Independent functional blocks (Campaigns, Leads, Analytics).
- `/data`: Database schemas, SQLite files, or JSON storage.
- `/docs`: Project documentation and agent instructions.

## Key Principles
1. **Modularity**: Each feature should reside in its own subdirectory within `/modules`.
2. **Simplicity**: Code should be readable and avoid over-engineering.
3. **Consistency**: Use standardized naming conventions and shared assets.
4. **Agent-Friendly**: Every module should contain a `README.md` explaining its purpose and integration steps.
5. **Self-Healing Schema**: Use a `schema_guard.php` or similar logic to automatically initialize and update the SQLite database.
6. **Audit Logging**: All data mutations (Insert/Update/Delete) must be logged to a central audit system.

## Modules Overview
- **Dashboard**: Strategic command center with "Smart Opportunities" (Inventory Analysis).
- **Leads**: CRM-integrated contact management with dual-sync capabilities.
- **Model Templates**: Technical spec library with auto-generation and photo bank tracking.
- **Ad Generator**: Multi-tone manifest creation with asset verification.
- **Campaigns**: Strategic outreach management and initiative grouping.
- **Reports**: Automated funnel analytics and warehouse coverage auditing.
- **Docs**: Integrated Knowledge Base for SOPs and guidelines.

## Data Architecture
- **marketing.db**: Local state, templates, campaigns, and audit logs.
- **customers.db**: Master CRM source of truth (Bi-directional sync).
- **labels.sqlite**: Live warehouse inventory data (Read-only integration).

## Technology Stack
- **Frontend**: HTML5, Vanilla CSS, Modern JavaScript.
- **Backend**: PHP (Modular logic).
- **Database**: SQLite (Optimized with performance indexing).
