# Process: Batch Photography Workflow

## Objective
Reduce the time spent on listing photos by creating a centralized "Photo Bucket" that allows for rapid reuse of hardware photography across marketing campaigns and manifests.

## Standard Shots
While the Photo Bucket supports flexible uploads, the following "Hero Shots" are recommended for every hardware model:
1. **The Scale Shot** (Category: `Bulk Stock`): A photo of the full pallet or a stack of units (proves volume).
2. **The Detail Shot** (Category: `Laptop`/`Workstation`): A close-up of a single, cleaned unit (proves quality).
3. **The Proof Shot** (Category: `Other`): A clear photo of the BIOS or System Info screen (proves specs).

## Storage & Management
- **Centralized Bucket**: All photos are stored in `marketing/assets/photo_bucket/`.
- **Database Tracking**: metadata is stored in the `photos` table in `marketing.db`.
- **Model Linking**: Photos can be linked to a hardware model during upload to enable automatic integration with the **Ad Generator** and **Model Templates**.

## System Integration
- **Ad Generator**: Automatically pulls the latest 3 photos for the selected model.
- **Model Templates**: Displays a visual indicator of asset availability (📦 Volume, ✨ Quality, 🖼️ Metadata).
- **One-Click Access**: Use the "Copy Path" button in the Photo Bucket to get the web-ready URL for external listings.

## Agent Instructions
- Maintain the "Schema Guard" pattern for the `photos` table.
- Ensure all uploaded images are stored with unique filenames to prevent collisions.
- Optimization: In future versions, consider adding automatic thumbnail generation.
