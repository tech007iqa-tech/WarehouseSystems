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

    // 2. Map and Escape data for XML
    $brand    = htmlspecialchars($item['brand'] ?? '', ENT_XML1, 'UTF-8');
    $model    = htmlspecialchars($item['model'] ?? '', ENT_XML1, 'UTF-8');
    $series   = htmlspecialchars($item['series'] ?? '', ENT_XML1, 'UTF-8');
    $cpu_gen  = htmlspecialchars($item['cpu_gen'] ?? '', ENT_XML1, 'UTF-8');
    $cpu_specs= htmlspecialchars($item['cpu_specs'] ?? ($item['cpu_details'] ?? ''), ENT_XML1, 'UTF-8');
    $cpu_cores= htmlspecialchars($item['cpu_cores'] ?? '', ENT_XML1, 'UTF-8');
    $cpu_speed= htmlspecialchars($item['cpu_speed'] ?? '', ENT_XML1, 'UTF-8');
    $ram      = htmlspecialchars($item['ram'] ?? 'None', ENT_XML1, 'UTF-8');
    $storage  = htmlspecialchars($item['storage'] ?? 'None', ENT_XML1, 'UTF-8');
    $battery  = (int)($item['battery'] ?? 0) === 1 ? 'YES' : 'NO';
    $bios_state = htmlspecialchars($item['bios_state'] ?? 'Unknown', ENT_XML1, 'UTF-8');
    $warehouse_location = htmlspecialchars($item['warehouse_location'] ?? 'Unassigned', ENT_XML1, 'UTF-8');
    $description = htmlspecialchars($item['description'] ?? 'Untested', ENT_XML1, 'UTF-8');

    // 3. Generate the XML (Multi-page configuration + Quantity Loop)
    $qty = max(1, min(100, (int)($_POST['qty'] ?? 1)));
    $show_a = ($_POST['print_a'] ?? '1') === '1';
    $show_b = ($_POST['print_b'] ?? '1') === '1';

    $cpu_spec_line = trim($cpu_specs . ' (' . $cpu_cores . ' @ ' . $cpu_speed . ')', ' (@ )');
    if (!$cpu_spec_line) $cpu_spec_line = '—';
    $cpu_gen_display = $cpu_gen ? $cpu_gen : 'Gen Unknown';

    $xml_inner = '';
    for ($i = 0; $i < $qty; $i++) {
        // Label A (Branding)
        if ($show_a) {
            $xml_inner .= '<text:p text:style-name="P3">' . $brand . ' ' . $model . ' ' . $series . '</text:p>';
            if ($show_b || $i < $qty - 1) {
                $xml_inner .= '<text:p text:style-name="PB"></text:p>';
            }
        }

        // Label B (Specs)
        if ($show_b) {
            $xml_inner .= '<text:p text:style-name="Standard">Technical Specifications (' . $cpu_gen_display . ')</text:p>';
            $xml_inner .= '<text:p text:style-name="Standard">CPU: ' . $cpu_spec_line . '</text:p>';
            $xml_inner .= '<text:p text:style-name="Standard">RAM: ' . ($ram ? $ram : 'None') . ' | Storage: ' . ($storage ? $storage : 'None') . '</text:p>';
            $xml_inner .= '<text:p text:style-name="Standard">Battery: ' . ($battery ? 'YES' : 'NO') . ' | BIOS: ' . $bios_state . '</text:p>';
            $xml_inner .= '<text:p text:style-name="Standard">Loc: ' . $warehouse_location . ' | Cond: ' . $description . '</text:p>';
            
            // Add page break only if there is another set coming up
            if ($i < $qty - 1) {
                $xml_inner .= '<text:p text:style-name="PB"></text:p>';
            }
        }
    }

    // Use the raw inner XML as the content. PowerShell will inject this into the master template shell.
    $xml_content = $xml_inner;

    // 4. Validate XML well-formedness before injection (Wrap in dummy root for fragment check)
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    if (!$doc->loadXML("<root>$xml_content</root>")) {
        $errors = libxml_get_errors();
        $err_msg = "XML Content Error: ";
        foreach ($errors as $error) { $err_msg .= trim($error->message) . " "; }
        libxml_clear_errors();
        throw new Exception($err_msg);
    }

    // 5. Save Temporary XML and call PowerShell Script
    $temp_xml_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reprint_' . time() . '.xml';
    file_put_contents($temp_xml_path, $xml_content);

    $export_dir = __DIR__ . '/../exports/labels/';
    if (!is_dir($export_dir)) mkdir($export_dir, 0777, true);

    $final_odt_name = "Label_" . $id . "_" . preg_replace('/[^a-zA-Z0-9]/', '', $brand . $model) . ".odt";
    $final_odt_path = realpath($export_dir) . DIRECTORY_SEPARATOR . $final_odt_name;
    
    $master_template = realpath(__DIR__ . '/../templates/label_template.odt');
    $ps_script = realpath(__DIR__ . '/../templates/scripts/generate_odt.ps1');

    $cmd = 'powershell.exe -ExecutionPolicy Bypass -File "' . $ps_script . '" -SourceXML "' . $temp_xml_path . '" -OutputODT "' . $final_odt_path . '" -MasterTemplate "' . $master_template . '"';
    
    $exec_output = shell_exec($cmd);
    unlink($temp_xml_path);

    // 5. Response Logic (Download vs Direct Open)
    $mode = $_POST['mode'] ?? 'download';

    if (strpos($exec_output, 'SUCCESS') !== false) {
        
        // If mode is 'open', trigger Windows to launch the file directly
        if ($mode === 'open') {
            $open_cmd = 'powershell.exe -Command "Start-Process \'' . $final_odt_path . '\'"';
            shell_exec($open_cmd);
        }

        send_json_response(true, [
            'file_name' => $final_odt_name,
            'file_path' => 'exports/labels/' . $final_odt_name,
            'launched'  => ($mode === 'open')
        ]);
    } else {
        $clean_error = "ODT generation failed.";
        if (strpos($exec_output, 'ERROR:') !== false) {
            $clean_error = trim(substr($exec_output, strpos($exec_output, 'ERROR:') + 6));
        }
        throw new Exception($clean_error);
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
