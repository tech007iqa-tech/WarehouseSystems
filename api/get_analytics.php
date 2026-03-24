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
    $stmt = $pdo_orders->query("
        SELECT strftime('%Y-%m', order_date) as month, 
               SUM(total_price) as total_revenue, 
               SUM(total_qty) as units_sold
        FROM purchase_orders 
        WHERE invoice_status != 'Canceled'
        GROUP BY month 
        ORDER BY month DESC 
        LIMIT 6
    ");
    $data['sales_performance'] = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // 4. B2B Tier Distribution & Top Buyers (Decoupled Logic)
    
    // Fetch all current orders and their customer info in PHP
    $stmt_orders = $pdo_orders->query("SELECT customer_id, total_price, invoice_status FROM purchase_orders WHERE invoice_status != 'Canceled'");
    $orders_list = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all customers for mapping
    $stmt_customers = $pdo_rolodex->query("SELECT customer_id, company_name, tier FROM customers");
    $customer_lookup = [];
    foreach ($stmt_customers->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $customer_lookup[$c['customer_id']] = $c;
    }

    $tier_scores  = ['Gold' => 0, 'Silver' => 0, 'Bronze' => 0];
    $buyer_scores = [];

    foreach ($orders_list as $o) {
        $cid = $o['customer_id'];
        if (!isset($customer_lookup[$cid])) continue;

        $tier = $customer_lookup[$cid]['tier'];
        $comp = $customer_lookup[$cid]['company_name'];
        $val  = (float)$o['total_price'];

        if (isset($tier_scores[$tier])) {
            $tier_scores[$tier] += $val;
        } else {
            $tier_scores[$tier] = $val;
        }

        if (!isset($buyer_scores[$comp])) $buyer_scores[$comp] = 0;
        $buyer_scores[$comp] += $val;
    }

    // Format for Frontend
    arsort($tier_scores);
    foreach ($tier_scores as $t => $total) {
        $data['tier_distribution'][] = ['label' => $t, 'count' => $total];
    }

    arsort($buyer_scores);
    $top_buyers = array_slice($buyer_scores, 0, 3, true);
    foreach ($top_buyers as $b => $total) {
        $data['top_buyers'][] = ['label' => $b, 'count' => $total];
    }

    // 5. Aging Inventory (Oldest 5 items in Warehouse)
    $stmt = $pdo_labels->query("
        SELECT id, brand, model, description, created_at,
               (strftime('%s', 'now') - strftime('%s', created_at)) / 86400 as days_old
        FROM items 
        WHERE status = 'In Warehouse'
        ORDER BY created_at ASC 
        LIMIT 5
    ");
    $data['aging_inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. General Summary Stats
    $stmt = $pdo_labels->query("SELECT COUNT(*) FROM items WHERE status = 'In Warehouse'");
    $data['summary']['total_stock'] = $stmt->fetchColumn();

    // Ready to Dispatch = Sold but NOT yet Dispatched or Canceled
    $stmt = $pdo_orders->query("SELECT SUM(total_qty) FROM purchase_orders WHERE invoice_status NOT IN ('Canceled', 'Dispatched')");
    $data['summary']['sold_stock'] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo_orders->query("SELECT SUM(total_price) FROM purchase_orders WHERE invoice_status != 'Canceled' AND strftime('%Y-%m', order_date) = strftime('%Y-%m', 'now')");
    $data['summary']['this_month_sales'] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo_labels->query("SELECT AVG((strftime('%s', 'now') - strftime('%s', created_at)) / 86400) FROM items WHERE status = 'In Warehouse'");
    $data['summary']['avg_stock_days'] = round($stmt->fetchColumn() ?: 0);

    send_json_response(true, $data);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
