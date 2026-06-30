<?php
include 'core/warehouse_db.php';
include 'core/auth.php'; // Session is already started and checked

$current_user = $_SESSION['username'];
$selected_sector = $_GET['sector'] ?? 'Laptops';
$selected_loc = $_GET['loc'] ?? null;
$is_spreadsheet = ($selected_loc && $selected_loc !== 'GLOBAL');

// Handle Add/Edit/Delete Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        die("Security Error: CSRF Token Invalid.");
    }

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
                // Check if the new location code already exists
                $stmt_check = $conn_wh->prepare("SELECT COUNT(*) FROM locations WHERE location_code = ?");
                $stmt_check->execute([$new_loc]);
                $exists = $stmt_check->fetchColumn() > 0;

                // Update items in inventory from old to new location code
                $stmt = $conn_wh->prepare("UPDATE inventory SET location_code = ? WHERE location_code = ?");
                $stmt->execute([$new_loc, $old_loc]);

                if ($exists) {
                    // Merge: update status and timestamp of the existing target location, then delete old location entry
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                    $stmt_loc->execute([$new_status, $new_loc]);

                    if ($new_loc !== $old_loc) {
                        $stmt_del = $conn_wh->prepare("DELETE FROM locations WHERE location_code = ?");
                        $stmt_del->execute([$old_loc]);
                    }
                    $msg = "zone_merged";
                } else {
                    // Rename: target location doesn't exist, we can just update the existing location row
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET location_code = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                    $stmt_loc->execute([$new_loc, $new_status, $old_loc]);
                    $msg = "zone_updated";
                }

                $conn_wh->commit();
                header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=" . $msg);
                exit();
            } catch (Exception $e) {
                $conn_wh->rollBack();
                die("Failed to update zone: " . $e->getMessage());
            }
        }
    }

    if ($_POST['action'] === 'rename_working_zone' && isset($_POST['old_zone_name']) && isset($_POST['new_zone_name'])) {
        $old_zone = $_POST['old_zone_name'];
        $new_zone = trim($_POST['new_zone_name']);

        if (!empty($new_zone)) {
            $conn_wh->beginTransaction();
            try {
                // Check if the new working zone name already exists
                $stmt_check = $conn_wh->prepare("SELECT COUNT(*) FROM working_zones WHERE name = ?");
                $stmt_check->execute([$new_zone]);
                $exists = $stmt_check->fetchColumn() > 0;

                if ($exists) {
                    // Merge: If target zone exists, we update locations' working_zone_name to the new zone
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ? WHERE working_zone_name = ?");
                    $stmt_loc->execute([$new_zone, $old_zone]);

                    // Delete the old working zone as it's now empty/merged
                    if ($new_zone !== $old_zone) {
                        $stmt_del = $conn_wh->prepare("DELETE FROM working_zones WHERE name = ?");
                        $stmt_del->execute([$old_zone]);
                    }
                    $msg = "working_zone_merged";
                } else {
                    // Rename: normal update of the working zone name
                    $stmt = $conn_wh->prepare("UPDATE working_zones SET name = ? WHERE name = ?");
                    $stmt->execute([$new_zone, $old_zone]);

                    // Update locations pointing to the old zone name
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ? WHERE working_zone_name = ?");
                    $stmt_loc->execute([$new_zone, $old_zone]);
                    $msg = "working_zone_updated";
                }

                $conn_wh->commit();
                header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=" . $msg);
                exit();
            } catch (Exception $e) {
                $conn_wh->rollBack();
                die("Failed to update working zone: " . $e->getMessage());
            }
        }
    }

    if ($_POST['action'] === 'delete_working_zone' && isset($_POST['zone_name'])) {
        $zone_name = $_POST['zone_name'];
        $conn_wh->beginTransaction();
        try {
            $stmt_inv = $conn_wh->prepare("
                DELETE FROM inventory
                WHERE location_code IN (
                    SELECT location_code FROM locations WHERE working_zone_name = ?
                )
            ");
            $stmt_inv->execute([$zone_name]);

            $stmt_loc = $conn_wh->prepare("DELETE FROM locations WHERE working_zone_name = ?");
            $stmt_loc->execute([$zone_name]);

            $stmt_wz = $conn_wh->prepare("DELETE FROM working_zones WHERE name = ?");
            $stmt_wz->execute([$zone_name]);

            $conn_wh->commit();
            header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=working_zone_deleted");
            exit();
        } catch (Exception $e) {
            $conn_wh->rollBack();
            die("Delete working zone failed: " . $e->getMessage());
        }
    }

    if ($_POST['action'] === 'add_working_zone' && isset($_POST['zone_name'])) {
        $zone_name = trim($_POST['zone_name']);
        if (!empty($zone_name)) {
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
            $stmt->execute([$zone_name]);
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=zone_added");
        exit();
    }

    if ($_POST['action'] === 'add_sub_zone' && isset($_POST['shelf_name'])) {
        $shelf_name = trim($_POST['shelf_name']);
        $parent_zone = $_POST['parent_zone'] ?? 'General';
        if (!empty($shelf_name)) {
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
            $stmt->execute([$shelf_name, $parent_zone]);
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&zone=" . urlencode($parent_zone) . "&msg=shelf_added");
        exit();
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
        $qty = (int) $_POST['quantity'];
        $price = (float) ($_POST['price'] ?? 0.00);
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
                'bios' => $_POST['bios'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
        } elseif ($sector === 'Gaming') {
            $specs = [
                'category' => $_POST['gaming_category'] ?? 'Consoles',
                'series' => $_POST['series'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'cpu' => $_POST['cpu'] ?? '',
                'gpu' => $_POST['gpu'] ?? ''
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

            $stmt = $conn_wh->prepare("UPDATE inventory SET brand=?, model=?, specs_json=?, quantity=?, price=?, last_updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$brand, $model, $specs_json, $qty, $price, $current_user, $_POST['item_id']]);
            $last_id = $_POST['item_id'];
        } else {
            $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price]);
            $last_id = $conn_wh->lastInsertId();
        }
        $msg = ($_POST['action'] === 'edit_inventory') ? 'updated' : 'added';
        $hash = ($msg === 'added') ? '#wh-main-form' : '#inventory-list';
        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg . "&last_id=" . $last_id . $hash);
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

        if ($selected_sector === 'Master') {
            $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE location_code = ? ORDER BY id DESC");
            $stmt_i->execute([$selected_loc]);
        } else {
            $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code = ? ORDER BY id DESC");
            $stmt_i->execute([$selected_sector, $selected_loc]);
        }
        $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
    }
}

$highlight_id = $_GET['last_id'] ?? null;

// --- LIVEWIRE-STYLE AJAX VIEW HANDLER ---
if (UI::is_ajax()) {
    if (ob_get_level() > 0)
        ob_clean();
    ob_start();
    if (empty($items)): ?>
        <tr>
            <td colspan="10" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                No items found in this sector.
            </td>
        </tr>
    <?php else: ?>
        <tr id="wh-no-results" class="no-results-row" style="display: none;">
            <td colspan="12">
                <div class="no-results-wrapper" style="display: flex; justify-content: center; width: 100%;">
                    <div class="no-results-container">
                        <div class="no-results-icon">🕵️‍♂️</div>
                        <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.02em;">No
                            matches found</div>
                    </div>
                </div>
            </td>
        </tr>
        <?php foreach ($items as $item):
            $specs = json_decode($item['specs_json'], true) ?: [];

            if ($is_spreadsheet): ?>
                <tr class="inventory-card summary-row" data-id="<?= $item['id'] ?>"
                    data-brand="<?= htmlspecialchars($item['brand']) ?>"
                    data-model="<?= htmlspecialchars($item['model']) ?>"
                    data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                    data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                    data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">
                    <td class="editable-cell" data-field="brand">
                        <input type="text" class="cell-input" value="<?= htmlspecialchars($item['brand']) ?>" list="brand-options" placeholder="...">
                    </td>
                    <td class="editable-cell" data-field="model">
                        <input type="text" class="cell-input" value="<?= htmlspecialchars($item['model']) ?>" placeholder="...">
                    </td>

                    <?php if ($selected_sector === 'Laptops'): ?>
                        <td class="editable-cell" data-field="series">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="cpu">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" list="cpu-options-list" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="gen">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gen'] ?? '') ?>" list="gen-options-list" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="ram">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="storage">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="battery">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['battery'] ?? '') ?>" list="battery-options-list" placeholder="...">
                        </td>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                        <td class="editable-cell" data-field="gaming_category">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['category'] ?? '') ?>" list="gaming-cat-list" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="series">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="cpu">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="gpu">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gpu'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="ram">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="storage">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                        </td>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                        <td class="editable-cell" data-field="cpu_gen">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu_gen'] ?? '') ?>" list="cpu-gen-options-list" placeholder="...">
                        </td>
                    <?php else: // Electronics/Other ?>
                        <td class="editable-cell" data-field="type">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['type'] ?? '') ?>" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="voltage">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['voltage'] ?? '') ?>" placeholder="...">
                        </td>
                    <?php endif; ?>

                    <td class="editable-cell" data-field="condition">
                        <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['condition'] ?? 'Used') ?>" list="condition-options-list" placeholder="...">
                    </td>
                    <td class="editable-cell" data-field="notes">
                        <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['notes'] ?? '') ?>" placeholder="...">
                    </td>
                    <td class="editable-cell numeric" data-field="price">
                        <input type="number" step="any" class="cell-input text-right" value="<?= htmlspecialchars($item['price'] ?? '0.00') ?>">
                    </td>
                    <td class="editable-cell numeric" data-field="quantity">
                        <input type="number" step="1" class="cell-input text-center font-bold" value="<?= (int)$item['quantity'] ?>">
                    </td>
                    <td style="text-align:right;">
                        <div class="action-buttons">
                            <button type="button" class="btn-clone-row" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px;" title="Clone Row">➕</button>
                            <button type="button" class="btn-label"
                                onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                title="Generate & Download Label" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">🏷️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item?');">
                                <input type="hidden" name="action" value="delete_inventory">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                <?= UI::csrf_field() ?>
                                <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php else:
                $created_date = '';
                $created_date_only = '';
                $created_time_only = '';
                if (!empty($item['created_at'])) {
                    $date_created_obj = new DateTime($item['created_at'], new DateTimeZone('UTC'));
                    $date_created_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                    $created_date = $date_created_obj->format('m/d/y');
                    $created_date_only = $date_created_obj->format('m/d/y');
                    $created_time_only = $date_created_obj->format('h:i A');
                }

                $updated_date = '';
                if (!empty($item['updated_at'])) {
                    $date_updated_obj = new DateTime($item['updated_at'], new DateTimeZone('UTC'));
                    $date_updated_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                    $updated_date = $date_updated_obj->format('m/d/y');
                }
                ?>
                <tr class="inventory-card <?= ($highlight_id && $item['id'] == $highlight_id) ? 'highlight-row' : '' ?>"
                    data-id="<?= $item['id'] ?>" data-sector-theme="<?= htmlspecialchars($item['sector']) ?>"
                    data-brand="<?= htmlspecialchars($item['brand']) ?>" data-model="<?= htmlspecialchars($item['model']) ?>"
                    data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>" data-created-date="<?= $created_date_only ?>"
                    data-created-time="<?= $created_time_only ?>" data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                    data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['location_code'] . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['cpu_gen'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['series'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">

                    <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                    <td><span class="location-tag"><?= htmlspecialchars($item['location_code']) ?></span></td>

                    <?php if ($selected_sector === 'Master'): ?>
                        <td>
                            <a href="index.php?view=warehouse&sector=<?= urlencode($item['sector']) ?>&loc=<?= urlencode($item['location_code']) ?>"
                                style="text-decoration: none;">
                                <span
                                    class="sector-badge sector-<?= strtolower($item['sector']) ?>"><?= htmlspecialchars($item['sector']) ?></span>
                            </a>
                        </td>
                    <?php endif; ?>

                    <td>
                        <div class="cell-make"><?= htmlspecialchars($item['brand']) ?></div>
                        <div class="cell-model"><?= htmlspecialchars($item['model']) ?></div>
                    </td>

                    <td><span class="qty-pill"><?= (int) $item['quantity'] ?></span></td>

                    <td><span class="price-pill">$<?= number_format($item['price'] ?? 0, 0) ?></span></td>

                    <?php if ($selected_sector === 'Laptops'): ?>
                        <td>
                            <div class="spec-value"><?= htmlspecialchars($specs['cpu'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="spec-value">
                                <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                            </div>
                        </td>
                        <td>
                            <div class="spec-value">
                                <?= htmlspecialchars(($specs['series'] ?? '-') . ' (' . ($specs['gen'] ?? '-') . ')') ?>
                            </div>
                        </td>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                        <td>
                            <div class="spec-value"><?= htmlspecialchars($specs['category'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="spec-value">
                                <?= htmlspecialchars(($specs['cpu'] ?? '-') . ' / ' . ($specs['gpu'] ?? '-')) ?>
                            </div>
                        </td>
                        <td>
                            <div class="spec-value">
                                <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                            </div>
                        </td>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                        <td>
                            <div class="spec-value"><?= htmlspecialchars($specs['cpu_gen'] ?? '-') ?></div>
                        </td>
                    <?php elseif ($selected_sector === 'Master'): ?>
                        <td>
                            <div class="master-specs-wrapper">
                                <?php if ($item['sector'] === 'Laptops'): ?>
                                    <?php if (!empty($specs['cpu'])): ?>
                                        <span class="spec-tag cpu" title="CPU">💻 <?= htmlspecialchars($specs['cpu']) ?><?php if (!empty($specs['gen']) && $specs['gen'] !== '-'): ?> <small>(<?= htmlspecialchars($specs['gen']) ?>)</small><?php endif; ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                        <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($specs['series'])): ?>
                                        <span class="spec-tag series" title="Series">🏷️ <?= htmlspecialchars($specs['series']) ?></span>
                                    <?php endif; ?>
                                <?php elseif ($item['sector'] === 'Gaming'): ?>
                                    <?php if (!empty($specs['category'])): ?>
                                        <span class="spec-tag category" title="Category">🎮 <?= htmlspecialchars($specs['category']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($specs['gpu'])): ?>
                                        <span class="spec-tag gpu" title="GPU">⚡ <?= htmlspecialchars($specs['gpu']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                        <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                    <?php endif; ?>
                                <?php elseif ($item['sector'] === 'Desktops'): ?>
                                    <?php if (!empty($specs['cpu_gen'])): ?>
                                        <span class="spec-tag cpu" title="CPU/Gen">🖥️ <?= htmlspecialchars($specs['cpu_gen']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="spec-tag empty">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endif; ?>

                    <td>
                        <div class="notes-cell-wrapper">
                            <div class="status-row">
                                <?php if (!empty($item['status'])): ?>
                                    <span
                                        class="status-badge status-<?= htmlspecialchars($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                <?php endif; ?>
                                <?php
                                $cond = $specs['condition'] ?? 'Used';
                                $cond_class = 'cond-' . strtolower(str_replace(' ', '-', $cond));
                                ?>
                                <span class="condition-badge <?= $cond_class ?>"><?= htmlspecialchars($cond) ?></span>
                                <?php if ($item['sector'] === 'Laptops'): ?>
                                    <span class="battery-badge <?= empty($specs['battery']) ? 'missing' : '' ?>" title="Battery Status">
                                        🔋
                                        <?= !empty($specs['battery']) ? htmlspecialchars($specs['battery']) : 'Missing' ?>
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
                                    <span class="log-user">✏️
                                        <?= htmlspecialchars($item['last_updated_by']) ?></span>
                                    <span class="log-date">Edited <?= $updated_date ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td>
                        <div class="row-actions">
                            <button type="button" class="row-action-btn btn-edit" onclick='editWarehouseItem(<?= json_encode($item) ?>)'
                                title="Edit Entry">📝</button>
                            <button type="button" class="row-action-btn btn-label"
                                onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                title="Generate & Download Label">🏷️</button>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="action" value="delete_inventory">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                <?= UI::csrf_field() ?>
                                <button type="submit" class="row-action-btn btn-delete" title="Delete Entry">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($is_spreadsheet): ?>
            <!-- Permanent blank row at the bottom in spreadsheet AJAX response -->
            <tr class="summary-row new-blank-row" data-id="new">
                <td class="editable-cell" data-field="brand">
                    <input type="text" class="cell-input" list="brand-options" placeholder="Brand...">
                </td>
                <td class="editable-cell" data-field="model">
                    <input type="text" class="cell-input" placeholder="Model...">
                </td>

                <?php if ($selected_sector === 'Laptops'): ?>
                    <td class="editable-cell" data-field="series">
                        <input type="text" class="cell-input" placeholder="Series...">
                    </td>
                    <td class="editable-cell" data-field="cpu">
                        <input type="text" class="cell-input" list="cpu-options-list" placeholder="CPU...">
                    </td>
                    <td class="editable-cell" data-field="gen">
                        <input type="text" class="cell-input" list="gen-options-list" placeholder="Gen...">
                    </td>
                    <td class="editable-cell" data-field="ram">
                        <input type="text" class="cell-input" placeholder="RAM...">
                    </td>
                    <td class="editable-cell" data-field="storage">
                        <input type="text" class="cell-input" placeholder="Storage...">
                    </td>
                    <td class="editable-cell" data-field="battery">
                        <input type="text" class="cell-input" list="battery-options-list" placeholder="Battery...">
                    </td>
                <?php elseif ($selected_sector === 'Gaming'): ?>
                    <td class="editable-cell" data-field="gaming_category">
                        <input type="text" class="cell-input" list="gaming-cat-list" placeholder="Category...">
                    </td>
                    <td class="editable-cell" data-field="series">
                        <input type="text" class="cell-input" placeholder="Series...">
                    </td>
                    <td class="editable-cell" data-field="cpu">
                        <input type="text" class="cell-input" placeholder="CPU...">
                    </td>
                    <td class="editable-cell" data-field="gpu">
                        <input type="text" class="cell-input" placeholder="GPU...">
                    </td>
                    <td class="editable-cell" data-field="ram">
                        <input type="text" class="cell-input" placeholder="RAM...">
                    </td>
                    <td class="editable-cell" data-field="storage">
                        <input type="text" class="cell-input" placeholder="Storage...">
                    </td>
                <?php elseif ($selected_sector === 'Desktops'): ?>
                    <td class="editable-cell" data-field="cpu_gen">
                        <input type="text" class="cell-input" list="cpu-gen-options-list" placeholder="CPU/Gen...">
                    </td>
                <?php else: ?>
                    <td class="editable-cell" data-field="type">
                        <input type="text" class="cell-input" placeholder="Type...">
                    </td>
                    <td class="editable-cell" data-field="voltage">
                        <input type="text" class="cell-input" placeholder="Specs...">
                    </td>
                <?php endif; ?>

                <td class="editable-cell" data-field="condition">
                    <input type="text" class="cell-input" list="condition-options-list" placeholder="Condition...">
                </td>
                <td class="editable-cell" data-field="notes">
                    <input type="text" class="cell-input" placeholder="Notes...">
                </td>
                <td class="editable-cell numeric" data-field="price">
                    <input type="number" step="any" class="cell-input text-right" placeholder="Price...">
                </td>
                <td class="editable-cell numeric" data-field="quantity">
                    <input type="number" step="1" class="cell-input text-center font-bold" placeholder="Qty...">
                </td>
                <td style="text-align:right;">
                    <div class="action-buttons">
                        <button type="button" class="btn-add-row-indicator" style="background: none; border: none; font-size: 1rem; opacity: 0.3;">➕</button>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
    <?php endif;
    $table_html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'inventory-list' => $table_html
    ]);
    exit();
}
?>

<script id="warehouse-state" type="application/json">
    <?= json_encode(['activeSector' => $selected_sector], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>


<div class="warehouse-container">
    <header class="warehouse-header">
        <div class="warehouse-header-main">
            <div class="warehouse-title-block">
                <h1><a href="index.php?view=import_warehouse">Warehouse Control Center</a></h1>
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
                        <div
                            style="font-size:0.65rem; font-weight:900; text-transform:uppercase; color:<?= $active_l_color ?>; letter-spacing:0.05em;">
                            <?= htmlspecialchars($active_l_status) ?>
                        </div>
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

    <!-- Bulk Action Bar -->
    <div id="bulkActionBar" class="bulk-action-bar" style="display:none;">
        <div class="bulk-info">
            <span id="selectedCount">0</span> items selected
        </div>
        <div class="bulk-actions">
            <input type="text" id="bulkLocation" placeholder="Move to Zone..." list="gate-loc-datalist"
                style="width: 150px; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            <datalist id="gate-loc-datalist">
                <?php foreach ($existing_locs as $l)
                    echo "<option value='" . htmlspecialchars($l['location_code']) . "'>"; ?>
            </datalist>
            <div style="position:relative; display:flex; align-items:center;">
                <span style="position:absolute; left:10px; font-weight:800; color:var(--text-secondary);">$</span>
                <input type="number" id="bulkPrice" placeholder="Price"
                    style="width: 100px; padding: 10px 10px 10px 25px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
            <button id="applyBulkBtn" class="btn btn-success"
                style="background: white; color: var(--text-main); font-weight: 800; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer;">Apply
                Batch Changes</button>
            <button id="cancelBulkBtn"
                style="background: none; border: 1px solid rgba(255,255,255,0.3); color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; font-weight: 700;">Cancel</button>
        </div>
    </div>
    <?= UI::csrf_field() ?>

    <?php if (!$selected_loc): ?>
        <div class="location-gate">
            <div class="gate-options-container">
                <!-- OPTION 1: REGISTRATION / WORKING ZONE -->
                <div class="gate-card main-gate">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <div>
                            <h2 style="font-weight:900; margin-bottom:4px;">Select Working Zone</h2>
                            <p style="color:var(--text-secondary); font-size: 0.9rem;">Choose a shelf to register or edit
                                stock.</p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div class="search-container" style="max-width: 200px; margin: 0;">
                                <i class="search-icon">🔍</i>
                                <input type="text" id="gate-loc-search" placeholder="Find zone..."
                                    onkeyup="filterGateLocations()" class="search-input"
                                    style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                            </div>
                            <select id="gate-loc-sort" onchange="sortGateLocations()"
                                style="width: auto; height: 40px; font-size: 0.8rem; border-radius: 10px; padding: 0 12px; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: white; outline: none;">
                                <option value="asc">Sort: A-Z</option>
                                <option value="desc">Sort: Z-A</option>
                                <option value="status">Sort: Status Group</option>
                                <option value="count-desc">Sort: Most Items</option>
                                <option value="count-asc">Sort: Emptiest</option>
                            </select>
                        </div>
                    </div>

                    <?php
                    // Get current zone selection from GET parameter
                    $active_zone_name = $_GET['zone'] ?? null;

                    // Fetch working zones for the grid
                    $working_zones = $conn_wh->query("
                        SELECT wz.*,
                            (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name) as location_count,
                            (SELECT SUM((SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code)) FROM locations l WHERE l.working_zone_name = wz.name) as total_items
                        FROM working_zones wz
                        ORDER BY wz.name ASC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    if (!$active_zone_name): ?>
                        <div class="loc-grid" id="gate-loc-grid">
                            <div class="loc-item new-loc" style="padding: 10px;">
                                <form method="POST" action="" style="width:100%;">
                                    <input type="hidden" name="action" value="add_working_zone">
                                    <?= UI::csrf_field() ?>
                                    <input type="text" name="zone_name" placeholder="+ New Working Zone" required
                                        style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem;">
                                </form>
                            </div>

                            <?php foreach ($working_zones as $wz):
                                $wz_name = $wz['name'];
                                $wz_locations = (int) $wz['location_count'];
                                $wz_items = (int) $wz['total_items'];
                                ?>
                                <div class="loc-item-wrapper" style="position:relative;">
                                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&zone=<?= urlencode($wz_name) ?>"
                                        class="loc-item gate-loc-item" data-loc-name="<?= htmlspecialchars(strtolower($wz_name)) ?>"
                                        data-status="working" data-count="<?= $wz_locations ?>">
                                        <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:#3b82f6; letter-spacing:0.05em;">
                                            <small><?= $wz_locations ?></small> <?= $wz_locations == 1 ? "<small>Shelf</small>" : "<small>Locations</small>" ?>
                                        </div>
                                        <span class="loc-icon">📁</span>
                                        <span class="loc-name"><?= htmlspecialchars($wz_name) ?></span>
                                        <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= $wz_items ?> Items</div>
                                    </a>
                                    <button type="button" onclick='openRenameWorkingZoneModal(<?= json_encode($wz) ?>)'
                                        class="btn-rename-zone"
                                        style="position:absolute; bottom:5px; right:5px; background:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; font-size:0.7rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.1); opacity:0; transition:0.2s;">✏️</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else:
                        // Show shelves belonging to the active parent zone
                        $filtered_locs = [];
                        foreach ($existing_locs as $loc) {
                            if (($loc['working_zone_name'] ?? 'General') === $active_zone_name) {
                                $filtered_locs[] = $loc;
                            }
                        }
                        ?>
                        <div style="margin-bottom: 20px;">
                            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>" class="btn-export" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; box-shadow:none; display:inline-flex; width:auto; height:36px; padding:0 14px; border-radius:10px;">
                                🔙 Back to Zones
                            </a>
                            <span style="margin-left: 15px; font-weight: 800; color: var(--text-main); font-size: 1.1rem; vertical-align: middle;">
                                Zone: <?= htmlspecialchars($active_zone_name) ?>
                            </span>
                        </div>

                        <div class="loc-grid" id="gate-loc-grid">
                            <div class="loc-item new-loc" style="padding: 10px;">
                                <form method="POST" action="" style="width:100%;">
                                    <input type="hidden" name="action" value="add_sub_zone">
                                    <input type="hidden" name="parent_zone" value="<?= htmlspecialchars($active_zone_name) ?>">
                                    <?= UI::csrf_field() ?>
                                    <?php
                                        $prefix_placeholder = '';
                                        if (preg_match('/Zone\s+([a-zA-Z0-9]+)/i', $active_zone_name, $m)) {
                                            $prefix_placeholder = strtoupper($m[1]) . '-';
                                        }
                                    ?>
                                    <input type="text" name="shelf_name" placeholder="+ New Location (e.g. <?= $prefix_placeholder ?>1)" required
                                        value="<?= htmlspecialchars($prefix_placeholder) ?>"
                                        style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem;">
                                </form>
                            </div>

                            <?php foreach ($filtered_locs as $loc):
                                $l_name = $loc['location_code'];
                                $l_status = $loc['status'];
                                $l_color = $loc['status_color'] ?: '#94a3b8';
                                $l_count = (int) $loc['item_count'];
                                ?>
                                <div class="loc-item-wrapper" style="position:relative;">
                                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=<?= urlencode($l_name) ?>"
                                        class="loc-item gate-loc-item" data-loc-name="<?= htmlspecialchars(strtolower($l_name)) ?>"
                                        data-status="<?= htmlspecialchars(strtolower($l_status)) ?>" data-count="<?= $l_count ?>">
                                        <div
                                            style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:<?= $l_color ?>; letter-spacing:0.05em;">
                                            <?= htmlspecialchars($l_status) ?>
                                        </div>
                                        <span class="loc-icon">📦</span>
                                        <span class="loc-name"><?= htmlspecialchars($l_name) ?></span>
                                        <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= $l_count ?> Items</div>
                                    </a>
                                    <button type="button" onclick='openRenameModal(<?= json_encode($loc) ?>)'
                                        class="btn-rename-zone"
                                        style="position:absolute; bottom:5px; right:5px; background:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; font-size:0.7rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.1); opacity:0; transition:0.2s;">✏️</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div id="gate-no-results"
                        style="display:none; text-align:center; padding: 40px; color: #94a3b8; font-weight: 600;">
                        No matching zones found.
                    </div>
                </div>

                <!-- OPTION 2: GLOBAL DASHBOARD (Combined) -->
                <div class="gate-card">
                    <div style="font-size: 3.5rem; margin-bottom: 25px;">📊</div>
                    <h2 style="font-weight:900; margin-bottom:10px;">Global Dashboard</h2>
                    <p style="color:var(--text-secondary); margin-bottom:30px;">Managing stock and locations across all
                        inventory sectors in one easy view.</p>

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
        <div class="warehouse-layout <?= $is_spreadsheet ? 'spreadsheet-mode' : '' ?>">

            <?php if ($is_spreadsheet): ?>
                <!-- Metadata helper for JS -->
                <div id="warehouse-metadata"
                     data-csrf="<?= htmlspecialchars(Security::getToken()) ?>"
                     data-sector="<?= htmlspecialchars($selected_sector) ?>"
                     data-location-code="<?= htmlspecialchars($selected_loc) ?>"
                     style="display:none;"></div>

                <!-- Inventory List (Spreadsheet Mode) -->
                <section class="inventory-feed" style="width: 100%; max-width: none;">
                    <div class="inventory-feed-header">
                        <div class="inventory-summary-title">
                            <h2><?= htmlspecialchars($selected_sector) ?> Inventory</h2>
                            <?php
                            $total_qty = 0;
                            foreach ($items as $it) {
                                $total_qty += (int) ($it['quantity'] ?? 0);
                            }
                            ?>
                            <div class="inventory-total-count">
                                Total Qty: <span class="count-value" id="sidebar-total-qty"><?= number_format($total_qty) ?> Units</span>
                            </div>
                        </div>
                        <div class="inventory-actions">
                            <div class="search-container" style="flex: 1; max-width: 300px;">
                                <i class="search-icon">🔍</i>
                                <input type="text" id="wh-search" placeholder="Search items..."
                                    aria-label="Search warehouse inventory" onkeyup="syncSearch(this)"
                                    onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input">
                            </div>
                            <button type="button" onclick="downloadWarehouseCSV()" class="btn-export">
                                📊 Export CSV
                            </button>
                            <button type="button" onclick="window.location.href='index.php?view=import_warehouse'"
                                class="btn-export" style="background: #1e293b; color: white; border: none;">
                                📥 Import Bulk
                            </button>
                        </div>
                    </div>

                    <div class="scroll-hint">↔️ Swipe horizontally to edit/view all columns</div>
                    <div class="spreadsheet-table-wrapper">
                        <table class="spreadsheet-table">
                            <thead>
                                <?php if ($selected_sector === 'Laptops'): ?>
                                <tr>
                                    <th style="width: 10%;">Brand</th>
                                    <th style="width: 10%;">Model</th>
                                    <th style="width: 10%;">Series</th>
                                    <th style="width: 8%;">CPU</th>
                                    <th style="width: 8%;">Gen</th>
                                    <th style="width: 8%;">RAM</th>
                                    <th style="width: 10%;">Storage</th>
                                    <th style="width: 8%;">Battery</th>
                                    <th style="width: 10%;">Condition</th>
                                    <th style="width: 12%;">Notes</th>
                                    <th style="width: 8%;">Price</th>
                                    <th style="width: 6%;">Qty</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                                <?php elseif ($selected_sector === 'Gaming'): ?>
                                <tr>
                                    <th style="width: 10%;">Brand</th>
                                    <th style="width: 10%;">Model</th>
                                    <th style="width: 10%;">Category</th>
                                    <th style="width: 10%;">Specs/Series</th>
                                    <th style="width: 8%;">CPU</th>
                                    <th style="width: 8%;">GPU</th>
                                    <th style="width: 8%;">RAM</th>
                                    <th style="width: 10%;">Storage</th>
                                    <th style="width: 10%;">Condition</th>
                                    <th style="width: 12%;">Notes</th>
                                    <th style="width: 8%;">Price</th>
                                    <th style="width: 6%;">Qty</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                                <?php elseif ($selected_sector === 'Desktops'): ?>
                                <tr>
                                    <th style="width: 12%;">Brand</th>
                                    <th style="width: 15%;">Model</th>
                                    <th style="width: 18%;">CPU/Gen/Brand</th>
                                    <th style="width: 12%;">Condition</th>
                                    <th style="width: 25%;">Notes</th>
                                    <th style="width: 10%;">Price</th>
                                    <th style="width: 8%;">Qty</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <th style="width: 12%;">Brand</th>
                                    <th style="width: 15%;">Model</th>
                                    <th style="width: 15%;">Device Type</th>
                                    <th style="width: 15%;">Voltage/Specs</th>
                                    <th style="width: 12%;">Condition</th>
                                    <th style="width: 20%;">Notes</th>
                                    <th style="width: 10%;">Price</th>
                                    <th style="width: 8%;">Qty</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody id="inventory-list">
                                <?php foreach ($items as $item):
                                    $specs = json_decode($item['specs_json'], true) ?: [];
                                    ?>
                                    <tr class="inventory-card summary-row" data-id="<?= $item['id'] ?>"
                                        data-brand="<?= htmlspecialchars($item['brand']) ?>"
                                        data-model="<?= htmlspecialchars($item['model']) ?>"
                                        data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                                        data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                                        data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">
                                        <td class="editable-cell" data-field="brand">
                                            <input type="text" class="cell-input" value="<?= htmlspecialchars($item['brand']) ?>" list="brand-options" placeholder="...">
                                        </td>
                                        <td class="editable-cell" data-field="model">
                                            <input type="text" class="cell-input" value="<?= htmlspecialchars($item['model']) ?>" placeholder="...">
                                        </td>

                                        <?php if ($selected_sector === 'Laptops'): ?>
                                            <td class="editable-cell" data-field="series">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="cpu">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" list="cpu-options-list" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="gen">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gen'] ?? '') ?>" list="gen-options-list" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="ram">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="storage">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="battery">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['battery'] ?? '') ?>" list="battery-options-list" placeholder="...">
                                            </td>
                                        <?php elseif ($selected_sector === 'Gaming'): ?>
                                            <td class="editable-cell" data-field="gaming_category">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['category'] ?? '') ?>" list="gaming-cat-list" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="series">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="cpu">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="gpu">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gpu'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="ram">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="storage">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                                            </td>
                                        <?php elseif ($selected_sector === 'Desktops'): ?>
                                            <td class="editable-cell" data-field="cpu_gen">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu_gen'] ?? '') ?>" list="cpu-gen-options-list" placeholder="...">
                                            </td>
                                        <?php else: // Electronics/Other ?>
                                            <td class="editable-cell" data-field="type">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['type'] ?? '') ?>" placeholder="...">
                                            </td>
                                            <td class="editable-cell" data-field="voltage">
                                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['voltage'] ?? '') ?>" placeholder="...">
                                            </td>
                                        <?php endif; ?>

                                        <td class="editable-cell" data-field="condition">
                                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['condition'] ?? 'Used') ?>" list="condition-options-list" placeholder="...">
                                        </td>
                                        <td class="editable-cell" data-field="notes">
                                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['notes'] ?? '') ?>" placeholder="...">
                                        </td>
                                        <td class="editable-cell numeric" data-field="price">
                                            <input type="number" step="any" class="cell-input text-right" value="<?= htmlspecialchars($item['price'] ?? '0.00') ?>">
                                        </td>
                                        <td class="editable-cell numeric" data-field="quantity">
                                            <input type="number" step="1" class="cell-input text-center font-bold" value="<?= (int)$item['quantity'] ?>">
                                        </td>
                                        <td style="text-align:right;">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-clone-row" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px;" title="Clone Row">➕</button>
                                                <button type="button" class="btn-label"
                                                    onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                                    title="Generate & Download Label" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">🏷️</button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item?');">
                                                    <input type="hidden" name="action" value="delete_inventory">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                                    <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                                    <?= UI::csrf_field() ?>
                                                    <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Permanent blank row at the bottom -->
                                <tr class="summary-row new-blank-row" data-id="new">
                                    <td class="editable-cell" data-field="brand">
                                        <input type="text" class="cell-input" list="brand-options" placeholder="Brand...">
                                    </td>
                                    <td class="editable-cell" data-field="model">
                                        <input type="text" class="cell-input" placeholder="Model...">
                                    </td>

                                    <?php if ($selected_sector === 'Laptops'): ?>
                                        <td class="editable-cell" data-field="series">
                                            <input type="text" class="cell-input" placeholder="Series...">
                                        </td>
                                        <td class="editable-cell" data-field="cpu">
                                            <input type="text" class="cell-input" list="cpu-options-list" placeholder="CPU...">
                                        </td>
                                        <td class="editable-cell" data-field="gen">
                                            <input type="text" class="cell-input" list="gen-options-list" placeholder="Gen...">
                                        </td>
                                        <td class="editable-cell" data-field="ram">
                                            <input type="text" class="cell-input" placeholder="RAM...">
                                        </td>
                                        <td class="editable-cell" data-field="storage">
                                            <input type="text" class="cell-input" placeholder="Storage...">
                                        </td>
                                        <td class="editable-cell" data-field="battery">
                                            <input type="text" class="cell-input" list="battery-options-list" placeholder="Battery...">
                                        </td>
                                    <?php elseif ($selected_sector === 'Gaming'): ?>
                                        <td class="editable-cell" data-field="gaming_category">
                                            <input type="text" class="cell-input" list="gaming-cat-list" placeholder="Category...">
                                        </td>
                                        <td class="editable-cell" data-field="series">
                                            <input type="text" class="cell-input" placeholder="Series...">
                                        </td>
                                        <td class="editable-cell" data-field="cpu">
                                            <input type="text" class="cell-input" placeholder="CPU...">
                                        </td>
                                        <td class="editable-cell" data-field="gpu">
                                            <input type="text" class="cell-input" placeholder="GPU...">
                                        </td>
                                        <td class="editable-cell" data-field="ram">
                                            <input type="text" class="cell-input" placeholder="RAM...">
                                        </td>
                                        <td class="editable-cell" data-field="storage">
                                            <input type="text" class="cell-input" placeholder="Storage...">
                                        </td>
                                    <?php elseif ($selected_sector === 'Desktops'): ?>
                                        <td class="editable-cell" data-field="cpu_gen">
                                            <input type="text" class="cell-input" list="cpu-gen-options-list" placeholder="CPU/Gen...">
                                        </td>
                                    <?php else: ?>
                                        <td class="editable-cell" data-field="type">
                                            <input type="text" class="cell-input" placeholder="Type...">
                                        </td>
                                        <td class="editable-cell" data-field="voltage">
                                            <input type="text" class="cell-input" placeholder="Specs...">
                                        </td>
                                    <?php endif; ?>

                                    <td class="editable-cell" data-field="condition">
                                        <input type="text" class="cell-input" list="condition-options-list" placeholder="Condition...">
                                    </td>
                                    <td class="editable-cell" data-field="notes">
                                        <input type="text" class="cell-input" placeholder="Notes...">
                                    </td>
                                    <td class="editable-cell numeric" data-field="price">
                                        <input type="number" step="any" class="cell-input text-right" placeholder="Price...">
                                    </td>
                                    <td class="editable-cell numeric" data-field="quantity">
                                        <input type="number" step="1" class="cell-input text-center font-bold" placeholder="Qty...">
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="action-buttons">
                                            <button type="button" class="btn-add-row-indicator" style="background: none; border: none; font-size: 1rem; opacity: 0.3;">➕</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                                <tr>
                                    <?php
                                    $total_cols_sp = 9;
                                    if ($selected_sector === 'Laptops') $total_cols_sp = 13;
                                    elseif ($selected_sector === 'Gaming') $total_cols_sp = 13;
                                    elseif ($selected_sector === 'Desktops') $total_cols_sp = 8;
                                    ?>
                                    <td colspan="<?= $total_cols_sp - 3 ?>" style="padding: 15px;">
                                        <div class="search-container footer-search" style="max-width: 300px; margin: 0;">
                                            <i class="search-icon">🔍</i>
                                            <input type="text" id="wh-search-footer" placeholder="Filter these results..."
                                                onkeyup="syncSearch(this)"
                                                onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input"
                                                style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                                        </div>
                                    </td>
                                    <td style="text-align: right; padding: 15px; font-size: 1.1rem; color: #334155; font-weight: 800;">
                                        Inventory Total:
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span class="qty-pill" id="table-total-qty" style="background: #1e293b; color: white; font-size: 1.1rem; padding: 6px 12px; display: inline-block;">
                                            <?= number_format($total_qty) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; padding: 15px;">
                                        <button type="button" id="btn-consolidate-spreadsheet" class="btn-consolidate" onclick="consolidateWarehouseRows()" style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 8px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.75rem; color: #475569;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'" title="Consolidate duplicate rows">
                                            🔄 Consolidate
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Hidden datalists for cells -->
                <datalist id="cpu-options-list">
                    <option value="i3"></option>
                    <option value="i5"></option>
                    <option value="i7"></option>
                    <option value="i9"></option>
                    <option value="Ryzen 3"></option>
                    <option value="Ryzen 5"></option>
                    <option value="Ryzen 7"></option>
                    <option value="Ryzen 9"></option>
                </datalist>
                <datalist id="gen-options-list">
                    <option value="-"></option>
                    <option value="4th & 5th"></option>
                    <option value="6th & 7th"></option>
                    <option value="8th"></option>
                    <option value="9th"></option>
                    <option value="10th"></option>
                    <option value="11th"></option>
                    <option value="12th"></option>
                    <option value="13th"></option>
                    <option value="14th"></option>
                    <option value="Core 2 Duo"></option>
                    <option value="2nd"></option>
                    <option value="3rd"></option>
                    <option value="AMD"></option>
                </datalist>
                <datalist id="battery-options-list">
                    <option value="Yes"></option>
                    <option value="Unknown"></option>
                </datalist>
                <datalist id="gaming-cat-list">
                    <option value="PC"></option>
                    <option value="Consoles"></option>
                    <option value="Controllers"></option>
                    <option value="Games"></option>
                </datalist>
                <datalist id="cpu-gen-options-list">
                    <option value="2nd-3rd Gen"></option>
                    <option value="4th-5th Gen"></option>
                    <option value="6th-7th Gen"></option>
                    <option value="i5-8th Gen"></option>
                    <option value="i7-8th Gen"></option>
                    <option value="i5-9th Gen"></option>
                    <option value="i7-9th Gen"></option>
                    <option value="i5-10th Gen"></option>
                    <option value="i7-10th Gen"></option>
                    <option value="i5-11th Gen"></option>
                    <option value="i7-11th Gen"></option>
                    <option value="i5-12th Gen"></option>
                    <option value="i7-12th Gen"></option>
                    <option value="i5-13th Gen"></option>
                    <option value="i7-13th Gen"></option>
                </datalist>
                <datalist id="condition-options-list">
                    <option value="A Grade"></option>
                    <option value="B Grade"></option>
                    <option value="C Grade"></option>
                    <option value="No Power"></option>
                    <option value="No Post"></option>
                </datalist>

            <?php else: ?>
                <!-- Inventory List -->
                <section class="inventory-feed">
                    <div class="inventory-feed-header">
                        <div class="inventory-summary-title">
                            <h2><?= htmlspecialchars($selected_sector) ?> Inventory</h2>
                            <?php
                            $total_qty = 0;
                            foreach ($items as $it)
                                $total_qty += (int) ($it['quantity'] ?? 0);
                            ?>
                            <div class="inventory-total-count">
                                Total Qty: <span class="count-value"><?= number_format($total_qty) ?> Units</span>
                            </div>
                        </div>
                        <div class="inventory-actions">
                            <div class="search-container" style="flex: 1; max-width: 300px;">
                                <i class="search-icon">🔍</i>
                                <input type="text" id="wh-search" placeholder="Search items..."
                                    aria-label="Search warehouse inventory" onkeyup="syncSearch(this)"
                                    onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input">
                            </div>
                            <a href="#wh-main-form" class="btn-export"
                                style="background: var(--text-main); color: white; border: none;">NEW Item</a>
                            <button type="button" onclick="downloadWarehouseCSV()" class="btn-export">
                                📊 Export CSV
                            </button>
                            <button type="button" onclick="window.location.href='index.php?view=import_warehouse'"
                                class="btn-export" style="background: #1e293b; color: white; border: none;">
                                📥 Import Bulk
                            </button>
                        </div>
                    </div>

                    <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
                    <div class="inventory-table-container">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                                    <th class="col-type">Location</th>
                                    <?php if ($selected_sector === 'Master'): ?>
                                        <th>Sector</th>
                                    <?php endif; ?>
                                    <th class="col-main">Make/Model</th>
                                    <th class="col-qty">QTY</th>
                                    <th class="col-qty">Price</th>
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
                                    <th class="col-actions">Modify</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-list">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="10"
                                            style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                                            No items found in this sector.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Dynamic No Results Placeholder -->
                                    <tr id="wh-no-results" class="no-results-row" style="display: none;">
                                        <td colspan="12">
                                            <div class="no-results-wrapper"
                                                style="display: flex; justify-content: center; width: 100%;">
                                                <div class="no-results-container">
                                                    <div class="no-results-icon">🕵️‍♂️</div>
                                                    <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.02em;">No
                                                        matches found</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php foreach ($items as $item):
                                        $specs = json_decode($item['specs_json'], true) ?: [];

                                        // Timezone conversion to America/Los_Angeles
                                        $created_date = '';
                                        $created_date_only = '';
                                        $created_time_only = '';
                                        if (!empty($item['created_at'])) {
                                            $date_created_obj = new DateTime($item['created_at'], new DateTimeZone('UTC'));
                                            $date_created_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                                            $created_date = $date_created_obj->format('m/d/y');
                                            $created_date_only = $date_created_obj->format('m/d/y');
                                            $created_time_only = $date_created_obj->format('h:i A');
                                        }

                                        $updated_date = '';
                                        if (!empty($item['updated_at'])) {
                                            $date_updated_obj = new DateTime($item['updated_at'], new DateTimeZone('UTC'));
                                            $date_updated_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                                            $updated_date = $date_updated_obj->format('m/d/y');
                                        }
                                        ?>
                                        <tr class="inventory-card <?= ($highlight_id && $item['id'] == $highlight_id) ? 'highlight-row' : '' ?>"
                                            data-id="<?= $item['id'] ?>" data-sector-theme="<?= htmlspecialchars($item['sector']) ?>"
                                            data-brand="<?= htmlspecialchars($item['brand']) ?>"
                                            data-model="<?= htmlspecialchars($item['model']) ?>"
                                            data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                                            data-created-date="<?= $created_date_only ?>" data-created-time="<?= $created_time_only ?>"
                                            data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                                            data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['location_code'] . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['cpu_gen'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['series'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">

                                            <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                                            <td><span class="location-tag"><?= htmlspecialchars($item['location_code']) ?></span></td>

                                            <?php if ($selected_sector === 'Master'): ?>
                                                <td>
                                                    <a href="index.php?view=warehouse&sector=<?= urlencode($item['sector']) ?>&loc=<?= urlencode($item['location_code']) ?>"
                                                        style="text-decoration: none;">
                                                        <span
                                                            class="sector-badge sector-<?= strtolower($item['sector']) ?>"><?= htmlspecialchars($item['sector']) ?></span>
                                                    </a>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <div class="cell-make"><?= htmlspecialchars($item['brand']) ?></div>
                                                <div class="cell-model"><?= htmlspecialchars($item['model']) ?></div>
                                            </td>

                                            <td><span class="qty-pill"><?= (int) $item['quantity'] ?></span></td>

                                            <td><span class="price-pill">$<?= number_format($item['price'] ?? 0, 0) ?></span></td>

                                            <?php if ($selected_sector === 'Laptops'): ?>
                                                <td>
                                                    <div class="spec-value"><?= htmlspecialchars($specs['cpu'] ?? '-') ?></div>
                                                </td>
                                                <td>
                                                    <div class="spec-value">
                                                        <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="spec-value">
                                                        <?= htmlspecialchars(($specs['series'] ?? '-') . ' (' . ($specs['gen'] ?? '-') . ')') ?>
                                                    </div>
                                                </td>
                                            <?php elseif ($selected_sector === 'Gaming'): ?>
                                                <td>
                                                    <div class="spec-value"><?= htmlspecialchars($specs['category'] ?? '-') ?></div>
                                                </td>
                                                <td>
                                                    <div class="spec-value">
                                                        <?= htmlspecialchars(($specs['cpu'] ?? '-') . ' / ' . ($specs['gpu'] ?? '-')) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="spec-value">
                                                        <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                                                    </div>
                                                </td>
                                            <?php elseif ($selected_sector === 'Desktops'): ?>
                                                <td>
                                                    <div class="spec-value"><?= htmlspecialchars($specs['cpu_gen'] ?? '-') ?></div>
                                                </td>
                                            <?php elseif ($selected_sector === 'Master'): ?>
                                                <td>
                                                    <div class="master-specs-wrapper">
                                                        <?php if ($item['sector'] === 'Laptops'): ?>
                                                            <?php if (!empty($specs['cpu'])): ?>
                                                                <span class="spec-tag cpu" title="CPU">💻 <?= htmlspecialchars($specs['cpu']) ?><?php if (!empty($specs['gen']) && $specs['gen'] !== '-'): ?> <small>(<?= htmlspecialchars($specs['gen']) ?>)</small><?php endif; ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                                                <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($specs['series'])): ?>
                                                                <span class="spec-tag series" title="Series">🏷️ <?= htmlspecialchars($specs['series']) ?></span>
                                                            <?php endif; ?>
                                                        <?php elseif ($item['sector'] === 'Gaming'): ?>
                                                            <?php if (!empty($specs['category'])): ?>
                                                                <span class="spec-tag category" title="Category">🎮 <?= htmlspecialchars($specs['category']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($specs['gpu'])): ?>
                                                                <span class="spec-tag gpu" title="GPU">⚡ <?= htmlspecialchars($specs['gpu']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                                                <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                                            <?php endif; ?>
                                                        <?php elseif ($item['sector'] === 'Desktops'): ?>
                                                            <?php if (!empty($specs['cpu_gen'])): ?>
                                                                <span class="spec-tag cpu" title="CPU/Gen">🖥️ <?= htmlspecialchars($specs['cpu_gen']) ?></span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="spec-tag empty">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <div class="notes-cell-wrapper">
                                                    <div class="status-row">
                                                        <?php if (!empty($item['status'])): ?>
                                                            <span
                                                                class="status-badge status-<?= htmlspecialchars($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                                        <?php endif; ?>
                                                        <?php
                                                        $cond = $specs['condition'] ?? 'Used';
                                                        $cond_class = 'cond-' . strtolower(str_replace(' ', '-', $cond));
                                                        ?>
                                                        <span class="condition-badge <?= $cond_class ?>"><?= htmlspecialchars($cond) ?></span>
                                                        <?php if ($item['sector'] === 'Laptops'): ?>
                                                            <span class="battery-badge <?= empty($specs['battery']) ? 'missing' : '' ?>"
                                                                title="Battery Status">
                                                                🔋
                                                                <?= !empty($specs['battery']) ? htmlspecialchars($specs['battery']) : 'Missing' ?>
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
                                                            <span class="log-user">✏️
                                                                <?= htmlspecialchars($item['last_updated_by']) ?></span>
                                                            <span class="log-date">Edited <?= $updated_date ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="row-actions">
                                                    <button type="button" class="row-action-btn btn-edit"
                                                        onclick='editWarehouseItem(<?= json_encode($item) ?>)'
                                                        title="Edit Entry">📝</button>
                                                    <button type="button" class="row-action-btn btn-label"
                                                        onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                                        title="Generate & Download Label">🏷️</button>
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure?');">
                                                        <input type="hidden" name="action" value="delete_inventory">
                                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                        <input type="hidden" name="sector"
                                                            value="<?= htmlspecialchars($selected_sector) ?>">
                                                        <input type="hidden" name="location_code"
                                                            value="<?= htmlspecialchars($selected_loc) ?>">
                                                        <?= UI::csrf_field() ?>
                                                        <button type="submit" class="row-action-btn btn-delete"
                                                            title="Delete Entry">🗑️</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                                <tr>
                                    <td colspan="<?= $selected_sector === 'Master' ? 3 : 2 ?>" style="padding: 15px;">
                                        <div class="search-container footer-search" style="max-width: 300px; margin: 0;">
                                            <i class="search-icon">🔍</i>
                                            <input type="text" id="wh-search-footer" placeholder="Filter these results..."
                                                onkeyup="syncSearch(this)"
                                                onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input"
                                                style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                                        </div>
                                    </td>
                                    <td
                                        style="text-align: right; padding: 15px; font-size: 1.1rem; color: #334155; font-weight: 800;">
                                        Inventory Total:</td>
                                    <td style="padding: 15px;">
                                        <span class="qty-pill" id="table-total-qty"
                                            style="background: #1e293b; color: white; font-size: 1.1rem; padding: 6px 12px;">
                                            <?= number_format($total_qty) ?>
                                        </span>
                                    </td>
                                    <?php
                                    $total_cols = 9; // default for Electronics/Other
                                    if ($selected_sector === 'Laptops' || $selected_sector === 'Gaming') {
                                        $total_cols = 11;
                                    } elseif ($selected_sector === 'Desktops') {
                                        $total_cols = 9;
                                    } elseif ($selected_sector === 'Master') {
                                        $total_cols = 10;
                                    }
                                    $cols_used = ($selected_sector === 'Master' ? 3 : 2) + 2; // first td + Inventory Total td + Qty td
                                    $remaining_cols = $total_cols - $cols_used;
                                    ?>
                                    <td colspan="<?= $remaining_cols ?>"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <!-- Add Item Sidebar (Hidden in Global View) -->
                <?php if ($selected_loc !== 'GLOBAL'): ?>
                    <aside class="warehouse-sidebar">
                        <div
                            style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 20px;">

                            <?php
                            $parent_zone_link = "index.php?view=warehouse&sector=" . urlencode($selected_sector);
                            if ($selected_loc) {
                                $stmt_wz_link = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
                                $stmt_wz_link->execute([$selected_loc]);
                                $wz_name_val = $stmt_wz_link->fetchColumn();
                                if ($wz_name_val) {
                                    $parent_zone_link .= "&zone=" . urlencode($wz_name_val);
                                }
                            }
                            ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <a href="<?= htmlspecialchars($parent_zone_link) ?>" title="Back to current zone" style="text-decoration: none; font-size: 1.1rem; vertical-align: middle;">🔙</a>
                                    <h3 id="wh-form-title" style="font-weight: 800; margin: 0; display: inline-block; vertical-align: middle;">📥 Register Stock</h3>
                                </div>
                                <div id="session-counter"
                                    style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 16px; border-radius: 14px; font-size: 0.75rem; font-weight: 700; color: #15803d; display: none; line-height: 1.4; min-width: 180px;">
                                    <div>✨ <span id="session-count-val" style="font-weight: 900;">0</span> Added this session</div>
                                    <div id="session-last-item-info"
                                        style="font-size: 0.68rem; color: #166534; margin-top: 4px; border-top: 1px dashed #bbf7d0; padding-top: 4px; font-weight: 600; display: none;">
                                        Last: <strong id="session-last-model-series"></strong> (Qty: <span
                                            id="session-last-qty"></span>) @ <span id="session-last-time"></span>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                                <button type="button" id="btn-clone-last" onclick="fillLastEnteredData()"
                                    style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; cursor: pointer; color: #475569; transition: all 0.2s;">
                                    📋 Clone Last
                                </button>
                            </div>
                            <form method="POST" action="" id="wh-main-form">
                                <?= UI::csrf_field() ?>
                                <input type="hidden" name="action" id="wh-form-action" value="add_inventory">
                                <input type="hidden" name="item_id" id="wh-edit-id" value="">
                                <input type="hidden" name="last_updated_at" id="wh-last-updated" value="">
                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">

                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="wh-location-code">Location Code (Zone/Shelf)</label>
                                    <input type="text" id="wh-location-code" name="location_code"
                                        value="<?= htmlspecialchars($selected_loc) ?>" readonly
                                        style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; background:#f8fafc; color:#64748b; font-weight:700;">
                                </div>

                                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                                    <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                                        <label for="wh-brand">Brand</label>
                                        <input type="text" name="brand" list="brand-options" id="wh-brand" placeholder="Dell"
                                            required
                                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                                        <datalist id="brand-options"></datalist>
                                    </div>
                                    <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                                        <label for="wh-model">Model</label>
                                        <input type="text" name="model" list="model-options" id="wh-model" placeholder="Latitude"
                                            required
                                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                                        <datalist id="model-options"></datalist>
                                    </div>
                                    <?php if ($selected_sector === 'Laptops'): ?>
                                        <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                                            <label for="wh-spec-series">Series</label>
                                            <input type="text" id="wh-spec-series" name="series" required placeholder="E7450"
                                                style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                                        </div>
                                    <?php endif; ?>
                                </div>


                                <!-- Sector Specific Fields -->
                                <div id="sector-specific-fields"
                                    style="border-top: 1px dashed #eee; padding-top: 15px; margin-bottom: 15px;">
                                    <?php if ($selected_sector === 'Laptops'): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                            <div class="form-group"
                                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-cpu">CPU</label>
                                                <select id="wh-spec-cpu" name="cpu" required
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                                    <option value="N/A" style="background-color: #f1f5f9; color: #64748b;">-</option>
                                                    <option value="i3" style="background-color: #e0f2fe; color: #0369a1;">i3</option>
                                                    <option value="i5" style="background-color: #e0f2fe; color: #0369a1;">i5</option>
                                                    <option value="i7" style="background-color: #e0f2fe; color: #0369a1;">i7</option>
                                                    <option value="i9" style="background-color: #e0f2fe; color: #0369a1;">i9</option>
                                                    <option value="Ryzen 3" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 3
                                                    </option>
                                                    <option value="Ryzen 5" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 5
                                                    </option>
                                                    <option value="Ryzen 7" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 7
                                                    </option>
                                                    <option value="Ryzen 9" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 9
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="form-group"
                                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-gen">Generation</label>
                                                <input type="text" id="wh-spec-gen" name="gen" required list="gen-options"
                                                    placeholder="11th Gen"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                <datalist id="gen-options">
                                                    <option value="-">
                                                    <option value="4th & 5th">
                                                    <option value="6th & 7th">
                                                    <option value="8th">
                                                    <option value="9th">
                                                    <option value="10th">
                                                    <option value="11th">
                                                    <option value="12th">
                                                    <option value="13th">
                                                    <option value="14th">
                                                    <option value="Core 2 Duo">
                                                    <option value="2nd">
                                                    <option value="3rd">
                                                    <option value="AMD">
                                                </datalist>
                                            </div>
                                            <div class="form-group"
                                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-gpu">GPU</label>
                                                <input type="text" id="wh-spec-gpu" name="gpu" placeholder="Integrated / RTX"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                            </div>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                            <div class="form-group"
                                                style="flex: 1 1 80px; min-width: 80px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-windows">OS</label>
                                                <input type="text" id="wh-spec-windows" name="windows" list="os-options"
                                                    placeholder="Win 11 Pro"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                <datalist id="os-options">
                                                    <option value="Win11 Pro">
                                                    <option value="Windows 10 Pro">
                                                    <option value="Windows 11 Home"></option>
                                                </datalist>
                                            </div>
                                            <div class="form-group" id="wh-bios-state-group"
                                                style="flex: 1 1 80px; min-width: 80px; display: none; flex-direction: column;">
                                                <label for="wh-spec-bios">Bios</label>
                                                <select id="wh-spec-bios" name="bios"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                                    <option value="—">-</option>
                                                    <option value="Unlocked">Unlocked</option>
                                                    <option value="Locked">Locked</option>
                                                </select>
                                            </div>
                                            <div class="form-group"
                                                style="flex: 1 1 80px; min-width: 80px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-battery">Battery</label>
                                                <select id="wh-spec-battery" name="battery"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                                    <option value=""></option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="Unknown">Unknown</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                            <div class="form-group"
                                                style="flex: 1 1 120px; min-width: 120px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-ram">RAM</label>
                                                <input type="text" id="wh-spec-ram" name="ram" placeholder="16GB"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                            </div>
                                            <div class="form-group"
                                                style="flex: 1 1 120px; min-width: 120px; display: flex; flex-direction: column;">
                                                <label for="wh-spec-storage">Storage</label>
                                                <input type="text" id="wh-spec-storage" name="storage" placeholder="512GB NVMe"
                                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                            </div>
                                        </div>
                                    <?php elseif ($selected_sector === 'Gaming'): ?>
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <label for="wh-gaming-cat">Category</label>
                                            <select name="gaming_category" id="wh-gaming-cat" onchange="toggleGamingFields()"
                                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                                <option value="PC">PC / Custom Build</option>
                                                <option value="Consoles">Consoles</option>
                                                <option value="Controllers">Controllers</option>
                                                <option value="Games">Games</option>
                                            </select>
                                        </div>

                                        <!-- PC Specific -->
                                        <div id="wh-gaming-pc-fields">
                                            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                                <div class="form-group" style="flex: 1 1 200px;">
                                                    <label for="wh-gaming-pc-cpu">CPU</label>
                                                    <input type="text" id="wh-gaming-pc-cpu" name="cpu" placeholder="Ryzen 7"
                                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                </div>
                                                <div class="form-group" style="flex: 1 1 200px;">
                                                    <label for="wh-gaming-pc-gpu">GPU</label>
                                                    <input type="text" id="wh-gaming-pc-gpu" name="gpu" placeholder="RTX 3070"
                                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Specific Specs for everything else -->
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <label for="wh-series" id="wh-gaming-spec-label">Specs / Series</label>
                                            <input type="text" name="series" list="series-options" id="wh-series"
                                                placeholder="Series / Edition"
                                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                            <datalist id="series-options"></datalist>
                                            <div id="wh-gaming-extra-specs"
                                                style="display: flex; flex-wrap: wrap; gap: 10px; margin-top:5px;">
                                                <div class="form-group" style="flex: 1 1 200px;">
                                                    <input type="text" name="ram" id="wh-ram" placeholder="RAM / Color"
                                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                </div>
                                                <div class="form-group" style="flex: 1 1 200px;">
                                                    <input type="text" name="storage" id="wh-storage" placeholder="Storage"
                                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($selected_sector === 'Electronics'): ?>
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <label for="wh-elec-type">Device Type</label>
                                            <input type="text" id="wh-elec-type" name="type" placeholder="Charger / Hub"
                                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <label for="wh-elec-spec">Specs / Condition</label>
                                            <input type="text" id="wh-elec-spec" name="voltage" placeholder="65W / 19.5V"
                                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                            <input type="text" name="condition" placeholder="New"
                                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; margin-top:5px;">
                                        </div>
                                    <?php elseif ($selected_sector === 'Desktops'): ?>
                                        <div class="form-group" style="margin-bottom: 15px;">
                                            <label for="wh-spec-cpu-gen">CPU / Gen Brand</label>
                                            <input type="text" id="wh-spec-cpu-gen" name="cpu_gen" list="cpu-gen-options"
                                                placeholder="i7 10th Gen / Intel"
                                                style="width:100%; height:40px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 600;">
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

                                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                                        <label for="wh-condition">Condition</label>
                                        <select id="wh-condition" name="condition" onchange="toggleBiosState()"
                                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight:700;">
                                            <option value="A Grade" style="background-color: #dcfce740; color: #0b3f1eff;">A Grade
                                            </option>
                                            <option value="B Grade" style="background-color: #e0f2fe40; color: #014468ff;">B Grade
                                            </option>
                                            <option value="C Grade" style="background-color: #faf5ff40; color: #531888ff;">C Grade
                                            </option>
                                            <option value="No Power" style="background-color: #fee2e240; color: #741212ff;">No Power
                                            </option>
                                            <option value="No Post" style="background-color: #fff7ed40; color: #9c3d18ff;">No Post
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                                        <label for="wh-price">Price</label>
                                        <div style="position:relative; display:flex; align-items:center;">
                                            <span style="position:absolute; left:12px; font-weight:800; color:#64748b;">$</span>
                                            <input type="number" step="1" id="wh-price" name="price" value=".97" placeholder="150"
                                                min="0" required
                                                style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px 0 25px; font-weight: 800;">
                                        </div>
                                    </div>
                                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                                        <label for="wh-quantity">QTY</label>
                                        <input type="number" id="wh-quantity" name="quantity" value="1" min="1" required
                                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 800;">
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="wh-notes">Notes / Observations</label>
                                    <input type="text" id="wh-notes" name="notes" placeholder="Any scratches or specifics..."
                                        style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                                </div>

                                <button type="submit" id="wh-submit-btn"
                                    style="width:100%; height:50px; background:var(--text-main); color:white; border:none; border-radius:14px; font-weight:800; cursor:pointer;">
                                    📥 Add to Stock
                                </button>
                                <button type="button" id="wh-cancel-edit" onclick="resetWarehouseForm()"
                                    style="display:none; width:100%; margin-top:10px; background:none; border:none; color:#64748b; font-weight:700; cursor:pointer;">Cancel
                                    Edit</button>
                            </form>
                        </div>
                    </aside>
                <?php else: ?>
                    <aside class="warehouse-sidebar"
                        style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:20px; padding:40px; text-align:center; color:#64748b;">
                        <div style="font-size:2rem; margin-bottom:15px;">🚫</div>
                        <h3 style="font-weight:800;">Registration Locked</h3>
                        <p>You are in <b>Global View</b>. To add or edit specific stock, please select a specific <b>Working
                                Zone</b> from the gate.</p>
                        <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>"
                            style="display:inline-block; margin-top:20px; color:var(--text-main); font-weight:800;">Back to Gate</a>
                    </aside>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>

    <!-- warehouse.js is now loaded globally in index.php -->
    <!-- Rename Zone Modal -->
    <div id="rename-modal" class="modal-overlay no-print"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;"
        onclick="if(event.target===this) closeRenameModal()">
        <div
            style="background:white; border-radius:24px; width:95%; max-width:450px; padding:35px; box-shadow:var(--shadow-lg); position:relative;">
            <form method="POST" id="delete-zone-form"
                onsubmit="return confirm('CRITICAL ACTION: This will PERMANENTLY DELETE ALL ITEMS in this zone. This cannot be undone. Proceed?');">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="delete_zone">
                <input type="hidden" name="old_loc" id="delete-zone-loc">
                <button type="submit" class="btn-hidden-delete" title="Hidden: Delete Zone"
                    style="position:absolute; top:20px; right:20px; background:none; border:none; cursor:pointer; font-size:1.1rem; opacity:0.1; transition:opacity 0.3s, transform 0.2s; padding:5px;">🗑️</button>
            </form>

            <h2 style="font-weight:900; margin-bottom:10px; font-size:1.25rem;">📦 Manage Working Zone</h2>
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:25px;">Update the name or operational status of
                this location.</p>

            <form method="POST">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="rename_zone">
                <input type="hidden" name="old_loc" id="rename-old-loc">

                <div class="form-group" style="margin-bottom:20px;">
                    <label for="rename-new-loc"
                        style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Zone
                        Name</label>
                    <input type="text" name="new_loc" id="rename-new-loc" required
                        style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:800; font-size:1rem;">
                </div>

                <div class="form-group" style="margin-bottom:30px;">
                    <label for="rename-status"
                        style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Location
                        Status</label>
                    <select name="location_status" id="rename-status"
                        style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:700; cursor:pointer; background:#f8fafc;">
                        <?php foreach ($all_statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status['name']) ?>">
                                <?= htmlspecialchars($status['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:10px; text-align:right;">
                        <a href="javascript:void(0)" onclick="toggleManageStatuses()"
                            style="font-size:0.7rem; color:var(--accent-color); font-weight:800; text-decoration:none;">+
                            Add New Status Type</a>
                    </div>
                </div>

                <div id="manage-statuses-block"
                    style="display:none; background:#f1f5f9; padding:20px; border-radius:16px; margin-bottom:25px; border:1px dashed #cbd5e1;">
                    <div
                        style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:#64748b; margin-bottom:10px;">
                        Create New Status</div>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="new-status-name" placeholder="Status Name"
                            style="flex:2; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 10px; font-size:0.85rem;">
                        <input type="color" id="new-status-color" value="#64748b"
                            style="flex:0.5; height:38px; border:none; padding:0; background:none; cursor:pointer;">
                        <button type="button" onclick="addNewStatusType()"
                            style="flex:1; background:var(--accent-color); color:white; border:none; border-radius:8px; font-weight:800; font-size:0.75rem; cursor:pointer;">Apply</button>
                    </div>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeRenameModal()"
                        style="flex:1; height:48px; border-radius:14px; border:1px solid #ddd; background:none; font-weight:800; cursor:pointer; color:#64748b;">Cancel</button>
                    <button type="submit"
                        style="flex:1; height:48px; border-radius:14px; border:none; background:var(--text-main); color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">Update
                        Zone</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rename Working Zone Modal -->
    <div id="rename-working-zone-modal" class="modal-overlay no-print"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;"
        onclick="if(event.target===this) closeRenameWorkingZoneModal()">
        <div
            style="background:white; border-radius:24px; width:95%; max-width:450px; padding:35px; box-shadow:var(--shadow-lg); position:relative;">
            <form method="POST" id="delete-working-zone-form"
                onsubmit="return confirm('CRITICAL ACTION: This will PERMANENTLY DELETE THIS WORKING ZONE AND ALL LOCATIONS AND ITEMS inside it. This cannot be undone. Proceed?');">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="delete_working_zone">
                <input type="hidden" name="zone_name" id="delete-working-zone-name">
                <button type="submit" class="btn-hidden-delete" title="Hidden: Delete Working Zone"
                    style="position:absolute; top:20px; right:20px; background:none; border:none; cursor:pointer; font-size:1.1rem; opacity:0.1; transition:opacity 0.3s, transform 0.2s; padding:5px;">🗑️</button>
            </form>

            <h2 style="font-weight:900; margin-bottom:10px; font-size:1.25rem;">📁 Manage Working Zone</h2>
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:25px;">Update the name of this working zone.</p>

            <form method="POST">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="rename_working_zone">
                <input type="hidden" name="old_zone_name" id="rename-old-zone-name">

                <div class="form-group" style="margin-bottom:30px;">
                    <label for="rename-new-zone-name"
                        style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Working Zone Name</label>
                    <input type="text" name="new_zone_name" id="rename-new-zone-name" required
                        style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:800; font-size:1rem;">
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeRenameWorkingZoneModal()"
                        style="flex:1; height:48px; border-radius:14px; border:1px solid #ddd; background:none; font-weight:800; cursor:pointer; color:#64748b;">Cancel</button>
                    <button type="submit"
                        style="flex:1; height:48px; border-radius:14px; border:none; background:var(--text-main); color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">Update Zone</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRenameWorkingZoneModal(wzData) {
            const name = wzData.name;
            document.getElementById('rename-old-zone-name').value = name;
            document.getElementById('delete-working-zone-name').value = name;
            document.getElementById('rename-new-zone-name').value = name;

            document.getElementById('rename-working-zone-modal').style.display = 'flex';
            document.getElementById('rename-new-zone-name').focus();
        }

        function closeRenameWorkingZoneModal() {
            document.getElementById('rename-working-zone-modal').style.display = 'none';
        }

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
            const name = document.getElementById('new-status-name').value.trim();
            const color = document.getElementById('new-status-color').value;
            if (!name) return;

            const formData = new FormData();
            formData.append('action', 'add_location_status');
            formData.append('status_name', name);
            formData.append('status_color', color);
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                if (response.ok) {
                    const select = document.getElementById('rename-status');
                    if (select) {
                        const opt = document.createElement('option');
                        opt.value = name;
                        opt.textContent = name;
                        select.appendChild(opt);
                        select.value = name;
                    }
                    const block = document.getElementById('manage-statuses-block');
                    if (block) block.style.display = 'none';
                    document.getElementById('new-status-name').value = '';
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