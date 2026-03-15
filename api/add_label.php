<?php
// api/add_label.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    // 1. Validation & Sanitization
    if (empty($_POST['brand']) || empty($_POST['model'])) {
        throw new Exception("Brand and Model are required.");
    }

    $brand = sanitize_text($_POST['brand']);
    $model = sanitize_text($_POST['model']);
    $series = sanitize_text($_POST['series'] ?? null);
    $cpu_gen = sanitize_text($_POST['cpu_gen'] ?? null);
    $cpu_details = sanitize_text($_POST['cpu_details'] ?? null);
    $ram = isset($_POST['has_ram']) && $_POST['has_ram'] == '1' ? sanitize_text($_POST['ram'] ?? null) : null;
    $storage = isset($_POST['has_storage']) && $_POST['has_storage'] == '1' ? sanitize_text($_POST['storage'] ?? null) : null;
    $battery = isset($_POST['battery']) && $_POST['battery'] == '1' ? 1 : 0;
    $bios_state = sanitize_text($_POST['bios_state'] ?? 'Unlocked');
    $description = sanitize_text($_POST['description'] ?? 'Untested');
    $warehouse_location = sanitize_text($_POST['warehouse_location'] ?? null);

    // 2. Insert into labels.sqlite Database
    $stmt = $pdo_labels->prepare("
        INSERT INTO items (
            brand, model, series, cpu_gen, cpu_details, 
            ram, storage, battery, bios_state, description, 
            warehouse_location, status
        ) VALUES (
            :brand, :model, :series, :cpu_gen, :cpu_details, 
            :ram, :storage, :battery, :bios_state, :description, 
            :location, 'In Warehouse'
        )
    ");

    $stmt->execute([
        ':brand' => $brand,
        ':model' => $model,
        ':series' => $series,
        ':cpu_gen' => $cpu_gen,
        ':cpu_details' => $cpu_details,
        ':ram' => $ram,
        ':storage' => $storage,
        ':battery' => $battery,
        ':bios_state' => $bios_state,
        ':description' => $description,
        ':location' => $warehouse_location
    ]);

    $inserted_id = $pdo_labels->lastInsertId();

    // 3. Generate the XML for the ODT Template Injection
    // The following is a raw OpenDocument text snippet replacing standard text blocks.
    $xml_content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" office:version="1.2">
  <office:body>
    <office:text>
      <text:p text:style-name="Title">ID: ' . $inserted_id . ' - ' . $brand . ' ' . $model . ' ' . $series . '</text:p>
      <text:p text:style-name="Standard">CPU: ' . $cpu_gen . ' / ' . $cpu_details . '</text:p>
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
            'file_path' => 'exports/labels/' . $final_odt_name
        ]);
    } else {
        // Warning: Database succeeded but file generation failed
        send_json_response(false, null, "Item saved to Database, but ODT generation failed. Check PowerShell permissions.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
