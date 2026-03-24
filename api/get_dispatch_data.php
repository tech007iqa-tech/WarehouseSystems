<?php
// api/get_dispatch_data.php
// GET ?q=&archive= : Fetches sale records from the Orders DB for the Dispatch Desk.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $q       = isset($_GET['q'])       ? trim($_GET['q'])       : '';
    $archive = isset($_GET['archive']) ? (int)$_GET['archive']  : 0;
    $status  = isset($_GET['status'])  ? trim($_GET['status'])  : '';

    // 1. Fetch Customers mapping for Buyer names
    $stmt_c = $pdo_rolodex->query("SELECT customer_id, company_name FROM customers");
    $customers = [];
    foreach ($stmt_c->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $customers[$c['customer_id']] = $c['company_name'];
    }

    // 2. Build the Orders query
    $conditions = ["o.invoice_status != 'Canceled'"];
    $params = [];

    if ($archive > 0) {
        $conditions[] = "o.order_date >= datetime('now', '-{$archive} days')";
    }

    if ($status !== '') {
        $conditions[] = "o.invoice_status = :status";
        $params[':status'] = $status;
    }

    if ($q !== '') {
        $keywords = explode(' ', $q);
        $i = 1;
        
        // --- PROACTIVE IMPROVEMENT: Multi-Table Search Pre-filter ---
        // Search across customer fields in rolodex.sqlite
        $matching_customer_ids = [];
        $stmt_matching = $pdo_rolodex->query("SELECT customer_id FROM customers");
        foreach ($customers as $cid => $cname) {
            // We'll also check other fields if needed, but since we already have the map:
            $match_found = false;
            foreach ($keywords as $word) {
                // Check name
                if (stripos($cname, $word) !== false) { $match_found = true; break; }
            }
            if ($match_found) $matching_customer_ids[] = (int)$cid;
        }

        foreach ($keywords as $word) {
            $word = trim($word);
            if ($word === '') continue;
            
            $paramKey = ":q" . $i;
            $search_term = '%' . $word . '%';
            
            // Smarter Order Number handling: "ORD-000123", "PO-123", "123"
            $numeric_q = 0;
            $clean_q = preg_replace('/[^0-9]/', '', $word);
            if (is_numeric($clean_q)) {
                $numeric_q = (int)$clean_q;
            }

            $customer_cond = "";
            if (!empty($matching_customer_ids)) {
                $customer_cond = " OR o.customer_id IN (" . implode(',', $matching_customer_ids) . ")";
            }

            // Include Invoice Status in search
            $conditions[] = "(i.brand LIKE $paramKey 
                              OR i.model LIKE $paramKey 
                              OR i.specs_blob LIKE $paramKey 
                              OR o.invoice_status LIKE $paramKey
                              OR CAST(o.order_number AS TEXT) LIKE $paramKey 
                              OR o.order_number = " . (int)$numeric_q . "
                              $customer_cond)";
            
            $params[$paramKey] = $search_term;
            $i++;
        }
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $pdo_orders->prepare("
        SELECT 
            i.line_id as id,
            i.order_number,
            i.item_id as original_item_id,
            i.brand,
            i.model,
            i.specs_blob,
            i.qty,
            i.unit_price as sale_price,
            o.customer_id,
            o.order_date as updated_at,
            o.invoice_status
        FROM order_items i
        JOIN purchase_orders o ON i.order_number = o.order_number
        {$where}
        ORDER BY o.order_date DESC
        LIMIT 250
    ");
    
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Post-process to add Buyer names and normalize fields for the template
    $results = [];
    foreach ($rows as $row) {
        $cid = $row['customer_id'];
        $row['buyer_name'] = $customers[$cid] ?? 'Unknown Buyer';
        $row['buyer_order_num'] = 'ORD-' . str_pad($row['order_number'], 6, '0', STR_PAD_LEFT);
        
        // Parse specs_blob back into individual fields if possible (for the template)
        // Format used in orders_api: "Series | CPU Gen | RAM | Storage | Description"
        $parts = explode(' | ', $row['specs_blob']);
        $row['series']      = $parts[0] ?? '';
        $row['cpu_gen']     = $parts[1] ?? '';
        $row['ram']         = $parts[2] ?? '';
        $row['storage']     = $parts[3] ?? '';
        $row['description'] = $parts[4] ?? '';
        
        $results[] = $row;
    }

    send_json_response(true, $results);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
