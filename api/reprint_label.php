<?php
// api/reprint_label.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("Valid Item ID is required.");
    }

    // 1. Fetch Item Data
    $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found.");
    }

    // 2. Map data for XML
    $brand = $item['brand'];
    $model = $item['model'];
    $series = $item['series'] ?? '';
    $cpu_gen = $item['cpu_gen'] ?? '';
    $cpu_specs = $item['cpu_specs'] ?? ($item['cpu_details'] ?? ''); // Fallback to old field
    $cpu_cores = $item['cpu_cores'] ?? '';
    $cpu_speed = $item['cpu_speed'] ?? '';
    $ram = $item['ram'] ?? 'None';
    $storage = $item['storage'] ?? 'None';
    $battery = (int)$item['battery'] === 1 ? 'YES' : 'NO';
    $bios_state = $item['bios_state'] ?? 'Unknown';
    $warehouse_location = $item['warehouse_location'] ?? 'Unassigned';
    $description = $item['description'] ?? 'Untested';

    // 3. Generate the XML (Multi-page configuration)
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
    $temp_xml_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reprint_' . time() . '.xml';
    file_put_contents($temp_xml_path, $xml_content);

    $final_odt_name = "Label_Reprint_" . $id . "_" . preg_replace('/[^a-zA-Z0-9]/', '', $brand . $model) . ".odt";
    $final_odt_path = realpath(__DIR__ . '/../exports/labels/') . DIRECTORY_SEPARATOR . $final_odt_name;
    
    $master_template = realpath(__DIR__ . '/../templates/label_template.odt');
    $ps_script = realpath(__DIR__ . '/../templates/scripts/generate_odt.ps1');

    $cmd = 'powershell.exe -ExecutionPolicy Bypass -File "' . $ps_script . '" -SourceXML "' . $temp_xml_path . '" -OutputODT "' . $final_odt_path . '" -MasterTemplate "' . $master_template . '"';
    
    $exec_output = shell_exec($cmd);
    unlink($temp_xml_path);

    if (strpos($exec_output, 'SUCCESS') !== false || file_exists($final_odt_path)) {
        send_json_response(true, [
            'file_name' => $final_odt_name,
            'file_path' => 'exports/labels/' . $final_odt_name
        ]);
    } else {
        throw new Exception("ODT generation failed.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
