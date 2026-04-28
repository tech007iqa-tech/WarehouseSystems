# Feature: Inventory-to-Ad Script (Manifest Generator)

## Objective
Automatically generate marketing copy from current warehouse stock levels to streamline outreach to B2B contacts and social media postings.

## Logic Parameters
The "Manifest Generator" should pull items from the `labels.sqlite` database based on these criteria:
- **Quantity**: Items with count > 10.
- **Location**: Items marked as "Inbound" or "Processed".
- **Condition**: Items that are "Ready for Sale".

## Output Formats
- **LinkedIn Blast**: A punchy, professional list with emojis.
- **B2B Email**: A clean table or list focused on volume and price.
- **Marketplace Quick-Post**: Short, spec-heavy text.

## Agent Instructions
- Create a PHP function `generate_manifest_text($format)` in `modules/manifest/functions.php`.
- The function must join the local `marketing` templates with the external `labels` database.
- Use a "Tiered" approach: Bulk lots get more descriptive text, small batches get concise bullet points.
