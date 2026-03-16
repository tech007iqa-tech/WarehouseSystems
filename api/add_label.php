<?php
// api/add_label.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // 1. Validation & Sanitization
    if (empty($_POST['brand']) || empty($_POST['model'])) {
        throw new Exception("Brand and Model are required.");
    }

    $brand = sanitize_text($_POST['brand']);
    $model = sanitize_text($_POST['model']);
    $series = sanitize_text($_POST['series'] ?? null);
    $cpu_gen = sanitize_text($_POST['cpu_gen'] ?? null);
    $cpu_specs = sanitize_text($_POST['cpu_specs'] ?? null);
    $cpu_cores = sanitize_text($_POST['cpu_cores'] ?? null);
    $cpu_speed = sanitize_text($_POST['cpu_speed'] ?? null);
    $ram = isset($_POST['has_ram']) && $_POST['has_ram'] == '1' ? sanitize_text($_POST['ram'] ?? null) : null;
    $storage = isset($_POST['has_storage']) && $_POST['has_storage'] == '1' ? sanitize_text($_POST['storage'] ?? null) : null;
    $battery = isset($_POST['battery']) && $_POST['battery'] == '1' ? 1 : 0;
    $bios_state = sanitize_text($_POST['bios_state'] ?? 'Unknown');
    $description = sanitize_text($_POST['description'] ?? 'Untested');
    $warehouse_location = sanitize_text($_POST['warehouse_location'] ?? null);

    // 2. Check for Duplicates (Avoid redundant Label Profiles)
    // We check if an item with exact technical specs and location already exists.
    $check_stmt = $pdo_labels->prepare("
        SELECT id FROM items 
        WHERE brand = :brand AND model = :model AND (series = :series OR (series IS NULL AND :series_null IS NULL))
        AND (cpu_gen = :cpu_gen OR (cpu_gen IS NULL AND :cpu_gen_null IS NULL))
        AND (cpu_specs = :cpu_specs OR (cpu_specs IS NULL AND :cpu_specs_null IS NULL))
        AND (cpu_cores = :cpu_cores OR (cpu_cores IS NULL AND :cpu_cores_null IS NULL))
        AND (cpu_speed = :cpu_speed OR (cpu_speed IS NULL AND :cpu_speed_null IS NULL))
        AND (ram = :ram OR (ram IS NULL AND :ram_null IS NULL))
        AND (storage = :storage OR (storage IS NULL AND :storage_null IS NULL))
        AND bios_state = :bios_state AND description = :description 
        AND (warehouse_location = :location OR (warehouse_location IS NULL AND :location_null IS NULL))
        AND status = 'In Warehouse'
        LIMIT 1
    ");

    $check_stmt->execute([
        ':brand' => $brand, ':model' => $model, 
        ':series' => $series, ':series_null' => $series,
        ':cpu_gen' => $cpu_gen, ':cpu_gen_null' => $cpu_gen,
        ':cpu_specs' => $cpu_specs, ':cpu_specs_null' => $cpu_specs,
        ':cpu_cores' => $cpu_cores, ':cpu_cores_null' => $cpu_cores,
        ':cpu_speed' => $cpu_speed, ':cpu_speed_null' => $cpu_speed,
        ':ram' => $ram, ':ram_null' => $ram,
        ':storage' => $storage, ':storage_null' => $storage,
        ':bios_state' => $bios_state, ':description' => $description,
        ':location' => $warehouse_location, ':location_null' => $warehouse_location
    ]);

    $existing_item = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_item) {
        $inserted_id = $existing_item['id'];
        $is_duplicate = true;
    } else {
        // 3. Insert new row into labels.sqlite Database
        $is_duplicate = false;
        $stmt = $pdo_labels->prepare("
            INSERT INTO items (
                brand, model, series, cpu_gen, cpu_specs, cpu_cores, cpu_speed, 
                ram, storage, battery, bios_state, description, 
                warehouse_location, status
            ) VALUES (
                :brand, :model, :series, :cpu_gen, :cpu_specs, :cpu_cores, :cpu_speed, 
                :ram, :storage, :battery, :bios_state, :description, 
                :location, 'In Warehouse'
            )
        ");

        $stmt->execute([
            ':brand' => $brand,
            ':model' => $model,
            ':series' => $series,
            ':cpu_gen' => $cpu_gen,
            ':cpu_specs' => $cpu_specs,
            ':cpu_cores' => $cpu_cores,
            ':cpu_speed' => $cpu_speed,
            ':ram' => $ram,
            ':storage' => $storage,
            ':battery' => $battery,
            ':bios_state' => $bios_state,
            ':description' => $description,
            ':location' => $warehouse_location
        ]);

        $inserted_id = $pdo_labels->lastInsertId();
    }

    // 4. Return success to UI (No file generation here per user request)
    send_json_response(true, [
        'id' => $inserted_id,
        'is_duplicate' => $is_duplicate
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
