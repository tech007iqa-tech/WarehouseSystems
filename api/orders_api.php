<?php
// api/orders_api.php
// POST endpoint: Creates a purchase order from a JSON cart payload.
// 1. Validates input
// 2. Builds ODS content.xml
// 3. Calls PowerShell to inject XML into order_template.ots
// 4. Logs the order in orders.sqlite
// 5. Marks all sold items in labels.sqlite as 'Sold'
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    // --- 1. PARSE & VALIDATE JSON INPUT ---
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload.');
    }

    $customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
    $cart        = isset($input['cart']) && is_array($input['cart']) ? $input['cart'] : [];

    if ($customer_id <= 0) {
        throw new Exception('A customer must be selected.');
    }
    if (empty($cart)) {
        throw new Exception('The order cart is empty.');
    }

    // Validate and collect all item IDs and price
    $all_item_ids  = [];
    $total_qty     = 0;
    $total_price   = 0.0;
    $line_number   = 0;

    foreach ($cart as &$group) {
        if (empty($group['item_ids']) || !is_array($group['item_ids'])) {
            throw new Exception('A cart group has no item IDs.');
        }
        $unit_price = isset($group['unit_price']) ? (float)$group['unit_price'] : 0;
        if ($unit_price <= 0) {
            throw new Exception('All items must have a unit price greater than $0.');
        }
        
        // Use passed qty, or fallback to counting item_ids (backward compatibility)
        $qty = isset($group['qty']) ? (int)$group['qty'] : count($group['item_ids']);
        $group['qty']      = $qty;
        $group['subtotal'] = $unit_price * $qty;

        $total_qty   += $qty;
        $total_price += $group['subtotal'];

        foreach ($group['item_ids'] as $id) {
            $all_item_ids[] = (int)$id;
        }
        $line_number++;
    }
    unset($group);

    // Fetch customer data from rolodex for the document
    $stmt_cust = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :cid");
    $stmt_cust->execute([':cid' => $customer_id]);
    $customer = $stmt_cust->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        throw new Exception('Selected customer not found in Rolodex.');
    }

    // --- 2. INSERT ORDER INTO orders.sqlite (get order number first) ---
    $stmt_order = $pdo_orders->prepare("
        INSERT INTO purchase_orders (customer_id, total_qty, total_price, document_path)
        VALUES (:cid, :qty, :price, :doc_path)
    ");
    $stmt_order->execute([
        ':cid'      => $customer_id,
        ':qty'      => $total_qty,
        ':price'    => $total_price,
        ':doc_path' => '' // Filled in after file generation
    ]);
    $order_number = (int)$pdo_orders->lastInsertId();

    // --- 3. INSERT ORDER LINES INTO order_items ---
    $stmt_line = $pdo_orders->prepare("
        INSERT INTO order_items (order_number, item_id, brand, model, specs_blob, qty, unit_price, total_price)
        VALUES (:onum, :iid, :brand, :model, :specs, :qty, :upose, :tprice)
    ");

    foreach ($cart as $group) {
        // We use the first ID in the group as the template ID
        $template_id = (int)$group['item_ids'][0];
        $specs_blob = "{$group['series']} | {$group['cpu_gen']} | {$group['ram']} | {$group['storage']} | {$group['description']}";
        
        $stmt_line->execute([
            ':onum'   => $order_number,
            ':iid'    => $template_id,
            ':brand'  => $group['brand'],
            ':model'  => $group['model'],
            ':specs'  => $specs_blob,
            ':qty'    => $group['qty'],
            ':upose'  => $group['unit_price'],
            ':tprice' => $group['subtotal']
        ]);
    }

    // --- 4. BUILD THE ODS content.xml ---
    $order_date    = date('F j, Y');
    $order_num_pad = 'ORD-' . str_pad($order_number, 6, '0', STR_PAD_LEFT);
    $company       = $customer['company_name']   ?? 'N/A';
    $contact       = $customer['contact_person'] ?? '';
    $phone         = $customer['phone']          ?? '';
    $email         = $customer['email']          ?? '';

    // Helper to build a simple string cell
    function str_cell($val) {
        $safe = htmlspecialchars((string)$val, ENT_XML1, 'UTF-8');
        return "<table:table-cell office:value-type=\"string\"><text:p>{$safe}</text:p></table:table-cell>";
    }
    // Helper to build a numeric cell (shown as formatted currency string)
    function num_cell($val, $display = null) {
        $display = $display ?? '$' . number_format((float)$val, 2, '.', ',');
        $safe    = htmlspecialchars($display, ENT_XML1, 'UTF-8');
        return "<table:table-cell office:value-type=\"float\" office:value=\"{$val}\"><text:p>{$safe}</text:p></table:table-cell>";
    }
    // Empty cell
    function empty_cell($n = 1) {
        if ($n <= 1) return '<table:table-cell/>';
        return "<table:table-cell table:number-columns-repeated=\"{$n}\"/>";
    }

    // Build line item rows
    $line_rows_xml = '';
    $row_num = 0;
    foreach ($cart as $group) {
        $row_num++;
        $brand       = $group['brand']       ?? '';
        $model       = $group['model']       ?? '';
        $series      = $group['series']      ?? '';
        $cpu_gen     = $group['cpu_gen']     ?? '';
        $ram         = $group['ram']         ?? 'None';
        $storage     = $group['storage']     ?? 'None';
        $description = $group['description'] ?? '';
        $qty         = (int)$group['qty'];
        $unit_price  = (float)$group['unit_price'];
        $subtotal    = (float)$group['subtotal'];

        $line_rows_xml .= '<table:table-row>'
            . str_cell($row_num)
            . str_cell($brand)
            . str_cell($model)
            . str_cell($series)
            . str_cell($cpu_gen)
            . str_cell($ram)
            . str_cell($storage)
            . str_cell($description)
            . num_cell($qty, $qty)
            . num_cell($unit_price, '$' . number_format($unit_price, 2))
            . num_cell($subtotal,   '$' . number_format($subtotal,   2))
            . '</table:table-row>';
    }

    $xml_content = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content 
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" 
    xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" 
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" 
    xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" 
    xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" 
    xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" 
    xmlns:xlink="http://www.w3.org/1999/xlink" 
    xmlns:dc="http://purl.org/dc/elements/1.1/" 
    xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" 
    xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0" 
    xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" 
    xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0" 
    xmlns:dr3d="urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0" 
    xmlns:math="http://www.w3.org/1998/Math/MathML" 
    xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0" 
    xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0" 
    xmlns:ooo="http://openoffice.org/2004/office" 
    xmlns:ooow="http://openoffice.org/2004/writer" 
    xmlns:oooc="http://openoffice.org/2004/calc" 
    xmlns:dom="http://www.w3.org/2001/xml-events" 
    xmlns:xforms="http://www.w3.org/2002/xforms" 
    xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xmlns:rpt="http://openoffice.org/2005/report" 
    xmlns:of="urn:oasis:names:tc:opendocument:xmlns:of:1.2" 
    xmlns:xhtml="http://www.w3.org/1999/xhtml" 
    xmlns:grddl="http://www.w3.org/2003/g/data-view#" 
    xmlns:officeooo="http://openoffice.org/2009/office" 
    xmlns:tableooo="http://openoffice.org/2009/table" 
    xmlns:drawooo="http://openoffice.org/2010/draw" 
    xmlns:calcext="http://openoffice.org/2009/calc" 
    xmlns:loext="http://www.libreoffice.org/2017/content-optimise" 
    xmlns:field="urn:openoffice:names:experimental:ooo-ms-interop:xmlns:field:1.0" 
    office:version="1.2">
  <office:scripts/>
  <office:font-face-decls/>
  <office:automatic-styles></office:automatic-styles>
  <office:body>
    <office:spreadsheet>
      <table:table table:name="Purchase Order">

        <!-- ===== TITLE ROW ===== -->
        <table:table-row>
          <table:table-cell office:value-type="string">
            <text:p>IQA METAL - B2B PURCHASE ORDER</text:p>
          </table:table-cell>
          ' . empty_cell(10) . '
        </table:table-row>

        <!-- ===== BLANK ===== -->
        <table:table-row><table:table-cell/></table:table-row>

        <!-- ===== ORDER META ===== -->
        <table:table-row>
          ' . str_cell('Order #:') . str_cell($order_num_pad) . empty_cell(6) . str_cell('Company:') . str_cell($company) . empty_cell(1) . '
        </table:table-row>
        <table:table-row>
          ' . str_cell('Date:') . str_cell($order_date) . empty_cell(6) . str_cell('Contact:') . str_cell($contact) . empty_cell(1) . '
        </table:table-row>
        <table:table-row>
          ' . empty_cell(8) . str_cell('Phone:') . str_cell($phone) . empty_cell(1) . '
        </table:table-row>
        <table:table-row>
          ' . empty_cell(8) . str_cell('Email:') . str_cell($email) . empty_cell(1) . '
        </table:table-row>

        <!-- ===== BLANK ===== -->
        <table:table-row><table:table-cell/></table:table-row>

        <!-- ===== COLUMN HEADERS ===== -->
        <table:table-row>
          ' . str_cell('#')
          . str_cell('Brand')
          . str_cell('Model')
          . str_cell('Series')
          . str_cell('CPU / Gen')
          . str_cell('RAM')
          . str_cell('Storage')
          . str_cell('Condition')
          . str_cell('Qty')
          . str_cell('Unit Price')
          . str_cell('Subtotal') . '
        </table:table-row>

        <!-- ===== LINE ITEMS ===== -->
        ' . $line_rows_xml . '

        <!-- ===== BLANK ===== -->
        <table:table-row><table:table-cell/></table:table-row>

        <!-- ===== TOTALS ===== -->
        <table:table-row>
          ' . empty_cell(8)
          . str_cell('TOTAL QTY')
          . num_cell($total_qty, $total_qty)
          . empty_cell(1) . '
        </table:table-row>
        <table:table-row>
          ' . empty_cell(8)
          . str_cell('TOTAL PRICE')
          . empty_cell(1)
          . num_cell($total_price, '$' . number_format($total_price, 2)) . '
        </table:table-row>

      </table:table>
    </office:spreadsheet>
  </office:body>
</office:document-content>';

    // --- 4. WRITE TEMP XML AND CALL POWERSHELL ---
    // Ensure exports/orders directory exists
    $exports_dir = realpath(__DIR__ . '/../exports/orders/');
    if (!$exports_dir) {
        mkdir(__DIR__ . '/../exports/orders/', 0777, true);
        $exports_dir = realpath(__DIR__ . '/../exports/orders/');
    }

    $temp_xml_path    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'order_' . $order_number . '_' . time() . '.xml';
    $final_ots_name   = 'Order_' . $order_num_pad . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $company) . '.ots';
    $final_ots_path   = $exports_dir . DIRECTORY_SEPARATOR . $final_ots_name;
    $master_template  = realpath(__DIR__ . '/../templates/order_template.ots');
    $ps_script        = realpath(__DIR__ . '/../templates/scripts/generate_ots.ps1');

    file_put_contents($temp_xml_path, $xml_content);

    $cmd = 'powershell.exe -ExecutionPolicy Bypass -File "' . $ps_script . '" '
         . '-SourceXML "' . $temp_xml_path . '" '
         . '-OutputOTS "' . $final_ots_path . '" '
         . '-MasterTemplate "' . $master_template . '"';

    $exec_output = shell_exec($cmd);

    // Clean up temp XML
    if (file_exists($temp_xml_path)) {
        unlink($temp_xml_path);
    }

    if (strpos($exec_output, 'SUCCESS') === false) {
        $clean_err = 'OTS file generation failed.';
        if (strpos($exec_output, 'ERROR:') !== false) {
            $clean_err = trim(substr($exec_output, strpos($exec_output, 'ERROR:') + 6));
        }
        throw new Exception($clean_err);
    }

    // --- 5. UPDATE ORDER RECORD WITH FILE PATH ---
    $relative_doc_path = 'exports/orders/' . $final_ots_name;
    $stmt_update = $pdo_orders->prepare("
        UPDATE purchase_orders SET document_path = :doc WHERE order_number = :num
    ");
    $stmt_update->execute([':doc' => $relative_doc_path, ':num' => $order_number]);

    // --- 7. RETURN SUCCESS ---
    send_json_response(true, [
        'order_number' => $order_num_pad,
        'file_name'    => $final_ots_name,
        'file_path'    => $relative_doc_path,
        'total_qty'    => $total_qty,
        'total_price'  => '$' . number_format($total_price, 2)
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
