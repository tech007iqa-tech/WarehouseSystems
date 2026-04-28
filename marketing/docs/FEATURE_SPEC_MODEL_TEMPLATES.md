# Feature: Model Template Blocks

## Objective
Create a library of "Marketplace-ready" technical descriptions for common hardware models to eliminate redundant typing and ensure consistency in marketing materials.

## Database Structure
A new table `model_templates` will be added to the marketing database:
- `id`: Primary Key
- `model_name`: e.g., "Dell Wyse 5070"
- `category`: e.g., "Thin Client", "Laptop", "Desktop"
- `base_specs`: JSON or Text block of standard specs.
- `marketing_copy`: The "Hero Description" for ads.
- `last_updated`: Timestamp

## Logic Flow
1. **Selection**: User selects a model from a dropdown.
2. **Fetch**: System retrieves the `marketing_copy` and `base_specs`.
3. **Merge**: User adds specific details (Price, Quantity, Condition).
4. **Copy/Paste**: Final text is generated for the user to copy.

## Agent Instructions
- Build a simple CRUD interface in `modules/templates/` to manage these blocks.
- Ensure the descriptions are optimized for readability (bullet points, clear headers).
- Use HSL-based styling for the "Preview" area so it looks premium.
