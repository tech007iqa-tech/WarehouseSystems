<?php
// api/edit_label.php
// POST: Updates a hardware item record in labels.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception('A valid item ID is required.');
    }

    // Verify the item exists
    $stmt_check = $pdo_labels->prepare("SELECT id FROM items WHERE id = :id");
    $stmt_check->execute([':id' => $id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Item #' . $id . ' not found.');
    }

    // Sanitize all editable fields
    $brand               = sanitize_text($_POST['brand']              ?? null);
    $model               = sanitize_text($_POST['model']              ?? null);
    $series              = sanitize_text($_POST['series']             ?? null);
    $cpu_gen             = sanitize_text($_POST['cpu_gen']            ?? null);
    $cpu_details         = sanitize_text($_POST['cpu_details']        ?? null);
    $ram                 = sanitize_text($_POST['ram']                ?? null);
    $storage             = sanitize_text($_POST['storage']            ?? null);
    $battery             = isset($_POST['battery']) && $_POST['battery'] == '1' ? 1 : 0;
    $battery_specs       = sanitize_text($_POST['battery_specs']      ?? null);
    $gpu                 = sanitize_text($_POST['gpu']                ?? null);
    $screen_res          = sanitize_text($_POST['screen_res']         ?? null);
    $webcam              = sanitize_text($_POST['webcam']             ?? null);
    $backlit_kb          = sanitize_text($_POST['backlit_kb']         ?? null);
    $os_version          = sanitize_text($_POST['os_version']         ?? null);
    $cosmetic_grade      = sanitize_text($_POST['cosmetic_grade']     ?? null);
    $work_notes          = sanitize_text($_POST['work_notes']         ?? null);
    $bios_state          = sanitize_text($_POST['bios_state']         ?? null);
    $description         = sanitize_text($_POST['description']        ?? null);
    $warehouse_location  = sanitize_text($_POST['warehouse_location'] ?? null);
    $status              = sanitize_text($_POST['status']             ?? 'In Warehouse');

    if (!$brand || !$model) {
        throw new Exception('Brand and Model are required.');
    }

    $stmt = $pdo_labels->prepare("
        UPDATE items SET
            brand              = :brand,
            model              = :model,
            series             = :series,
            cpu_gen            = :cpu_gen,
            cpu_details        = :cpu_details,
            ram                = :ram,
            storage            = :storage,
            battery            = :battery,
            battery_specs      = :battery_specs,
            gpu                = :gpu,
            screen_res         = :screen_res,
            webcam             = :webcam,
            backlit_kb         = :backlit_kb,
            os_version         = :os_version,
            cosmetic_grade     = :cosmetic_grade,
            work_notes         = :work_notes,
            bios_state         = :bios_state,
            description        = :description,
            warehouse_location = :warehouse_location,
            status             = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':brand'              => $brand,
        ':model'              => $model,
        ':series'             => $series,
        ':cpu_gen'            => $cpu_gen,
        ':cpu_details'        => $cpu_details,
        ':ram'                => $ram,
        ':storage'            => $storage,
        ':battery'            => $battery,
        ':battery_specs'      => $battery_specs,
        ':gpu'                => $gpu,
        ':screen_res'         => $screen_res,
        ':webcam'             => $webcam,
        ':backlit_kb'         => $backlit_kb,
        ':os_version'         => $os_version,
        ':cosmetic_grade'     => $cosmetic_grade,
        ':work_notes'         => $work_notes,
        ':bios_state'         => $bios_state,
        ':description'        => $description,
        ':warehouse_location' => $warehouse_location,
        ':status'             => $status,
        ':id'                 => $id,
    ]);

    // Return the full updated row so the JS can re-render it
    $stmt_fetch = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt_fetch->execute([':id' => $id]);
    $updated_item = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    send_json_response(true, ['item' => $updated_item]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
