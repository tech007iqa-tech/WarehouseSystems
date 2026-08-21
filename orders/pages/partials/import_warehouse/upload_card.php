<?php
/**
 * Warehouse Import Upload Card & Guidelines Partial
 * Initial upload form with drag-and-drop CSV file selector and data migration criteria guide.
 */
?>
<div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px;">
    <!-- UPLOAD ZONE -->
    <div style="background: white; padding: 40px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
        <form action="index.php?view=import_warehouse" method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 30px;">
                <label for="csv-input" style="display: block; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 15px;">1. Select CSV Manifest</label>
                <div id="drop-zone" style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 60px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;">
                    <input type="file" name="inventory_csv" id="csv-input" accept=".csv" required style="display: none;">
                    <div style="font-size: 4rem; margin-bottom: 15px;">📂</div>
                    <div style="font-weight: 800; font-size: 1.2rem; color: #1e293b; margin-bottom: 8px;">Click to Upload CSV</div>
                    <p id="file-name" style="color: #64748b; font-size: 0.95rem;">File must contain columns: Date, QTY, Item, Serial, location, notes</p>
                </div>
            </div>

            <button type="submit" class="btn-main" style="width: 100%; height: 60px; font-size: 1.1rem; border-radius: 16px;">
                🔍 Validate CSV & Preview Import
            </button>
        </form>
    </div>

    <!-- GUIDE -->
    <div style="background: #f8fafc; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0;">
        <h3 style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.4rem;">📘</span> CSV Data Migration Criteria
        </h3>
        <p style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px;">
            Upload inventory documents containing details about devices. The system will parse the properties out of the fields automatically.
        </p>

        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
            <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <code style="font-weight: 800; color: var(--accent-dark);">Date | QTY</code>
                <span style="font-size: 0.85rem; color: #94a3b8;">Entry date & total quantities</span>
            </div>
            <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <code style="font-weight: 800; color: var(--accent-dark);">Item</code>
                <span style="font-size: 0.85rem; color: #94a3b8;">Text describing device specifications</span>
            </div>
            <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <code style="font-weight: 800; color: var(--accent-dark);">Serial | Location</code>
                <span style="font-size: 0.85rem; color: #94a3b8;">Shelf code (A-O or custom) & serial number</span>
            </div>
        </div>

        <div style="padding: 20px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px;">
            <h4 style="font-weight: 900; color: #92400e; margin-bottom: 5px; font-size: 0.95rem;">💡 Auto-Creating Locations</h4>
            <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                If a location listed in the CSV (e.g. <strong>N4</strong>) doesn't exist, the system will automatically create it and map it to its corresponding working zone (e.g. <strong>Zone N</strong>).
            </p>
        </div>
    </div>
</div>
