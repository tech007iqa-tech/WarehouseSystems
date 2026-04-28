<?php
include 'core/warehouse_db.php';
include 'core/auth.php'; // Session is already started and checked

$current_user = $_SESSION['username'];
$selected_sector = $_GET['sector'] ?? 'Laptops';
$selected_loc = $_GET['loc'] ?? null;

// Handle Add/Edit/Delete Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_inventory' && isset($_POST['item_id'])) {
        $stmt = $conn_wh->prepare("DELETE FROM inventory WHERE id=?");
        $stmt->execute([$_POST['item_id']]);
        
        $sector = $_GET['sector'] ?? $_POST['sector'] ?? 'Laptops';
        $loc = $_GET['loc'] ?? $_POST['location_code'] ?? '';
        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=deleted#wh-form-title");
        exit();
    }

    if ($_POST['action'] === 'rename_zone' && isset($_POST['old_loc']) && isset($_POST['new_loc'])) {
        $old_loc = $_POST['old_loc'];
        $new_loc = trim($_POST['new_loc']);
        $new_status = $_POST['location_status'] ?? 'Idle';
        
        if (!empty($new_loc)) {
            $conn_wh->beginTransaction();
            try {
                // Update items
                $stmt = $conn_wh->prepare("UPDATE inventory SET location_code = ? WHERE location_code = ?");
                $stmt->execute([$new_loc, $old_loc]);
                
                // Update or Create location entry
                $stmt_loc = $conn_wh->prepare("INSERT OR REPLACE INTO locations (location_code, status, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                $stmt_loc->execute([$new_loc, $new_status]);
                
                // If renamed, delete old location entry if it's different
                if ($new_loc !== $old_loc) {
                    $stmt_del = $conn_wh->prepare("DELETE FROM locations WHERE location_code = ?");
                    $stmt_del->execute([$old_loc]);
                }
                
                $conn_wh->commit();
                header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=zone_updated");
                exit();
            } catch (Exception $e) {
                $conn_wh->rollBack();
                die("Failed to update zone: " . $e->getMessage());
            }
        }
    }

    if ($_POST['action'] === 'add_location_status' && isset($_POST['status_name'])) {
        $name = trim($_POST['status_name']);
        $color = $_POST['status_color'] ?? '#64748b';
        if (!empty($name)) {
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO location_statuses (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=status_added");
        exit();
    }

    if ($_POST['action'] === 'delete_zone' && isset($_POST['old_loc'])) {
        $old_loc = $_POST['old_loc'];
        $conn_wh->beginTransaction();
        try {
            // Bulk delete items
            $stmt = $conn_wh->prepare("DELETE FROM inventory WHERE location_code = ?");
            $stmt->execute([$old_loc]);
            
            // Delete location tracking
            $stmt_loc = $conn_wh->prepare("DELETE FROM locations WHERE location_code = ?");
            $stmt_loc->execute([$old_loc]);
            
            $conn_wh->commit();
            header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=zone_deleted");
            exit();
        } catch (Exception $e) {
            $conn_wh->rollBack();
            die("Delete failed: " . $e->getMessage());
        }
    }

    if ($_POST['action'] === 'add_inventory' || $_POST['action'] === 'edit_inventory') {
        $brand = $_POST['brand'];
        $model = $_POST['model'];
        $loc = $_POST['location_code'];
        $qty = (int)$_POST['quantity'];
        $sector = $_POST['sector'];
        
        // Dynamic Specs mapping based on sector
        $specs = [];
        if ($sector === 'Laptops') {
            $specs = [
                'cpu' => $_POST['cpu'] ?? '', 
                'gpu' => $_POST['gpu'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'battery' => $_POST['battery'] ?? '',
                'windows' => $_POST['windows'] ?? '',
                'series' => $_POST['series'] ?? '',
                'gen' => $_POST['gen'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
        } elseif ($sector === 'Gaming') {
            $specs = [
                'category' => $_POST['gaming_category'] ?? 'Consoles', 'series' => $_POST['series'] ?? '',
                'condition' => $_POST['condition'] ?? '', 'notes' => $_POST['notes'] ?? '',
                'ram' => $_POST['ram'] ?? '', 'storage' => $_POST['storage'] ?? '', 'cpu' => $_POST['cpu'] ?? '', 'gpu' => $_POST['gpu'] ?? ''
            ];
        } elseif ($sector === 'Desktops') {
            $specs = [
                'cpu_gen' => $_POST['cpu_gen'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
        } else {
            $specs = ['condition' => $_POST['condition'] ?? '', 'notes' => $_POST['notes'] ?? ''];
        }

        $specs_json = json_encode($specs);

        if ($_POST['action'] === 'edit_inventory' && isset($_POST['item_id'])) {
            // Concurrency Check: Verify if the record was updated by someone else
            $last_known = $_POST['last_updated_at'] ?? '';
            $stmt_check = $conn_wh->prepare("SELECT updated_at FROM inventory WHERE id = ?");
            $stmt_check->execute([$_POST['item_id']]);
            $current_ts = $stmt_check->fetchColumn();

            if ($last_known && $current_ts && $last_known !== $current_ts) {
                $error_msg = "CONCURRENCY_ERROR";
                header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $error_msg . "#wh-form-title");
                exit();
            }

            $stmt = $conn_wh->prepare("UPDATE inventory SET brand=?, model=?, specs_json=?, quantity=?, last_updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$brand, $model, $specs_json, $qty, $current_user, $_POST['item_id']]);
        } else {
            $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty]);
        }
        $msg = ($_POST['action'] === 'edit_inventory') ? 'updated' : 'added';
        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg . "#wh-form-title");
        exit();
    }
}

// Fetch All Unique Locations with Status
$stmt_locs = $conn_wh->query("
    SELECT l.*, 
        (SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code) as item_count,
        ls.color as status_color
    FROM locations l
    LEFT JOIN location_statuses ls ON l.status = ls.name
    ORDER BY l.location_code ASC
");
$existing_locs = $stmt_locs->fetchAll(PDO::FETCH_ASSOC);

// Fetch Available Statuses
$all_statuses = $conn_wh->query("SELECT * FROM location_statuses ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Sectors
$sectors = $conn_wh->query("SELECT * FROM sectors")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Inventory for selected sector and LOCATION
$items = [];
if ($selected_loc) {
    if ($selected_loc === 'GLOBAL') {
        if ($selected_sector === 'Master') {
            $stmt_i = $conn_wh->query("SELECT * FROM inventory ORDER BY sector ASC, id DESC");
            $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? ORDER BY id DESC");
            $stmt_i->execute([$selected_sector]);
            $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Ensure location entry exists
        $stmt_check = $conn_wh->prepare("INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')");
        $stmt_check->execute([$selected_loc]);

        $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code = ? ORDER BY id DESC");
        $stmt_i->execute([$selected_sector, $selected_loc]);
        $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<script id="warehouse-state" type="application/json">
    <?= json_encode(['activeSector' => $selected_sector], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>


<div class="warehouse-container">
    <header class="warehouse-header">
        <div class="warehouse-header-main">
            <div class="warehouse-title-block">
                <h1>Warehouse Control Center</h1>
                <p class="subtitle">Managing stock and locations across all inventory sectors.</p>
            </div>
            <?php if ($selected_loc): 
                $active_l_stmt = $conn_wh->prepare("SELECT l.*, ls.color FROM locations l LEFT JOIN location_statuses ls ON l.status = ls.name WHERE l.location_code = ?");
                $active_l_stmt->execute([$selected_loc]);
                $active_l = $active_l_stmt->fetch(PDO::FETCH_ASSOC);
                $active_l_status = $active_l['status'] ?? 'Idle';
                $active_l_color = $active_l['color'] ?? '#94a3b8';
            ?>
                <div class="active-loc-display" style="display:flex; align-items:center; gap:15px;">
                    <div style="text-align:right;">
                        <div class="loc-label">Active Location</div>
                        <div style="font-size:0.65rem; font-weight:900; text-transform:uppercase; color:<?= $active_l_color ?>; letter-spacing:0.05em;"><?= htmlspecialchars($active_l_status) ?></div>
                    </div>
                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>" class="loc-active-badge">
                        <span class="loc-pin">📍</span>
                        <span class="loc-text"><?= htmlspecialchars($selected_loc) ?></span>
                        <span class="loc-change">Change</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$selected_loc): ?>
        <div class="location-gate">
            <div class="gate-options-container">
                <!-- OPTION 1: REGISTRATION / WORKING ZONE -->
                <div class="gate-card main-gate">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2 style="font-weight:900; margin-bottom:4px;">Select Working Zone</h2>
                            <p style="color:var(--text-secondary); font-size: 0.9rem;">Choose a shelf to register or edit stock.</p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div class="search-container" style="max-width: 200px; margin: 0;">
                                <i class="search-icon">🔍</i>
                                <input type="text" id="gate-loc-search" placeholder="Find zone..." onkeyup="filterGateLocations()" class="search-input" style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                            </div>
                            <select id="gate-loc-sort" onchange="sortGateLocations()" style="width: auto; height: 40px; font-size: 0.8rem; border-radius: 10px; padding: 0 12px; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: white; outline: none;">
                                <option value="asc">Sort: A-Z</option>
                                <option value="desc">Sort: Z-A</option>
                                <option value="status">Sort: Status Group</option>
                                <option value="count-desc">Sort: Most Items</option>
                                <option value="count-asc">Sort: Emptiest</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="loc-grid" id="gate-loc-grid">
                        <?php foreach ($existing_locs as $loc): 
                            $l_name = $loc['location_code'];
                            $l_status = $loc['status'];
                            $l_color = $loc['status_color'] ?: '#94a3b8';
                            $l_count = (int)$loc['item_count'];
                        ?>
                            <div class="loc-item-wrapper" style="position:relative;">
                                <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=<?= urlencode($l_name) ?>" 
                                   class="loc-item gate-loc-item" 
                                   data-loc-name="<?= htmlspecialchars(strtolower($l_name)) ?>"
                                   data-status="<?= htmlspecialchars(strtolower($l_status)) ?>"
                                   data-count="<?= $l_count ?>">
                                    <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:<?= $l_color ?>; letter-spacing:0.05em;"><?= htmlspecialchars($l_status) ?></div>
                                    <span class="loc-icon">📦</span>
                                    <span class="loc-name"><?= htmlspecialchars($l_name) ?></span>
                                    <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= $l_count ?> Items</div>
                                </a>
                                <button type="button" onclick='openRenameModal(<?= json_encode($loc) ?>)' class="btn-rename-zone" style="position:absolute; bottom:5px; right:5px; background:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; font-size:0.7rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.1); opacity:0; transition:0.2s;">✏️</button>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="loc-item new-loc">
                            <form method="GET" action="index.php" style="width:100%;">
                                <input type="hidden" name="view" value="warehouse">
                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                <input type="text" name="loc" placeholder="+ New Zone" required style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none;">
                                <button type="submit" style="display:none;"></button>
                            </form>
                        </div>
                    </div>
                    <div id="gate-no-results" style="display:none; text-align:center; padding: 40px; color: #94a3b8; font-weight: 600;">
                        No matching zones found.
                    </div>
                </div>

                <!-- OPTION 2: GLOBAL DASHBOARD (Combined) -->
                <div class="gate-card">
                    <div style="font-size: 3.5rem; margin-bottom: 25px;">📊</div>
                    <h2 style="font-weight:900; margin-bottom:10px;">Global Dashboard</h2>
                    <p style="color:var(--text-secondary); margin-bottom:30px;">Managing stock and locations across all inventory sectors in one easy view.</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                        <a href="index.php?view=warehouse&sector=Master&loc=GLOBAL" 
                           style="display: block; width: 100%; padding: 18px; background: var(--text-main); color: white; border-radius: 14px; font-weight: 800; text-decoration: none; transition: 0.2s; font-size: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                           🏢 Master Overview (All Stock)
                        </a>
                        
                        <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=GLOBAL" 
                           style="display: block; width: 100%; padding: 15px; border: 2px solid #e2e8f0; color: #64748b; border-radius: 14px; font-weight: 700; text-decoration: none; transition: 0.2s; font-size: 0.9rem;">
                           🌐 View Only <?= htmlspecialchars($selected_sector) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>

    <!-- Sector Navigation -->
    <div class="sector-nav">
        <?php foreach ($sectors as $s): ?>
            <a href="index.php?view=warehouse&sector=<?= urlencode($s['name']) ?>&loc=<?= urlencode($selected_loc) ?>" 
               class="sector-card <?= $selected_sector === $s['name'] ? 'active' : '' ?>" 
               data-sector="<?= htmlspecialchars($s['name']) ?>">
                <span class="sector-icon"><?= $s['icon'] ?></span>
                <span class="sector-name"><?= htmlspecialchars($s['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Main Content Grid -->
    <div class="warehouse-layout">
        
        <!-- Inventory List -->
        <section class="inventory-feed">
            <div class="inventory-feed-header">
                <div class="inventory-summary-title">
                    <h2><?= htmlspecialchars($selected_sector) ?> Inventory</h2>
                    <?php 
                        $total_qty = 0;
                        foreach($items as $it) $total_qty += (int)($it['quantity'] ?? 0);
                    ?>
                    <div class="inventory-total-count">
                        Total Qty: <span class="count-value"><?= number_format($total_qty) ?> Units</span>
                    </div>
                </div>
                <div class="inventory-actions">
                    <div class="search-container">
                        <i class="search-icon">🔍</i>
                        <input type="text" id="wh-search" placeholder="Search items..." aria-label="Search warehouse inventory" onkeyup="filterWarehouse()" class="search-input">
                    </div>
                    <button type="button" onclick="downloadWarehouseCSV()" class="btn-export">
                        📊 Export CSV
                    </button>
                    <button type="button" onclick="window.location.href='index.php?view=import_warehouse'" class="btn-export" style="background: var(--text-main); color: white; border: none;">
                        📥 Import Bulk
                    </button>
                </div>
            </div>

            <div class="inventory-table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th class="col-type">Location</th>
                            <?php if ($selected_sector === 'Master'): ?>
                                <th>Sector</th>
                            <?php endif; ?>
                            <th class="col-main">Make/Model</th>
                            <th class="col-qty">QTY</th>
                            <?php if ($selected_sector === 'Laptops'): ?>
                                <th>CPU</th>
                                <th>Ram/Storage</th>
                                <th>Series</th>
                            <?php elseif ($selected_sector === 'Gaming'): ?>
                                <th>Category</th>
                                <th>CPU / GPU</th>
                                <th>RAM / Storage</th>
                            <?php elseif ($selected_sector === 'Desktops'): ?>
                                <th>CPU / Gen Brand</th>
                            <?php elseif ($selected_sector === 'Master'): ?>
                                <th>Core Specs</th>
                            <?php endif; ?>
                            <th>Notes</th>
                            <th class="col-log">Staff Log</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="inventory-list">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="10" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                                    No items found in this sector.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): 
                                $specs = json_decode($item['specs_json'], true) ?: [];
                                $created_date = date('m/d/y', strtotime($item['created_at']));
                                $updated_date = date('m/d/y', strtotime($item['updated_at']));
                            ?>
                                <tr class="inventory-card" 
                                     data-sector-theme="<?= htmlspecialchars($item['sector']) ?>"
                                     data-brand="<?= htmlspecialchars($item['brand']) ?>"
                                     data-model="<?= htmlspecialchars($item['model']) ?>"
                                     data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                                     data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['location_code'])) ?>">
                                    
                                    <td><span class="location-tag"><?= htmlspecialchars($item['location_code']) ?></span></td>
                                    
                                    <?php if ($selected_sector === 'Master'): ?>
                                        <td>
                                            <a href="index.php?view=warehouse&sector=<?= urlencode($item['sector']) ?>&loc=<?= urlencode($item['location_code']) ?>" style="text-decoration: none;">
                                                <span class="sector-badge sector-<?= strtolower($item['sector']) ?>"><?= htmlspecialchars($item['sector']) ?></span>
                                            </a>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <div class="cell-make"><?= htmlspecialchars($item['brand']) ?></div>
                                        <div class="cell-model"><?= htmlspecialchars($item['model']) ?></div>
                                    </td>

                                    <td><span class="qty-pill"><?= (int)$item['quantity'] ?></span></td>

                                    <?php if ($selected_sector === 'Laptops'): ?>
                                        <td><div class="spec-value"><?= htmlspecialchars($specs['cpu'] ?? '-') ?></div></td>
                                        <td><div class="spec-value"><?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></div></td>
                                        <td><div class="spec-value"><?= htmlspecialchars(($specs['series'] ?? '-') . ' (' . ($specs['gen'] ?? '-') . ')') ?></div></td>
                                    <?php elseif ($selected_sector === 'Gaming'): ?>
                                        <td><div class="spec-value"><?= htmlspecialchars($specs['category'] ?? '-') ?></div></td>
                                        <td><div class="spec-value"><?= htmlspecialchars(($specs['cpu'] ?? '-') . ' / ' . ($specs['gpu'] ?? '-')) ?></div></td>
                                        <td><div class="spec-value"><?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></div></td>
                                    <?php elseif ($selected_sector === 'Desktops'): ?>
                                        <td><div class="spec-value"><?= htmlspecialchars($specs['cpu_gen'] ?? '-') ?></div></td>
                                    <?php elseif ($selected_sector === 'Master'): ?>
                                        <td>
                                            <div class="spec-value" style="font-size: 0.75rem;">
                                                <?php 
                                                    if ($item['sector'] === 'Laptops') echo htmlspecialchars(($specs['cpu'] ?? '') . ' ' . ($specs['ram'] ?? ''));
                                                    elseif ($item['sector'] === 'Gaming') echo htmlspecialchars(($specs['category'] ?? '') . ' ' . ($specs['gpu'] ?? ''));
                                                    elseif ($item['sector'] === 'Desktops') echo htmlspecialchars($specs['cpu_gen'] ?? '');
                                                    else echo '-';
                                                ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <div class="notes-cell-wrapper">
                                            <div class="status-row">
                                                <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                                                <span class="condition-label"><?= htmlspecialchars($specs['condition'] ?? 'Used') ?></span>
                                                <?php if ($item['sector'] === 'Laptops'): ?>
                                                    <span class="battery-badge <?= empty($specs['battery']) ? 'missing' : '' ?>" title="Battery Status">
                                                        🔋 <?= !empty($specs['battery']) ? htmlspecialchars($specs['battery']) : 'Missing' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notes-text"><?= htmlspecialchars($specs['notes'] ?? '') ?></div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="staff-log-wrapper">
                                            <div class="log-entry">
                                                <span class="log-user">👤 <?= htmlspecialchars($item['user_owner']) ?></span>
                                                <span class="log-date">Created <?= $created_date ?></span>
                                            </div>
                                            <?php if ($item['last_updated_by']): ?>
                                                <div class="log-entry updated">
                                                    <span class="log-user">✏️ <?= htmlspecialchars($item['last_updated_by']) ?></span>
                                                    <span class="log-date">Edited <?= $updated_date ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="row-actions">
                                            <button type="button" class="row-action-btn btn-edit" onclick='editWarehouseItem(<?= json_encode($item) ?>)' title="Edit Entry">📝</button>
                                            <form method="POST" action="" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="action" value="delete_inventory">
                                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                                <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                                <button type="submit" class="row-action-btn btn-delete" title="Delete Entry">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                        <tr>
                            <td colspan="2" style="text-align: right; padding: 15px; font-size: 1.1rem; color: #334155; font-weight: 800;">Inventory Total:</td>
                            <td style="padding: 15px;">
                                <span class="qty-pill" id="table-total-qty" style="background: #1e293b; color: white; font-size: 1.1rem; padding: 6px 12px;">
                                    <?= number_format($total_qty) ?>
                                </span>
                            </td>
                            <?php if ($selected_sector === 'Laptops' || $selected_sector === 'Gaming'): ?>
                                <td colspan="6"></td>
                            <?php elseif ($selected_sector === 'Desktops'): ?>
                                <td colspan="4"></td>
                            <?php else: ?>
                                <td colspan="3"></td>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Add Item Sidebar (Hidden in Global View) -->
        <?php if ($selected_loc !== 'GLOBAL'): ?>
        <aside class="warehouse-sidebar">
            <div style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 20px;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 id="wh-form-title" style="font-weight: 800; margin: 0;">📥 Register Stock</h3>
                    <button type="button" id="btn-clone-last" onclick="fillLastEnteredData()" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; cursor: pointer; color: #475569; transition: all 0.2s;">
                        📋 Clone Last
                    </button>
                </div>
                <form method="POST" action="" id="wh-main-form">
                    <input type="hidden" name="action" id="wh-form-action" value="add_inventory">
                    <input type="hidden" name="item_id" id="wh-edit-id" value="">
                    <input type="hidden" name="last_updated_at" id="wh-last-updated" value="">
                    <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="wh-location-code">Location Code (Zone/Shelf)</label>
                        <input type="text" id="wh-location-code" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>" readonly style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; background:#f8fafc; color:#64748b; font-weight:700;">
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="wh-brand">Brand</label>
                            <input type="text" name="brand" list="brand-options" id="wh-brand" placeholder="Dell" required style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                            <datalist id="brand-options"></datalist>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="wh-model">Model</label>
                            <input type="text" name="model" list="model-options" id="wh-model" placeholder="Latitude" required style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                            <datalist id="model-options"></datalist>
                        </div></div>
                        <?php if (isset($_GET['msg'])): ?>
                    <div class="msg-banner <?= strpos($_GET['msg'], 'ERROR') !== false ? 'error' : '' ?>" id="wh-msg-banner" style="background: <?= strpos($_GET['msg'], 'ERROR') !== false ? '#fef2f2' : '#f0fdf4' ?>; border: 1px solid <?= strpos($_GET['msg'], 'ERROR') !== false ? '#fecaca' : '#bbf7d0' ?>; color: <?= strpos($_GET['msg'], 'ERROR') !== false ? '#991b1b' : '#15803d' ?>; padding: 12px 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.85rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; animation: slideDown 0.3s ease; transition: opacity 0.5s ease, transform 0.5s ease;">
                        <span><?= strpos($_GET['msg'], 'ERROR') !== false ? '⚠️' : '✅' ?></span>
                        <span style="flex: 1; margin: 0 10px;">
                            <?php 
                                if($_GET['msg'] === 'added') echo "Stock registered successfully!";
                                elseif($_GET['msg'] === 'updated') echo "Entry updated successfully!";
                                elseif($_GET['msg'] === 'deleted') echo "Entry removed from stock.";
                                elseif($_GET['msg'] === 'zone_renamed') echo "Working zone renamed successfully!";
                                elseif($_GET['msg'] === 'zone_deleted') echo "Working zone and all its items have been deleted.";
                                elseif($_GET['msg'] === 'CONCURRENCY_ERROR') echo "<strong>COLLISION:</strong> Record updated by another user. Please refresh and try again.";
                                else echo htmlspecialchars($_GET['msg']);
                            ?>
                        </span>
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#15803d; cursor:pointer; font-size:1.2rem; line-height:1; padding:0 5px; opacity:0.5;">&times;</button>
                    </div>
                <?php endif; ?>
                    

                    <!-- Sector Specific Fields -->
                    <div id="sector-specific-fields" style="border-top: 1px dashed #eee; padding-top: 15px; margin-bottom: 15px;">
                        <?php if ($selected_sector === 'Laptops'): ?>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-cpu">CPU</label>
                                    <input type="text" id="wh-spec-cpu" name="cpu" placeholder="Core i7-1185G7" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-gpu">GPU</label>
                                    <input type="text" id="wh-spec-gpu" name="gpu" placeholder="Integrated / RTX" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-ram">RAM</label>
                                    <input type="text" id="wh-spec-ram" name="ram" placeholder="16GB DDR4" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-storage">Storage</label>
                                    <input type="text" id="wh-spec-storage" name="storage" placeholder="512GB NVMe" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-battery">Battery Health</label>
                                    <input type="text" id="wh-spec-battery" name="battery" placeholder="85% Health" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-windows">Windows Version</label>
                                    <input type="text" id="wh-spec-windows" name="windows" placeholder="Win 11 Pro" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-series">Model Number</label>
                                    <input type="text" id="wh-spec-series" name="series" required placeholder="E7450" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="wh-spec-gen">Generation</label>
                                    <input type="text" id="wh-spec-gen" name="gen" required placeholder="11th Gen" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                        <?php elseif ($selected_sector === 'Gaming'): ?>
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="wh-gaming-cat">Category</label>
                                <select name="gaming_category" id="wh-gaming-cat" onchange="toggleGamingFields()" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                    <option value="PC">PC / Custom Build</option>
                                    <option value="Consoles">Consoles</option>
                                    <option value="Controllers">Controllers</option>
                                    <option value="Games">Games</option>
                                </select>
                            </div>

                            <!-- PC Specific -->
                            <div id="wh-gaming-pc-fields">
                                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label for="wh-gaming-pc-cpu">CPU</label>
                                        <input type="text" id="wh-gaming-pc-cpu" name="cpu" placeholder="Ryzen 7" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label for="wh-gaming-pc-gpu">GPU</label>
                                        <input type="text" id="wh-gaming-pc-gpu" name="gpu" placeholder="RTX 3070" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Specific Specs for everything else -->
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="wh-series" id="wh-gaming-spec-label">Specs / Series</label>
                                <input type="text" name="series" list="series-options" id="wh-series" placeholder="Series / Edition" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                <datalist id="series-options"></datalist>
                                <div id="wh-gaming-extra-specs" style="display: flex; gap: 10px; margin-top:5px;">
                                    <div class="form-group" style="flex: 1;">
                                        <input type="text" name="ram" id="wh-ram" placeholder="RAM / Color" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <input type="text" name="storage" id="wh-storage" placeholder="Storage" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($selected_sector === 'Electronics'): ?>
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="wh-elec-type">Device Type</label>
                                <input type="text" id="wh-elec-type" name="type" placeholder="Charger / Hub" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="wh-elec-spec">Specs / Condition</label>
                                <input type="text" id="wh-elec-spec" name="voltage" placeholder="65W / 19.5V" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                <input type="text" name="condition" placeholder="New" style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; margin-top:5px;">
                            </div>
                        <?php elseif ($selected_sector === 'Desktops'): ?>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="wh-spec-cpu-gen">CPU / Gen Brand</label>
                                <input type="text" id="wh-spec-cpu-gen" name="cpu_gen" list="cpu-gen-options" placeholder="i7 10th Gen / Intel" style="width:100%; height:40px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 600;">
                                <datalist id="cpu-gen-options">
                                    <option value="2nd-3rd Gen">
                                    <option value="4th-5th Gen">
                                    <option value="6th-7th Gen">
                                    <option value="i5-8th Gen">
                                    <option value="i7-8th Gen">
                                    <option value="i5-9th Gen">
                                    <option value="i7-9th Gen">
                                    <option value="i5-10th Gen">
                                    <option value="i7-10th Gen">
                                    <option value="i5-11th Gen">
                                    <option value="i7-11th Gen">
                                    <option value="i5-12th Gen">
                                    <option value="i7-12th Gen">
                                    <option value="i5-13th Gen">
                                    <option value="i7-13th Gen">
                                </datalist>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="wh-condition">Condition</label>
                            <select id="wh-condition" name="condition" style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight:700;">
                                <option value="A Grade">A Grade</option>
                                <option value="B Grade">B Grade</option>
                                <option value="C Grade">C Grade</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="wh-quantity">Initial Quantity</label>
                            <input type="number" id="wh-quantity" name="quantity" value="1" min="1" required style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 800;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="wh-notes">Notes / Observations</label>
                        <textarea id="wh-notes" name="notes" placeholder="Any scratches or specifics..." style="width:100%; height:80px; border-radius:10px; border:1px solid #ddd; padding: 10px; font-family:inherit; resize:none;"></textarea>
                    </div>

                    <button type="submit" id="wh-submit-btn" style="width:100%; height:50px; background:var(--text-main); color:white; border:none; border-radius:14px; font-weight:800; cursor:pointer;">
                        📥 Add to Stock
                    </button>
                    <button type="button" id="wh-cancel-edit" onclick="resetWarehouseForm()" style="display:none; width:100%; margin-top:10px; background:none; border:none; color:#64748b; font-weight:700; cursor:pointer;">Cancel Edit</button>
                </form>
            </div>
        </aside>
        <?php else: ?>
        <aside class="warehouse-sidebar" style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:20px; padding:40px; text-align:center; color:#64748b;">
            <div style="font-size:2rem; margin-bottom:15px;">🚫</div>
            <h3 style="font-weight:800;">Registration Locked</h3>
            <p>You are in <b>Global View</b>. To add or edit specific stock, please select a specific <b>Working Zone</b> from the gate.</p>
            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>" style="display:inline-block; margin-top:20px; color:var(--text-main); font-weight:800;">Back to Gate</a>
        </aside>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- warehouse.js is now loaded globally in index.php -->
<!-- Rename Zone Modal -->
<div id="rename-modal" class="modal-overlay no-print" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this) closeRenameModal()">
    <div style="background:white; border-radius:24px; width:95%; max-width:450px; padding:35px; box-shadow:var(--shadow-lg); position:relative;">
        <form method="POST" id="delete-zone-form" onsubmit="return confirm('CRITICAL ACTION: This will PERMANENTLY DELETE ALL ITEMS in this zone. This cannot be undone. Proceed?');">
            <input type="hidden" name="action" value="delete_zone">
            <input type="hidden" name="old_loc" id="delete-zone-loc">
            <button type="submit" class="btn-hidden-delete" title="Hidden: Delete Zone" style="position:absolute; top:20px; right:20px; background:none; border:none; cursor:pointer; font-size:1.1rem; opacity:0.1; transition:opacity 0.3s, transform 0.2s; padding:5px;">🗑️</button>
        </form>

        <h2 style="font-weight:900; margin-bottom:10px; font-size:1.25rem;">📦 Manage Working Zone</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:25px;">Update the name or operational status of this location.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="rename_zone">
            <input type="hidden" name="old_loc" id="rename-old-loc">
            
            <div class="form-group" style="margin-bottom:20px;">
                <label for="rename-new-loc" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Zone Name</label>
                <input type="text" name="new_loc" id="rename-new-loc" required style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:800; font-size:1rem;">
            </div>

            <div class="form-group" style="margin-bottom:30px;">
                <label for="rename-status" style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Location Status</label>
                <select name="location_status" id="rename-status" style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:700; cursor:pointer; background:#f8fafc;">
                    <?php foreach ($all_statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status['name']) ?>"><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:10px; text-align:right;">
                    <a href="javascript:void(0)" onclick="toggleManageStatuses()" style="font-size:0.7rem; color:var(--accent-color); font-weight:800; text-decoration:none;">+ Add New Status Type</a>
                </div>
            </div>

            <div id="manage-statuses-block" style="display:none; background:#f1f5f9; padding:20px; border-radius:16px; margin-bottom:25px; border:1px dashed #cbd5e1;">
                <div style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:#64748b; margin-bottom:10px;">Create New Status</div>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="new-status-name" placeholder="Status Name" style="flex:2; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 10px; font-size:0.85rem;">
                    <input type="color" id="new-status-color" value="#64748b" style="flex:0.5; height:38px; border:none; padding:0; background:none; cursor:pointer;">
                    <button type="button" onclick="addNewStatusType()" style="flex:1; background:var(--accent-color); color:white; border:none; border-radius:8px; font-weight:800; font-size:0.75rem; cursor:pointer;">Add</button>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="button" onclick="closeRenameModal()" style="flex:1; height:48px; border-radius:14px; border:1px solid #ddd; background:none; font-weight:800; cursor:pointer; color:#64748b;">Cancel</button>
                <button type="submit" style="flex:1; height:48px; border-radius:14px; border:none; background:var(--text-main); color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">Update Zone</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRenameModal(locData) {
        // locData is now an object
        const loc = locData.location_code;
        const status = locData.status;
        
        document.getElementById('rename-old-loc').value = loc;
        document.getElementById('delete-zone-loc').value = loc;
        document.getElementById('rename-new-loc').value = loc;
        document.getElementById('rename-status').value = status;
        
        document.getElementById('rename-modal').style.display = 'flex';
        document.getElementById('rename-new-loc').focus();
    }
    
    function closeRenameModal() {
        document.getElementById('rename-modal').style.display = 'none';
        document.getElementById('manage-statuses-block').style.display = 'none';
    }

    function toggleManageStatuses() {
        const block = document.getElementById('manage-statuses-block');
        block.style.display = block.style.display === 'none' ? 'block' : 'none';
    }

    async function addNewStatusType() {
        const name = document.getElementById('new-status-name').value;
        const color = document.getElementById('new-status-color').value;
        if (!name) return;

        const formData = new FormData();
        formData.append('action', 'add_location_status');
        formData.append('status_name', name);
        formData.append('status_color', color);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            if (response.ok) {
                location.reload();
            }
        } catch (err) {
            console.error("Failed to add status", err);
        }
    }

    // Add CSS for the rename button visibility on hover
    const style = document.createElement('style');
    style.textContent = `
        .loc-item-wrapper:hover .btn-rename-zone { opacity: 1 !important; }
        .btn-rename-zone:hover { transform: scale(1.1); background: #f8fafc !important; }
        .btn-hidden-delete:hover { opacity: 0.8 !important; transform: scale(1.2); color: #ef4444; }
    `;
    document.head.appendChild(style);

    // Ensure gaming fields are correctly toggled on load if Gaming is selected
    if (typeof toggleGamingFields === 'function') toggleGamingFields();
</script>
