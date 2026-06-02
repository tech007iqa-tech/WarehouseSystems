<?php
// orders/api/get_vocabulary.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';

try {
    $vocabulary = [
        'brands' => [],
        'models' => [],
        'cpus'   => []
    ];

    // 1. Fetch from Warehouse
    $conn_wh = Database::warehouse();

    $wh_brands = $conn_wh->query("SELECT DISTINCT brand FROM inventory WHERE brand != ''")->fetchAll(PDO::FETCH_COLUMN);
    $wh_models = $conn_wh->query("SELECT DISTINCT model FROM inventory WHERE model != ''")->fetchAll(PDO::FETCH_COLUMN);

    // For CPUs, we need to parse specs_json
    $wh_specs = $conn_wh->query("SELECT specs_json FROM inventory WHERE specs_json != ''")->fetchAll(PDO::FETCH_COLUMN);
    $wh_cpus = [];
    foreach ($wh_specs as $json) {
        $data = json_decode($json, true);
        if (!empty($data['cpu'])) $wh_cpus[] = $data['cpu'];
        if (!empty($data['cpu_gen'])) $wh_cpus[] = $data['cpu_gen'];
    }

    // 2. Fetch from Orders/Items (Historical)
    $conn_o = Database::orders();

    $o_brands = $conn_o->query("SELECT DISTINCT brand FROM items WHERE brand != ''")->fetchAll(PDO::FETCH_COLUMN);
    $o_models = $conn_o->query("SELECT DISTINCT model FROM items WHERE model != ''")->fetchAll(PDO::FETCH_COLUMN);
    $o_cpus   = $conn_o->query("SELECT DISTINCT cpu FROM items WHERE cpu != ''")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Merge and Unique
    $vocabulary['brands'] = array_values(array_unique(array_merge($wh_brands, $o_brands)));
    $vocabulary['models'] = array_values(array_unique(array_merge($wh_models, $o_models)));
    $vocabulary['cpus']   = array_values(array_unique(array_merge($wh_cpus, $o_cpus)));

    echo json_encode($vocabulary);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
