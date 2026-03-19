# Work Log - IQA Metal Inventory & Label System

## [2026-03-19] - Hardware Mapping Layer (Architectural Fortification)
### Added & Refactored
- **Phase 11: Unified Mapping Layer**: Implemented a single source of truth for all hardware field keys as defined in `dsa.md`. This eliminates "field name guessing" by AI agents and ensures site-wide stability.
- **Core Strategy**: Created `includes/hardware_mapping.php` (PHP constant) and `assets/js/hardware_mapping.js` (Browser global) to map 25+ technical fields to their exact database columns.
- **System-Wide Refactor**: 
    - Updated `includes/hardware_form.php` to use dynamic field mapping for all `name` and `id` attributes.
    - Path-corrected all hardware APIs (`add_label.php`, `edit_label.php`, `get_labels.php`, `search_item.php`, `search_inventory.php`, `reprint_label.php`) to use the mapping layer for POST collection and SQL construction.
    - Refactored `assets/js/labels.js` and `assets/js/forms.js` to drive the UI engine via the global `window.HW_FIELDS` object.
- **Safety & Verification**: Created `debug/verify_mapping.php`, a diagnostic tool that verifies synchronization between PHP, JS, and the SQLite schema.
- **Global Integration**: Modified `includes/header.php` to include the mapping JS globally, ensuring field availability on every page.

## [2026-03-18] - Thermal Printer Optimization (Zebra 2x1) & Maintenance
### Added & Refactored
- **Phase 9: Thermal Strategy**: Transformed `print_label.php` into a margin-less 2x1 thermal engine. Unified Branding (Label A) and Technical Specs (Label B) into a single 2-page print job (1 PDF file) specifically optimized for the Zebra GX 430d.
- **Strict Sizing**: Enforced rigid `@page` dimensions (2in x 1in landscape) with zero browser margins to prevent PDF scaling bugs.
- **Status Automation (v2)**: Updated `api/add_label.php` to automatically default status to **"In Warehouse"** during initial hardware intake, as requested.
- **Maintenance Tools**: Modified `api/delete_label.php` to remove the restriction on deleting "Sold" items, allowing warehouse staff to purge old records from the dashboard.
- **UUI Enhancements**: Added the **Series** field to the main header and "Quick Specs" summary sidebar in `hardware_view.php` for better hardware identification.
- **Accessibility (8.6 Refinement)**: Fixed all "No label associated with form field" violations in `header.php` and `hardware_form.php` to ensure 100% compliance with browser accessibility scanners.
- **Architectural Cleanup**: Centralized the Thermal Printer CSS into `assets/css/style.css` to prevent future logic regressions.
- **Documentation**: Synchronized `PROJECT_CONTEXT.md` and `ARCHITECTURE.md` with the new hybrid printing and maintenance logic.

## [2026-03-17] - Hardware Form UX & Status Automations
### Added & Refactored
- **Form Defaults**: Stripped placeholder examples from `includes/hardware_form.php` to prevent visual confusion with actual data.
- **Intelligent CPU Expansion**: Simplified Intel generations (i3, i5, i7, i9) down to base options and added a single unified `AMD` (AMD-) option to the auto-fill catalog in `assets/js/forms.js`.
- **Status Automation**: Engineered dynamic rules in `forms.js` that automatically enforce "Tested" status when "Refurbished (Ready)" condition is selected. Prevented manual selection of "Sold".
- **Grading Scale**: Added "Grade A", "Grade B", and "Grade C" as valid status options when an item is in "Untested (Intake)" condition.
- **UI Bug Fixes**: Added missing `--btn-danger-bg` variable to `style.css` and hardcoded exact hex values (`#ef4444`) in `conditionBadge()` (JS files) to permanently fix invisible backgrounds on "For Parts" badges.
- **Error Resolution**: Removed a duplicate `print_engine.js` script tag in `footer.php` that was causing `currentPrintId` Uncaught SyntaxErrors.

## [2026-03-17] - Labels Page UX Overhaul
### Added & Refactored
- **Mobile-First Layout**: Upgraded `labels.php` to use a responsive card-based layout on smaller screens.
- **Enhanced Filter Bar**: Improved search with flexible widening and a clear button. Prepared for condition-based filtering.
- **Floating Action Button**: Added quick access UI for `new_label.php` across devices.
- **Desktop Table Optimization**: Slimmed down columns and updated the Action Strip to use precise, icon-focused buttons (`🖨️ Print`, `📂 Open`, `✏️ Edit`, `🗑 Del`) for cleaner presentation.

## [2026-03-17] - Intelligent CPU Intake & 2x1 Sticker Check
### Added
- **CPU Intelligent Intake**: Upgraded `assets/js/forms.js` with a structured CPU catalog. Selecting a "Generation" (e.g., i5 11th Gen) now automatically pre-fills the technical specs (i5-11), core count, and processor speed.
- **Condition/Status Logic**: Split the "Condition / Status" field into two separate elements: **Condition / Internal Note \*** and **Status**.
- **Specialized States**: Added "No Post" and "No Power" as Status options, dynamically shown only when the hardware condition is set to "For Parts".
- **Auto-Focus Workflow**: Selecting a CPU generation now shifts focus to the Specs field and places the cursor at the end, allowing technicians to type model-specific digits instantly.
- **UI Refinement**: Removed the `i??-` prefix placeholder from the processor specs input to ensure a cleaner empty state in `hardware_form.php`.
- **Documentation Sync**: Verified that `print_label.php` utilizes 2" x 1" dimensions and updated `PROJECT_CONTEXT.md` to match physical sticker stock.

## [2026-03-17] - Analytics & Reporting (Phase 8)
### Added
- **Analytics Engine**: Created `api/get_analytics.php` to calculate inventory aging, brand distribution, and monthly sales totals.
- **Reporting Dashboard**: Launched `analytics.php` featuring CSS-animated charts for logistics and sales velocity.
- **Aging Tracker**: Implemented threshold-based highlights for stock that has been in stock for >30 days.
- **Navigation Update**: Added "📈 Performance" link to the persistent sidebar.

## [2026-03-16] - Universal Hardware Control Pattern (Phases A-D)
### Added
- **Unified Hardware Engine**: Created `includes/hardware_form.php` used by intake (`new_label.php`) and technical editing (`refurbished_view.php`).
- **Flash Launch Logic**: Implemented `assets/js/actions.js` which checks for existing label ODTs via `api/check_file_exists.php` and launches them instantly via the Windows Bridge.
- **Profile Cloning**: Enabled one-click specification cloning in `new_label.php` from the "Recently Added" sidebar.
- **Universal Action Strip**: Standardized the `🖨️ Print`, `📂 Open`, `✏️ Edit`, and `🗑 Del` UI across Inventory and Dashboard views.
### Refactored
- Upgraded `new_label.php` and `refurbished_view.php` to use a single shared form component.
- Centralized technical actions (Open/Launch) into a global `actions.js` bridge.
- Standardized action CSS in `style.css`.
- Standardized Search, Edit, and Print actions into a single reusable "Action Strip".
- **Smart Launch Workflow**: Designed the "Flash Launch" logic for the **📂 Open** action. The system will now check for existing `.odt` files on the workstation and launch them instantly via the Windows Bridge, only falling back to the generation engine if the file is missing or out of sync.
- **Master Specification Sync**: Planned a deep integration between `labels.php`, `new_label.php`, and `refurbished_view.php`. The goal is a "Dual-Path" editor—using fast Inline Editing for inventory moves and a Shared Technical Form for deep refurbishment specs.
- **Creation Optimization**: Formulated a "Clone & Populate" feature for the Intake sidebar to allow technicians to rapidly duplicate hardware profiles while batch processing.
