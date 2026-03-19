# 🏗️ Implementation Plan: Hardware Mapping Layer

## 🎯 Goal
Create a single point of definition for all hardware field keys to eliminate "variable guessing" by AI agents and ensure site-wide stability during database or UI changes.

## 🧱 1. Two-Factor Source of Truth

### A. Backend: `includes/hardware_mapping.php`
A PHP file defining a global constant array.
*   **Key:** Semantic Logic Name (e.g., `CPU_SPECS`)
*   **Value:** Database Column Name (e.g., `cpu_specs`)

### B. Frontend: `assets/js/hardware_mapping.js`
A global JavaScript object containing the same keys.
*   **Integrated into UI:** All forms will set their `name` and `id` attributes based on these mappings.

---

## 🚀 2. Site-Wide Migration Steps

### Step 1: Initialize Mappings
*   Create both mapping files with all 25+ identified fields.
*   Ensure they exactly match our recently fixed database schema.

### Step 2: Component Refactor ([includes/hardware_form.php](cci:7://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/includes/hardware_form.php:0:0-0:0))
*   Update the shared form fields to use the mapping keys for `name="..."` and `id="..."`.
*   **Before:** `<input name="cpu_gen">`
*   **After:** `<input name="<?= HW_FIELDS['CPU_GEN'] ?>">`

### Step 3: API Level Refactor ([api/add_label.php](cci:7://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/api/add_label.php:0:0-0:0), [api/edit_label.php](cci:7://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/api/edit_label.php:0:0-0:0))
*   Rewrite POST collection and SQL queries to use the mapping fields.
*   **Before:** `SET cpu_specs = :specs`
*   **After:** `SET " . HW_FIELDS['CPU_SPECS'] . " = :specs` (This ensures SQL never uses hardcoded strings).

### Step 4: UI Engine Refactor ([assets/js/labels.js](cci:7://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/assets/js/labels.js:0:0-0:0))
*   The inventory table's [buildRow](cci:1://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/assets/js/labels.js:87:0-159:1) and [openEditRow](cci:1://file:///c:/xampp/htdocs/GithubRepos/WarehouseSystems-1/assets/js/labels.js:211:0-268:1) functions will now use the global JS mapping object to read data from the JSON response and populate input fields.

---

## ⚠️ 3. Safety Standards for Future Agents (Verification)

1.  **Naming Lockdown:** All future AI prompts should be instructed to NEVER hardcode a key like `'cpu_details'`. They MUST check the `hardware_mapping` files first.
2.  **Schema Check:** Include a small PHP script `debug/verify_mapping.php` that compares the mapping file against the actual SQLite column names and alerts us if there is a mismatch.

---

## 🛠️ Proposed Field List
We will map these categories:
*   **Core Logic:** `BRAND`, `MODEL`, `SERIES`, `SERIAL_NUMBER`
*   **Processing:** `CPU_GEN`, `CPU_SPECS`, `CPU_CORES`, `CPU_SPEED`, `CPU_DETAILS`
*   **Internals:** `RAM`, `STORAGE`, `GPU`, `SCREEN_RES`
*   **Technical Details:** `BATTERY`, `BATTERY_SPECS`, `WEBCAM`, `BACKLIT_KB`, `OS_VERSION`, `COSMETIC_GRADE`, `WORK_NOTES`
*   **Status/Location:** `BIOS_STATE`, `DESCRIPTION`, `STATUS`, `LOCATION`
