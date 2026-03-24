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

    $tier = $customer['tier'] ?? 'Bronze';
    $discount_rate = ($tier === 'Gold') ? 0.10 : (($tier === 'Silver') ? 0.05 : 0.0);
    $discount_amt  = $total_price * $discount_rate;
    $final_total   = $total_price - $discount_amt;

    // --- 2. INSERT ORDER INTO orders.sqlite (get order number first) ---
    $stmt_order = $pdo_orders->prepare("
        INSERT INTO purchase_orders (customer_id, total_qty, total_price, document_path, invoice_status)
        VALUES (:cid, :qty, :price, :doc_path, 'Active')
    ");
    $stmt_order->execute([
        ':cid'      => $customer_id,
        ':qty'      => $total_qty,
        ':price'    => $final_total,
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

    // Build only the TABLE fragment. PowerShell will surgically graft this into the master template.
    $xml_content = '
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
          . str_cell('SUBTOTAL')
          . empty_cell(1)
          . num_cell($total_price, '$' . number_format($total_price, 2)) . '
        </table:table-row>
        ' . ($discount_amt > 0 ? '
        <table:table-row>
          ' . empty_cell(8)
          . str_cell("DISCOUNT ($tier)")
          . empty_cell(1)
          . num_cell(-$discount_amt, '-$' . number_format($discount_amt, 2)) . '
        </table:table-row>' : '') . '
        <table:table-row>
          ' . empty_cell(8)
          . str_cell('TOTAL PRICE')
          . empty_cell(1)
          . num_cell($final_total, '$' . number_format($final_total, 2)) . '
        </table:table-row>

      </table:table>';

    // --- 4. VALIDATE XML BEFORE INJECTION ---
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    if (!$doc->loadXML("<root>$xml_content</root>")) {
        $errors = libxml_get_errors();
        $err_msg = "OTS XML Validation Error: ";
        foreach ($errors as $error) { $err_msg .= trim($error->message) . " "; }
        libxml_clear_errors();
        throw new Exception($err_msg);
    }

    // --- 5. WRITE TEMP XML AND CALL POWERSHELL ---
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

    // --- 6. LOGISTICAL SYNC (DISABLED: Labels are master templates and stay in the library) ---
    // Section removed per user request: Labels in labels.php should not be marked sold.
    // Sale data is archived in the Orders database.

    // --- 7. LOG THE AUDIT EVENT ---
    $order_summary = "Purchase Order created for $company ($order_num_pad). Items: $total_qty, Total: $" . number_format($final_total, 2);
    log_audit_event($pdo_audit, 'Order', $order_number, 'CREATED', $order_summary, null, $input);

    // --- 8. RETURN SUCCESS ---
    send_json_response(true, [
        'order_number' => $order_num_pad,
        'file_name'    => $final_ots_name,
        'file_path'    => $relative_doc_path,
        'total_qty'    => $total_qty,
        'total_price'  => '$' . number_format($final_total, 2)
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
