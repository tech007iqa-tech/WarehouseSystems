<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::customers();
    $conn_orders = Database::orders();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            die("Security Error: CSRF Token Invalid.");
        }

        if (isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
        $stmt = $conn->prepare("UPDATE customers SET
            company_name = ?, website = ?, contact_person = ?, address = ?,
            email = ?, phone = ?, shipping_address = ?, internal_notes = ?,
            callback_date = ?, message_date = ?
            WHERE customer_id = ?");
        $stmt->execute([
            $_POST['company_name'], $_POST['website'], $_POST['contact_person'], $_POST['address'],
            $_POST['email'], $_POST['phone'], $_POST['shipping_address'], $_POST['internal_notes'],
            $_POST['callback_date'] ?? '', $_POST['message_date'] ?? '',
            $_POST['customer_id']
        ]);
        header("Location: index.php");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
        $cid = $_POST['customer_id'];

        // 1. Delete items associated with customer's orders
        $stmt_ids = $conn_orders->prepare("SELECT order_id FROM orders WHERE customer_id = ?");
        $stmt_ids->execute([$cid]);
        $order_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $stmt_del_items = $conn_orders->prepare("DELETE FROM items WHERE order_id IN ($placeholders)");
            $stmt_del_items->execute($order_ids);
        }

        // 2. Delete the orders themselves
        $stmt_del_orders = $conn_orders->prepare("DELETE FROM orders WHERE customer_id = ?");
        $stmt_del_orders->execute([$cid]);

        // 3. Delete the customer record
        $stmt_del_cust = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt_del_cust->execute([$cid]);

        header("Location: index.php?msg=customer_deleted");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
        $oid = $_POST['order_id'];

        // 1. Delete items from the order
        $stmt_del_items = $conn_orders->prepare("DELETE FROM items WHERE order_id = ?");
        $stmt_del_items->execute([$oid]);

        // 2. Delete the order itself
        $stmt_del_orders = $conn_orders->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt_del_orders->execute([$oid]);

        header("Location: index.php?msg=order_deleted");
        exit();
    }
} // End POST handler

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>

<script id="crm-state" type="application/json">
    <?= json_encode(['csrf_token' => Security::getToken()]) ?>
</script>

<!-- Load dedicated registry styles -->
<style>
    .dashboard-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: default;
    }
    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1) !important;
    }
</style>

<div class="form-side">
    <?php
    // Dashboard Logic: Fetch high-level KPIs
    $pipeline_data  = ['Daily' => 0, 'Weekly' => 0, 'Monthly' => 0, 'Yearly' => 0];
    $pipeline_orders = ['Daily' => [], 'Weekly' => [], 'Monthly' => [], 'Yearly' => []];
    $active_batches_count = 0;
    $pending_callbacks_count = 0;
    $warehouse_audit_count = 0;

    try {
        // Per-period date boundaries
        // Monday of the current business week (ISO: N=1 Mon … 7 Sun)
        $days_since_monday = (int) date('N') - 1;   // 0 on Mon, 4 on Fri, 6 on Sun
        $this_monday = date('Y-m-d', strtotime("-{$days_since_monday} days"));

        $periods = [
            'Daily'  => "date('now', 'localtime')",
            'Weekly' => "'{$this_monday}'",           // Mon of current work-week
            'Monthly'=> "date('now', 'start of month', 'localtime')",
            'Yearly' => "date('now', 'start of year', 'localtime')",
        ];

        // Completed order statuses — matches the rest of the app (orders.php, customer_registry.php)
        $done_statuses = "'paid', 'finalized', 'dispatched'";

        // Attach customers DB so we can pull company names in one query
        Database::attach($conn_orders, 'customers', 'cust_db');

        foreach ($periods as $label => $since) {
            // Aggregate value — use created_at (updated_at is NULL on many rows)
            $pipeline_data[$label] = $conn_orders->query(
                "SELECT COALESCE(SUM(i.quantity * i.unit_price), 0)
                 FROM items i
                 JOIN orders o ON i.order_id = o.order_id
                 WHERE o.status IN ({$done_statuses})
                   AND o.created_at >= {$since}"
            )->fetchColumn() ?: 0;

            // Per-order breakdown (latest 30)
            $rows = $conn_orders->query(
                "SELECT o.order_id,
                        o.customer_id,
                        o.status,
                        o.created_at,
                        COALESCE(c.company_name, o.customer_id) AS company_name,
                        COALESCE(SUM(i.quantity * i.unit_price), 0) AS order_value,
                        COALESCE(SUM(i.quantity), 0) AS total_qty
                 FROM orders o
                 LEFT JOIN items i ON i.order_id = o.order_id
                 LEFT JOIN cust_db.customers c ON c.customer_id = o.customer_id
                 WHERE o.status IN ({$done_statuses})
                   AND o.created_at >= {$since}
                 GROUP BY o.order_id
                 ORDER BY o.created_at DESC
                 LIMIT 30"
            )->fetchAll(PDO::FETCH_ASSOC);

            $pipeline_orders[$label] = $rows;
        }

        // 2. Active Batches
        $active_batches_count = $conn_orders->query("SELECT COUNT(*) FROM orders WHERE status = 'active'")->fetchColumn();

        // 3. Pending Callbacks
        $pending_callbacks_count = $conn->prepare("SELECT COUNT(*) FROM customers WHERE callback_date != '' AND callback_date <= ? AND account_status = 'Lead'");
        $pending_callbacks_count->execute([date('Y-m-d')]);
        $pending_callbacks_count = $pending_callbacks_count->fetchColumn();

        // 4. Warehouse Audits
        $conn_w = Database::warehouse();
        $warehouse_audit_count = $conn_w->query("SELECT COUNT(*) FROM locations WHERE status IN ('Audit', 'Idle')")->fetchColumn();
    } catch (Exception $e) {}
    ?>

    <?php
    // Embed order summaries as JSON for JS consumption
    $pipeline_json = json_encode([
        'values' => array_map(fn($v) => '$' . number_format($v, 0), $pipeline_data),
        'orders' => $pipeline_orders,
    ], JSON_HEX_TAG);
    ?>
    <script id="pipeline-data" type="application/json"><?= $pipeline_json ?></script>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <!-- Pipeline card spans full width so the order list has room -->
        <div class="dashboard-card pipeline-card" id="pipeline-card"
             style="background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); position: relative; grid-column: 1 / -1;">

            <!-- Top row: label + active period badge -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">💰 Pipeline</div>
                <div id="pipeline-period-badge"
                     style="font-size: 0.65rem; font-weight: 800; background: #f0f9ff; color: #0369a1; padding: 3px 8px; border-radius: 5px; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.2s; border: 1px solid #e0f2fe; display: flex; align-items: center; gap: 4px;"
                     onmouseover="this.style.background='#e0f2fe'; this.style.borderColor='#bae6fd';"
                     onmouseout="this.style.background='#f0f9ff'; this.style.borderColor='#e0f2fe';"
                     title="Click to view details">
                     Monthly <span style="font-size: 0.55rem; opacity: 0.7;">🔍</span>
                </div>
            </div>

            <!-- Value + order count on same line -->
            <div style="display: flex; align-items: baseline; gap: 12px; margin-bottom: 12px;">
                <div id="pipeline-value" style="font-size: 1.5rem; font-weight: 900; color: var(--accent-color); transition: opacity 0.2s;">$0</div>
                <div id="pipeline-count" style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);"></div>
            </div>

            <!-- D / W / M / Y toggle buttons -->
            <div class="pipeline-controls" style="display: flex; gap: 6px; margin-bottom: 14px;">
                <?php foreach(['Daily' => 'D', 'Weekly' => 'W', 'Monthly' => 'M', 'Yearly' => 'Y'] as $period => $label): ?>
                    <button type="button"
                            onclick="setPipelinePeriod('<?= $period ?>')"
                            class="pipeline-btn"
                            id="pipbtn-<?= $period ?>"
                            data-period="<?= $period ?>"
                            style="width: 36px; height: 28px; font-size: 0.7rem; font-weight: 800;
                                   border: 1px solid #e2e8f0; border-radius: 6px; background: white;
                                   cursor: pointer; transition: all 0.18s; color: #64748b;">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Note: Click the period badge above to expand/view detailed orders list -->
        </div>

        <?= UI::stat_card("Active Batches", $active_batches_count) ?>
        <?= UI::stat_card("Callbacks", $pending_callbacks_count, $pending_callbacks_count > 0 ? 'text-danger' : '') ?>
        <?= UI::stat_card("Zone Alerts", $warehouse_audit_count, $warehouse_audit_count > 5 ? 'text-warning' : '') ?>
    </div>



    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'customer_deleted'): ?>
        <div id="status-msg" style="background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; border: 1px solid #fecdd3; display: flex; justify-content: space-between; align-items: center;">
            <span>🗑️ Customer and all associated orders have been permanently removed.</span>
            <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#991b1b; cursor:pointer; font-weight:900;">✕</button>
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'order_deleted'): ?>
        <div id="status-msg" style="background: #fffbeb; color: #92400e; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; border: 1px solid #fde68a; display: flex; justify-content: space-between; align-items: center;">
            <span>🗑️ The specific order and its items have been successfully deleted.</span>
            <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#92400e; cursor:pointer; font-weight:900;">✕</button>
        </div>
    <?php endif; ?>

    <div class="registry-actions" style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
        <a href="index.php?view=register" class="btn-register" style="margin:0;">+ Register New Customer</a>

        <div class="sort-wrapper" style="display: flex; align-items: center; gap: 10px;">
            <label for="cust-sort" style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">Sort By:</label>
            <select id="cust-sort" onchange="window.location.href='index.php?sort=' + this.value" style="height: 42px; border-radius: 12px; border: 1px solid #ddd; padding: 0 15px; font-weight: 700; background: white; cursor: pointer;">
                <?php
                    $current_sort = $_GET['sort'] ?? $_SESSION['cust_sort_pref'] ?? 'date_desc';
                    $options = [
                        'date_desc' => '📅 Date (Recent First)',
                        'date_asc'  => '📅 Date (Oldest First)',
                        'name'      => '🔤 Name (A-Z)',
                        'orders'    => '📦 Total Orders',
                        'spent'     => '💰 Amount Spent'
                    ];
                    foreach ($options as $val => $label) {
                        $selected = ($current_sort === $val) ? 'selected' : '';
                        echo "<option value='{$val}' {$selected}>{$label}</option>";
                    }
                ?>
            </select>
        </div>
    </div>

    <div class="search-wrapper">
        <i class="search-icon">🔍</i>
        <input type="text" id="cust-search" placeholder="Search by name or ID..." onkeyup="filterCustomers()">
    </div>

    <div class="registry-list" id="customer-list">
        <?php
        // Persist sort preference in session
        if (isset($_GET['sort'])) {
            $_SESSION['cust_sort_pref'] = $_GET['sort'];
        }
        $sort_param = $_GET['sort'] ?? $_SESSION['cust_sort_pref'] ?? 'date_desc';

        // Fetch All Customers with Aggregated Order Data using Integrated Query
        try {
            $sql = "
                SELECT c.*,
                    COALESCE(o_stats.total_orders, 0) as total_orders,
                    COALESCE(i_stats.lifetime_value, 0) as lifetime_value,
                    COALESCE(o_stats.last_order_date, '0000-00-00 00:00:00') as last_order_date,
                    COALESCE(o_stats.completed_count, 0) as completed_count,
                    COALESCE(o_stats.active_count, 0) as active_count
                FROM customers c
                LEFT JOIN (
                    SELECT customer_id,
                           COUNT(*) as total_orders,
                           MAX(created_at) as last_order_date,
                           SUM(CASE WHEN status IN ('finalized', 'paid', 'dispatched', 'canceled') THEN 1 ELSE 0 END) as completed_count,
                           SUM(CASE WHEN status NOT IN ('finalized', 'paid', 'dispatched', 'canceled') THEN 1 ELSE 0 END) as active_count
                    FROM orders_db.orders
                    GROUP BY customer_id
                ) o_stats ON c.customer_id = o_stats.customer_id
                LEFT JOIN (
                    SELECT customer_id, SUM(quantity * unit_price) as lifetime_value
                    FROM orders_db.items
                    GROUP BY customer_id
                ) i_stats ON c.customer_id = i_stats.customer_id
            ";

            $stmt = Database::queryIntegrated('customers', ['orders_db' => 'orders'], $sql);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            // Fallback if integrated query fails
            $stmt = $conn->query("SELECT *, 0 as total_orders, 0 as lifetime_value, '0000-00-00 00:00:00' as last_order_date, 0 as completed_count, 0 as active_count FROM customers");
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch Detailed Orders List for all customers in one go
        $order_map = [];
        try {
            // Re-use attached orders_db or query it directly if not attached
            $sql_orders = "
                SELECT o.*,
                       (SELECT SUM(quantity) FROM orders_db.items WHERE order_id = o.order_id) as total_qty,
                       (SELECT SUM(quantity * unit_price) FROM orders_db.items WHERE order_id = o.order_id) as total_value
                FROM orders_db.orders o
                ORDER BY o.created_at DESC
            ";
            $stmt_o = $conn->query($sql_orders);
            while ($o = $stmt_o->fetch(PDO::FETCH_ASSOC)) {
                $order_map[$o['customer_id']][] = $o;
            }
        } catch (Exception $e) {
            // Silently fail if orders DB is not ready
        }

        // Attach orders to customers
        foreach ($customers as &$c) {
            $c['orders_list'] = $order_map[$c['customer_id']] ?? [];
        }
        unset($c); // Clean up reference

        // Advanced Sorting Logic
        usort($customers, function($a, $b) use ($sort_param) {
            switch ($sort_param) {
                case 'name':
                    return strcasecmp($a['company_name'], $b['company_name']);
                case 'orders':
                    return $b['total_orders'] <=> $a['total_orders'];
                case 'spent':
                    return $b['lifetime_value'] <=> $a['lifetime_value'];
                case 'date_desc':
                    return strcmp($b['last_order_date'], $a['last_order_date']);
                case 'date_asc':
                default:
                    // If no orders, push to end (or beginning depending on interpretation)
                    if ($a['last_order_date'] === '0000-00-00 00:00:00') return 1;
                    if ($b['last_order_date'] === '0000-00-00 00:00:00') return -1;
                    return strcmp($a['last_order_date'], $b['last_order_date']);
            }
        });

        if (count($customers) > 0) {
            foreach($customers as $c) {
                $initial = strtoupper(substr($c['company_name'], 0, 1));
                $json_data = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                $status_class = $c['active_count'] > 0 ? 'status-active' : 'status-idle';
                $status_text = $c['active_count'] > 0 ? 'Active Batch' : 'Idle';

                echo "<div class='cust-card' onclick='showDetails(this)' data-customer='{$json_data}' data-search='" . htmlspecialchars($c['company_name'] . " " . $c['customer_id']) . "'>
                        <div class='cust-avatar'>{$initial}</div>
                        <div class='cust-main'>
                             <div class='cust-name' onclick='showProfile(event, {$json_data})'>" . htmlspecialchars($c['company_name']) . "</div>
                            <div class='cust-id'>" . htmlspecialchars($c['customer_id']) . " " . (!empty($c['contact_person']) ? "• 👤 " . htmlspecialchars($c['contact_person']) : "") . "</div>
                            <div class='cust-meta-row'>
                                <span class='badge badge-completed'>{$c['completed_count']} Orders</span>
                                <span class='badge status-active' style='background:#f0f9ff; color:#0369a1;'>💰 $" . number_format($c['lifetime_value'], 0) . "</span>
                                <span class='badge status-idle'>Last Order: " . (!empty($c['orders_list']) ? date('M d, Y', strtotime($c['orders_list'][0]['created_at'])) : 'None') . "</span>
                            </div>
                            <div class='crm-summary-row' style='display:flex; gap:25px; margin-top:10px; padding-top:8px; border-top:1px solid #f1f5f9;'>
                                <div class='crm-stat'>
                                    <div style='font-size:0.6rem; font-weight:800; color:#94a3b8; text-transform:uppercase;'>📅 Next Callback</div>
                                    <div style='font-size:0.75rem; font-weight:700; color:" . (!empty($c['callback_date']) ? "#be123c" : "#64748b") . ";'>" . (!empty($c['callback_date']) ? htmlspecialchars($c['callback_date']) : "Not Set") . "</div>
                                </div>
                                <div class='crm-stat'>
                                    <div style='font-size:0.6rem; font-weight:800; color:#94a3b8; text-transform:uppercase;'>✉️ Last Contact</div>
                                    <div style='font-size:0.75rem; font-weight:700; color:#64748b;'>" . (!empty($c['message_date']) ? htmlspecialchars($c['message_date']) : "Not Set") . "</div>
                                </div>
                            </div>
                        </div>
                        <div class='card-actions'>
                            <a href=\"#customer-details\"><div class='btn-view-cust' title='View Details'>👁</div></a>
                        </div>
                      </div>";
            }
        } else {
            echo "<div class='empty-state' style='padding: 40px;'>No customers registered yet.</div>";
        }
        ?>
        <!-- Pipeline logic moved to external assets/js/pipeline.js -->
    </div>
 </div>
<div class="summary-side" id="customer-details">
    <section class="item-list">
        <h2>Customer Details</h2>
        <div id="side-details">
            <div class="empty-state" style='padding: 60px;'>
                Select a customer on the left to see full details.
            </div>
        </div>
    </section>
</div>

<!-- Profile Modal -->
<div id="profile-modal" class="modal-overlay" onclick="closeProfile()">
    <div class="profile-modal-box" onclick="event.stopPropagation()">
        <button class="close-btn" onclick="closeProfile()">✖</button>
        <div id="profile-content">
            <!-- Dynamically populated -->
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="import-modal" class="modal-overlay" style="overflow-y: auto; align-items: flex-start; padding: 20px 10px;" onclick="closeImportModal()">
    <div class="profile-modal-box" onclick="event.stopPropagation()" style="max-width: 800px; width: 95%; margin: auto;">
        <button class="close-btn" onclick="closeImportModal()">✖</button>
        <div style="padding: 20px;">
            <h2 style="font-weight: 900; margin-bottom: 10px;">📋 Import Batch from Clipboard</h2>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                Paste your data below. Format: <code style="background: #f1f5f9; padding: 2px 4px; border-radius: 4px;">Type [Tab] Brand [Tab] Model [Tab] Series [Tab] CPU [Tab] Description [Tab] Price [Tab] QTY</code>
            </p>

            <textarea id="import-paste-area" placeholder="Paste rows here..." style="width: 100%; height: 300px; border-radius: 12px; border: 2px solid #e2e8f0; padding: 15px; font-family: monospace; font-size: 0.85rem; resize: none; margin-bottom: 20px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--accent-color)'" onblur="this.style.borderColor='#e2e8f0'"></textarea>

            <div id="import-preview" style="margin-bottom: 20px; display: none;">
                <div id="import-mapping-info"></div>
                <h3 style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px;">Preview: <span id="import-row-count">0</span> rows detected</h3>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.7rem; background: #f8fafc;">
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;" id="import-preview-table">
                        <!-- Populated by JS -->
                    </table>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="processImport()" id="btn-submit-import" class="btn-main" style="flex: 2; height: 50px; background: var(--text-main); color: white; border: none; border-radius: 12px; font-weight: 800; cursor: pointer;">🚀 Start Bulk Import</button>
                <button type="button" onclick="closeImportModal()" class="btn-main" style="flex: 1; height: 50px; background: #f1f5f9; color: #64748b; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">Cancel</button>
            </div>
        </div>
    </div>
</div>


