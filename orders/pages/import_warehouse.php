<?php
/**
 * Bulk Warehouse Import
 * Handles CSV uploads for the main Warehouse Inventory system.
 */
include 'core/warehouse_db.php';
include 'core/auth.php';

$current_user = $_SESSION['username'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['inventory_csv'])) {
    $file = $_FILES['inventory_csv'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle !== false) {
            $header = fgetcsv($handle);
            if ($header) {
                // Map headers (case-insensitive)
                $mapping = [];
                foreach ($header as $index => $col) {
                    $mapping[trim(strtolower($col))] = $index;
                }
                
                $count = 0;
                $conn_wh->beginTransaction();
                try {
                    while (($data = fgetcsv($handle)) !== false) {
                        $brand = $data[$mapping['brand']] ?? 'Unknown';
                        $model = $data[$mapping['model']] ?? 'Unknown';
                        $qty   = (int)($data[$mapping['qty']] ?? $data[$mapping['quantity']] ?? 0);
                        $loc   = $data[$mapping['location']] ?? $data[$mapping['location_code']] ?? 'ZONE-X';
                        $sector = $data[$mapping['type']] ?? $data[$mapping['sector']] ?? 'Laptops';
                        
                        // Intelligent Specs Construction for Laptops
                        $specs = [];
                        
                        // Handle "CPU / Gen" split or mapping
                        $cpu_gen = $data[$mapping['cpu / gen']] ?? $data[$mapping['cpu/gen']] ?? '';
                        if ($cpu_gen) {
                            $parts = explode('/', $cpu_gen);
                            $specs['cpu'] = trim($parts[0]);
                            if (isset($parts[1])) $specs['gen'] = trim($parts[1]);
                        }

                        // Handle Description mapping to notes/condition
                        $desc = $data[$mapping['description']] ?? '';
                        if ($desc) $specs['notes'] = $desc;

                        // Map standard fields
                        $standard_maps = [
                            'series' => ['series'],
                            'ram' => ['ram'],
                            'storage' => ['storage'],
                            'gpu' => ['gpu'],
                            'price' => ['price', 'unit price'],
                            'condition' => ['condition', 'state']
                        ];

                        foreach ($standard_maps as $key => $aliases) {
                            foreach ($aliases as $alias) {
                                if (isset($mapping[$alias])) {
                                    $specs[$key] = $data[$mapping[$alias]];
                                    break;
                                }
                            }
                        }
                        
                        $specs_json = json_encode($specs);
                        
                        $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, last_updated_by) 
                                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $current_user]);
                        $count++;
                    }
                    $conn_wh->commit();
                    $message = "Successfully imported $count inventory items into the warehouse.";
                } catch (Exception $e) {
                    $conn_wh->rollBack();
                    $error = "Import failed: " . $e->getMessage();
                }
            }
            fclose($handle);
        }
    } else {
        $error = "File upload error code: " . $file['error'];
    }
}
?>

<div class="orders-container" style="animation: fadeInDown 0.4s ease-out;">
    <header class="orders-header" style="margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-main); margin-bottom: 5px;">Bulk Warehouse Import</h1>
            <p style="color: var(--text-secondary); font-size: 1rem;">Upload a CSV manifest to populate your warehouse inventory sectors in bulk.</p>
        </div>
        <a href="index.php?view=warehouse" class="btn-main" style="background: #f1f5f9; color: #475569; box-shadow: none; border: 1px solid #e2e8f0;">
            ← Back to Warehouse
        </a>
    </header>

    <?php if ($message): ?>
        <div style="background: #ecfdf5; color: #065f46; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #d1fae5; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">✅</span> <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fef2f2; color: #991b1b; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">⚠️</span> <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px;">
        <!-- UPLOAD ZONE -->
        <div style="background: white; padding: 40px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
            <form action="index.php?view=import_warehouse" method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 15px;">1. Select Inventory Manifest</label>
                    <div id="drop-zone" style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 60px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;">
                        <input type="file" name="inventory_csv" id="csv-input" accept=".csv" required style="display: none;">
                        <div style="font-size: 4rem; margin-bottom: 15px;">📂</div>
                        <div style="font-weight: 800; font-size: 1.2rem; color: #1e293b; margin-bottom: 8px;">Click to Upload CSV</div>
                        <p id="file-name" style="color: #64748b; font-size: 0.95rem;">Supports standard inventory exports</p>
                    </div>
                </div>

                <button type="submit" class="btn-main" style="width: 100%; height: 60px; font-size: 1.1rem; border-radius: 16px;">
                    🚀 Start Processing Import
                </button>
            </form>
        </div>

        <!-- GUIDE -->
        <div style="background: #f8fafc; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0;">
            <h3 style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.4rem;">📘</span> CSV Data Mapping
            </h3>
            <p style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px;">
                The system automatically maps your CSV headers. Ensure your file contains at least these columns:
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <code style="font-weight: 800; color: var(--accent-dark);">Type</code>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Laptops, Gaming, etc.</span>
                </div>
                <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <code style="font-weight: 800; color: var(--accent-dark);">Brand / Model / Series</code>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Standard Identity</span>
                </div>
                <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <code style="font-weight: 800; color: var(--accent-dark);">CPU / Gen</code>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Auto-Splits Specs</span>
                </div>
                <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <code style="font-weight: 800; color: var(--accent-dark);">Description</code>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Maps to Internal Notes</span>
                </div>
                <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <code style="font-weight: 800; color: var(--accent-dark);">Price / QTY</code>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Inventory Values</span>
                </div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px;">
                <h4 style="font-weight: 900; color: #92400e; margin-bottom: 5px; font-size: 0.95rem;">💡 Pro Tip</h4>
                <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                    Add columns like <strong>cpu</strong>, <strong>ram</strong>, or <strong>gpu</strong> to automatically populate technical specs for each item.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    const dropZone = document.getElementById('drop-zone');
    const csvInput = document.getElementById('csv-input');
    const fileName = document.getElementById('file-name');

    dropZone.onclick = () => csvInput.click();

    csvInput.onchange = (e) => {
        if (e.target.files.length > 0) {
            fileName.innerText = e.target.files[0].name;
            fileName.style.color = 'var(--accent-color)';
            fileName.style.fontWeight = '900';
            dropZone.style.borderColor = 'var(--accent-color)';
            dropZone.style.background = '#f0fdf4';
        }
    };
</script>
