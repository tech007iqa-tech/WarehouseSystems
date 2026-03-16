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

    // 4. Generate the XML for the ODT Template Injection (Multi-page configuration)
    // We combine the split CPU info for the "Specs" line
    $cpu_spec_line = trim($cpu_specs . ' (' . $cpu_cores . ' @ ' . $cpu_speed . ')', ' (@ )');
    if (!$cpu_spec_line) $cpu_spec_line = '—';

    $xml_content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content 
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" 
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" 
    xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" 
    xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" 
    xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" 
    office:version="1.2">
  <office:automatic-styles>
    <style:style style:name="PB" style:family="paragraph" style:parent-style-name="Standard">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:p text:style-name="Title">' . $brand . ' ' . $model . ' ' . $series . '</text:p>
      <text:p text:style-name="PB">Technical Specifications (' . $cpu_gen . ')</text:p>
      <text:p text:style-name="Standard">CPU: ' . $cpu_spec_line . '</text:p>
      <text:p text:style-name="Standard">RAM: ' . ($ram ? $ram : 'None') . ' | Storage: ' . ($storage ? $storage : 'None') . '</text:p>
      <text:p text:style-name="Standard">Battery: ' . ($battery ? 'YES' : 'NO') . ' | BIOS: ' . $bios_state . '</text:p>
      <text:p text:style-name="Standard">Loc: ' . $warehouse_location . ' | Cond: ' . $description . '</text:p>
    </office:text>
  </office:body>
</office:document-content>';

    // 4. Save Temporary XML and call PowerShell Script
    $temp_xml_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'label_' . time() . '.xml';
    file_put_contents($temp_xml_path, $xml_content);

    $final_odt_name = "Label_" . $inserted_id . "_" . preg_replace('/[^a-zA-Z0-9]/', '', $brand . $model) . ".odt";
    $final_odt_path = realpath(__DIR__ . '/../exports/labels/') . DIRECTORY_SEPARATOR . $final_odt_name;
    
    $master_template = realpath(__DIR__ . '/../templates/label_template.odt');
    $ps_script = realpath(__DIR__ . '/../templates/scripts/generate_odt.ps1');

    // Execute PowerShell
    // Wrap paths in quotes to handle spaces in directory names
    $cmd = 'powershell.exe -ExecutionPolicy Bypass -File "' . $ps_script . '" -SourceXML "' . $temp_xml_path . '" -OutputODT "' . $final_odt_path . '" -MasterTemplate "' . $master_template . '"';
    
    $exec_output = shell_exec($cmd);

    // Clean up temporary XML
    unlink($temp_xml_path);

    if (strpos($exec_output, 'SUCCESS') !== false || file_exists($final_odt_path)) {
        send_json_response(true, [
            'id' => $inserted_id,
            'file_name' => $final_odt_name,
            'file_path' => 'exports/labels/' . $final_odt_name,
            'is_duplicate' => $is_duplicate
        ]);
    } else {
        // Warning: Database succeeded but file generation failed
        send_json_response(false, null, "Item saved to Database, but ODT generation failed. Check PowerShell permissions.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
