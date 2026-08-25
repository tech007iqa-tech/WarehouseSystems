<?php
/**
 * Warehouse Action Controllers
 * Handles all POST requests for inventory, zones, statuses, and location photos.
 */

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

                // Sync custom status color and shelf association
                $target_loc = $new_loc;
                $stmt_color = $conn_wh->prepare("SELECT color FROM location_statuses WHERE name = ? ORDER BY (location_code = ?) DESC, is_default DESC LIMIT 1");
                $stmt_color->execute([$new_status, $target_loc]);
                $matched_color = $stmt_color->fetchColumn() ?: '#3b82f6';

                $stmt_is_def = $conn_wh->prepare("SELECT is_default FROM location_statuses WHERE name = ? AND (location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL')");
                $stmt_is_def->execute([$new_status]);
                $is_def = (int)$stmt_is_def->fetchColumn();

                $stmt_cur_cs = $conn_wh->prepare("SELECT rowid FROM location_statuses WHERE location_code = ?");
                $stmt_cur_cs->execute([$target_loc]);
                $cur_cs_id = $stmt_cur_cs->fetchColumn();

                if (!$is_def) {
                    if ($cur_cs_id) {
                        $conn_wh->prepare("UPDATE location_statuses SET name = ?, color = ? WHERE rowid = ?")->execute([$new_status, $matched_color, $cur_cs_id]);
                    } else {
                        $conn_wh->prepare("INSERT INTO location_statuses (name, color, is_default, location_code) VALUES (?, ?, 0, ?)")->execute([$new_status, $matched_color, $target_loc]);
                    }
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
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO location_statuses (name, color, is_default) VALUES (?, ?, 0)");
            $stmt->execute([$name, $color]);
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=status_added");
        exit();
    }

    if ($_POST['action'] === 'edit_location_status' && isset($_POST['status_id'])) {
        $id = (int)$_POST['status_id'];
        $name = trim($_POST['status_name'] ?? '');
        $color = $_POST['status_color'] ?? '#64748b';
        if ($id > 0 && !empty($name)) {
            $stmt_old = $conn_wh->prepare("SELECT name FROM location_statuses WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_name = $stmt_old->fetchColumn();

            $conn_wh->beginTransaction();
            $stmt = $conn_wh->prepare("UPDATE location_statuses SET name = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $color, $id]);

            if ($old_name && $old_name !== $name) {
                $conn_wh->prepare("UPDATE locations SET status = ? WHERE status = ?")->execute([$name, $old_name]);
            }
            $conn_wh->commit();
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=status_updated");
        exit();
    }

    if ($_POST['action'] === 'delete_location_status' && isset($_POST['status_id'])) {
        $id = (int)$_POST['status_id'];
        $stmt_cur = $conn_wh->prepare("SELECT * FROM location_statuses WHERE id = ?");
        $stmt_cur->execute([$id]);
        $cur = $stmt_cur->fetch(PDO::FETCH_ASSOC);

        $defaults = ['working', 'audit', 'shipping', 'in-review', 'warehoused', 'idle'];
        if ($cur && (int)$cur['is_default'] !== 1 && !in_array(strtolower($cur['name']), $defaults)) {
            $conn_wh->beginTransaction();
            $conn_wh->prepare("UPDATE locations SET status = 'Idle' WHERE status = ?")->execute([$cur['name']]);
            $conn_wh->prepare("DELETE FROM location_statuses WHERE id = ?")->execute([$id]);
            $conn_wh->commit();
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=status_deleted");
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

    if ($_POST['action'] === 'upload_location_photo') {
        $loc = $_POST['location_code'] ?? '';
        $sector = $_POST['sector'] ?? 'Laptops';
        $category = $_POST['category'] ?? 'General';
        $redirect_to = $_POST['redirect_to'] ?? 'location';
        $active_zone = $_POST['active_zone'] ?? '';

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../../../core/LocationPhotoProcessor.php';
            try {
                $processor = new LocationPhotoProcessor($conn_wh);
                $processor->processUpload(
                    $_FILES['photo']['tmp_name'],
                    $_FILES['photo']['name'],
                    $loc,
                    $sector,
                    $category,
                    $current_user
                );
                $msg = 'photo_uploaded';
            } catch (Exception $e) {
                $msg = 'photo_error&err=' . urlencode($e->getMessage());
            }
        } else {
            $msg = 'photo_error&err=No+file+selected';
        }

        if ($redirect_to === 'zone' && !empty($active_zone)) {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&zone=" . urlencode($active_zone) . "&msg=" . $msg);
        } else {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg);
        }
        exit();
    }

    if ($_POST['action'] === 'delete_location_photo') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $loc = $_POST['location_code'] ?? '';
        $sector = $_POST['sector'] ?? 'Laptops';
        $redirect_to = $_POST['redirect_to'] ?? 'location';
        $active_zone = $_POST['active_zone'] ?? '';

        if ($photo_id > 0) {
            try {
                $stmt = $conn_wh->prepare("SELECT * FROM location_photos WHERE id = ?");
                $stmt->execute([$photo_id]);
                $photo = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($photo) {
                    require_once __DIR__ . '/../../../core/Storage.php';
                    $ssdDriver = StorageManager::getDriver('ssd_local');
                    $archiveDriver = StorageManager::getDriver('spinning_disk');

                    $archiveDriver->delete($photo['archive_path']);
                    $ssdDriver->delete(basename($photo['optimized_path']));
                    $ssdDriver->delete(basename($photo['thumbnail_path']));

                    $stmt_del = $conn_wh->prepare("DELETE FROM location_photos WHERE id = ?");
                    $stmt_del->execute([$photo_id]);

                    $msg = 'photo_deleted';
                } else {
                    $msg = 'photo_not_found';
                }
            } catch (Exception $e) {
                $msg = 'photo_error&err=' . urlencode($e->getMessage());
            }
        } else {
            $msg = 'photo_error&err=Invalid+photo+ID';
        }

        if ($redirect_to === 'zone' && !empty($active_zone)) {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&zone=" . urlencode($active_zone) . "&msg=" . $msg);
        } else {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg);
        }
        exit();
    }
}
