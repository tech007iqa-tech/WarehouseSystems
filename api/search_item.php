<?php
// api/search_item.php
// GET ?id=N : Looks up a single item by ID from labels.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

try {
    $F = HW_FIELDS;
    $raw_query = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($raw_query === '') {
        throw new Exception('Search query is required.');
    }

    $item = null;
    $results = [];

    // 1. Try EXACT numeric ID lookup first (Deep lookup with order info)
    if (is_numeric($raw_query)) {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => (int)$raw_query]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. If NO direct ID match found, or if query contains spaces/text, do a FLEXIBLE keyword search
    if (!$item) {
        $keywords = explode(' ', $raw_query);
        $conditions = [];
        $params = [];
        $i = 1;
        foreach ($keywords as $word) {
            $word = trim($word);
            if ($word === '') continue;
            $paramKey = ":q" . $i;
            $conditions[] = "({$F['BRAND']} LIKE $paramKey OR {$F['MODEL']} LIKE $paramKey OR {$F['SERIES']} LIKE $paramKey 
                              OR {$F['CPU_GEN']} LIKE $paramKey OR {$F['CPU_SPECS']} LIKE $paramKey 
                              OR {$F['CPU_CORES']} LIKE $paramKey OR {$F['CPU_SPEED']} LIKE $paramKey
                              OR {$F['DESCRIPTION']} LIKE $paramKey OR {$F['LOCATION']} LIKE $paramKey 
                              OR CAST(id AS TEXT) LIKE $paramKey)";
            $params[$paramKey] = '%' . $word . '%';
            $i++;
        }
        
        if (!empty($conditions)) {
            $where = "WHERE " . implode(' AND ', $conditions);
            $stmt = $pdo_labels->prepare("SELECT * FROM items $where ORDER BY created_at DESC LIMIT 5");
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // We have a single ID match, put it in the results format for consistent processing
        $results = [$item];
    }

    if (empty($results)) {
        throw new Exception('No matching hardware found.');
    }

    // 3. Enrich the FIRST/PRIMARY result with order data (Dashboard experience)
    $primary_item = $results[0];
    $order_info = null;
    
    // Only fetch deep order info for the first result to keep it fast
    if (($primary_item[$F['STATUS']] ?? '') === 'Sold' && ($primary_item['order_id'] ?? null)) {
        $stmt_order = $pdo_orders->prepare("SELECT * FROM purchase_orders WHERE order_number = :num");
        $stmt_order->execute([':num' => $primary_item['order_id']]);
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $stmt_cust = $pdo_rolodex->prepare("SELECT company_name, contact_person FROM customers WHERE customer_id = :cid");
            $stmt_cust->execute([':cid' => $order['customer_id']]);
            $customer = $stmt_cust->fetch(PDO::FETCH_ASSOC);
            $order_info = [
                'order_number'   => 'ORD-' . str_pad($order['order_number'], 6, '0', STR_PAD_LEFT),
                'order_date'     => $order['order_date'],
                'company_name'   => $customer['company_name']   ?? 'Unknown',
                'contact_person' => $customer['contact_person'] ?? '',
                'document_path'  => $order['document_path']     ?? null,
            ];
        }
    }

    send_json_response(true, [
        'results'     => $results,
        'order_info'  => $order_info, // Only for the top result
        'is_single'   => (count($results) === 1)
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>

