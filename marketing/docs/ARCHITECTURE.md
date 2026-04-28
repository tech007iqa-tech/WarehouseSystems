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

## Technology Stack
- **Frontend**: HTML5, Vanilla CSS, Modern JavaScript.
- **Backend**: PHP (Modular logic).
- **Database**: SQLite (for portability and simplicity).
