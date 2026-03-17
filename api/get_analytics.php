<?php
// api/get_analytics.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $data = [
        'inventory_stats' => [],
        'sales_performance' => [],
        'brand_distribution' => [],
        'aging_inventory' => []
    ];

    // 1. Inventory Condition Breakdown
    $stmt = $pdo_labels->query("
        SELECT description as label, COUNT(*) as count 
        FROM items 
        WHERE status = 'In Warehouse' 
        GROUP BY description
    ");
    $data['inventory_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Brand Distribution (Top 10)
    $stmt = $pdo_labels->query("
        SELECT brand as label, COUNT(*) as count 
        FROM items 
        WHERE status = 'In Warehouse' 
        GROUP BY brand 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $data['brand_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Sales over time (Last 6 Months)
    // Note: SQLite date functions are used here
    $stmt = $pdo_orders->query("
        SELECT strftime('%Y-%m', order_date) as month, 
               SUM(total_price) as total_revenue, 
               SUM(total_qty) as units_sold
        FROM purchase_orders 
        GROUP BY month 
        ORDER BY month DESC 
        LIMIT 6
    ");
    $data['sales_performance'] = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // 4. Aging Inventory (Oldest 5 items in Warehouse)
    $stmt = $pdo_labels->query("
        SELECT id, brand, model, description, created_at,
               (strftime('%s', 'now') - strftime('%s', created_at)) / 86400 as days_old
        FROM items 
        WHERE status = 'In Warehouse'
        ORDER BY created_at ASC 
        LIMIT 5
    ");
    $data['aging_inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. General Summary Stats
    $stmt = $pdo_labels->query("SELECT COUNT(*) FROM items WHERE status = 'In Warehouse'");
    $data['summary']['total_stock'] = $stmt->fetchColumn();

    $stmt = $pdo_orders->query("SELECT SUM(total_price) FROM purchase_orders WHERE strftime('%Y-%m', order_date) = strftime('%Y-%m', 'now')");
    $data['summary']['this_month_sales'] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo_labels->query("SELECT AVG((strftime('%s', 'now') - strftime('%s', created_at)) / 86400) FROM items WHERE status = 'In Warehouse'");
    $data['summary']['avg_stock_days'] = round($stmt->fetchColumn() ?: 0);

    send_json_response(true, $data);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
