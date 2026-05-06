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

## Performance & Optimization
- **Triple-Tier Processing**: The system automatically generates three versions of every photo:
  1. **Raw Original**: The uncompressed backup of your upload.
  2. **Optimized Full (WebP)**: A high-performance full-screen version (Max 1920px).
  3. **Thumbnail (WebP)**: A lightweight 400px preview for the gallery grid.
- **WebP Compression**: Processed images use the WebP format for superior loading speed.

## Troubleshooting: "Performance Warning"
If you see a warning about the **GD Library**, thumbnail generation is disabled. 
1. **The Symptom**: "⚙️ Processing..." labels stay blurred or original high-res photos are used everywhere.
2. **The Fix**:
   - Open your `php.ini` file (via XAMPP Config -> PHP).
   - Search for `;extension=gd`.
   - Remove the leading semicolon: `extension=gd`.
   - **Important**: Stop and Start Apache to apply the change.

## Agent Instructions
- Maintain the "Schema Guard" pattern for the `photos` table.
- Ensure all uploaded images are stored with unique filenames.
- Always use the `PhotoProcessor` class for image handling.
