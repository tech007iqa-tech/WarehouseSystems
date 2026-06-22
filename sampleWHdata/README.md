# Daily Intake Form Audit & Normalization Terminal

This terminal is a self-contained, high-fidelity daily intake auditing interface. It processes user-uploaded device images, extracts details using an intelligent OCR/Inference simulation parser (with dictionary support), standardizes values, and appends them to your structured data.

## System Schema

The pipeline maps the extracted data into the primary `intakeform.csv` schema:

| Column | Type | Format / Normalization Rule |
| :--- | :--- | :--- |
| **Date** | Date | `YYYY-MM-DD` (defaults to current local date) |
| **QTY** | Integer | Standard count (defaults to `1`) |
| **Item** | String | Cleaned brand + model name. Any detected serial number is appended in parentheses: `Brand Model (Serial: XYZ123)` |
| **Serial** | String | Kept empty in the column per structure design, as serials are merged directly into the `Item` column. |
| **Location** | String | Standard uppercase location bin code (e.g., `C-1`, `A-3`) |
| **Notes** | String | Free-text technical details, condition notes, and specifications. |

---

## File Structure

- **`audit.html`**: A premium glassmorphic dark-mode web user interface. It features drag-and-drop file support, dynamic image pan/zoom/rotate controls, live form field binding with color-coded confidence indicators, and an interactive dictionary editor.
- **`audit.js`**: Frontend script managing user actions, canvas/DOM manipulation, rendering uploaded file thumbnails, and sending requests to the backend parser.
- **`process.php`**: Backend router implementing:
  - `action=extract`: Simulated OCR extraction that parses file labels and resolves abbreviations using the dictionary.
  - `action=save`: Appends the verified and normalized row to `intakeform.csv`.
  - `action=save_dictionary`: Persists user-defined variation terms.
- **`dictionary.json`**: Key-value store of common abbreviation variations (e.g. `srl`, `s/n`, `serl`) mapping directly to the Serial parser.

---

## Setup & Testing

1. Start Apache in your **XAMPP Control Panel**.
2. Open your web browser and navigate to:
   ```
   http://localhost/WarehouseSystems-main/sampleWHdata/audit.html
   ```
3. Drag and drop any laptop label images or document uploads into the intake zone.
4. Verify the OCR output, modify fields as needed, and hit **Approve & Commit Row** to append the row directly into `intakeform.csv`.
