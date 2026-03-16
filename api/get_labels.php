<?php
// api/get_labels.php
// GET ?q=&status= : Filtered inventory search used by the labels.php filter bar.
// Supports all status values ('In Warehouse', 'Sold') or 'all'.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

    $params      = [];
    $conditions  = [];

    // Status filter
    if ($status !== 'all' && $status !== '') {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }

    // Text search filter (Multi-keyword "Flexible Search")
    if ($q !== '') {
        $keywords = explode(' ', $q);
        $i = 1;
        foreach ($keywords as $word) {
            $word = trim($word);
            if ($word === '') continue;

            $paramKey = ":q" . $i;
            $search_term = '%' . $word . '%';
            
            $conditions[] = "(brand LIKE $paramKey OR model LIKE $paramKey OR series LIKE $paramKey
                              OR cpu_gen LIKE $paramKey OR cpu_specs LIKE $paramKey 
                              OR cpu_cores LIKE $paramKey OR cpu_speed LIKE $paramKey
                              OR description LIKE $paramKey OR warehouse_location LIKE $paramKey
                              OR CAST(id AS TEXT) LIKE $paramKey)";
            
            $params[$paramKey] = $search_term;
            $i++;
        }
    }

    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $pdo_labels->prepare("
        SELECT * FROM items
        {$where_clause}
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_json_response(true, $items);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
