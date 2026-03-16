<?php
// includes/status_functions.php
// Advanced system health monitoring functions.

/**
 * Checks the physical integrity and size of the database files.
 */
function get_system_health($pdo_labels, $pdo_orders, $pdo_rolodex) {
    $health = [
        'status' => 'Healthy',
        'alerts' => [],
        'databases' => []
    ];

    $dbs = [
        'Labels'  => $pdo_labels,
        'Orders'  => $pdo_orders,
        'Rolodex' => $pdo_rolodex
    ];

    foreach ($dbs as $name => $pdo) {
        try {
            // Check SQLite Integrity
            $stmt = $pdo->query("PRAGMA integrity_check");
            $res = $stmt->fetchColumn();
            
            if ($res !== 'ok') {
                $health['status'] = 'Critical';
                $health['alerts'][] = "Database $name is corrupted: $res";
            }

            // Get some raw stats to prove it works
            $count = 0;
            if ($name === 'Labels') {
                $count = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
            } elseif ($name === 'Orders') {
                $count = $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
            } elseif ($name === 'Rolodex') {
                $count = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
            }

            $health['databases'][] = [
                'name' => $name,
                'integrity' => ($res === 'ok'),
                'records' => $count
            ];

        } catch (Exception $e) {
            $health['status'] = 'Critical';
            $health['alerts'][] = "Connection to $name DB failed completely.";
        }
    }

    return $health;
}
?>
