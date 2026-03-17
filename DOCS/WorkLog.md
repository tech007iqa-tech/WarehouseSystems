# Work Log - IQA Metal Inventory & Label System

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
