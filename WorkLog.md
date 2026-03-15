# Work Log - ODT Label Generator

## Project Updates Milestones
  - Create labels for print


## Summary of Changes
We successfully implemented a system to generate `.odt` laptop labels from a web form.

## Future Updates
  - Add to database
  - Search database
  - Display labels
  - Edit
  - Delete

### 1. Backend Implementation (`test/add.php`)
- **Purpose**: Handles form submission and generates the label file.
- **Methodology**: 
  - Instead of using heavy libraries like `phpword` (which require Composer), we used a "Template Injection" method.
  - The script copies a master template (`assets/Data Sample Files/Dell Latitude 3520.odt`).
  - It generates a new `content.xml` file with your specific data (Brand, Model, CPU, etc.).
  - It uses a custom **PowerShell script** to inject this XML back into the `.odt` file (treating it as a zip archive).
  - This ensures compatibility on your Windows machine without needing extra PHP extensions like `ZipArchive`.

### 2. PowerShell Helper (`test/update_odt.ps1`)
- **Purpose**: Safely updates the ODT file.
- **Details**: 
  - Called by `test/add.php`.
  - Uses `Compress-Archive -Update` to overwrite the internal XML of the ODT file.
  - This solved the issue where PHP's native zip functions were missing or failing.

### 3. Frontend (`test/newEntry.php`)
- **Purpose**: The user interface for data entry.
- **Changes**:
  - Linked to `assets/css/style.css` for proper styling.
  - Confirmed all fields (Battery, RAM, Storage, CPU Cores, OS, Bios State) are correctly named and sent to the backend.

### 4. Result
- You can now fill out the form at `test/newEntry.php`.
- Clicking "Add Laptop" downloads a formatted `.odt` file ready for printing/editing.
- The label includes complex data like "Battery ✅" and full CPU specs.

