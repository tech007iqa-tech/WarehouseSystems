<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::customers();
    $conn_orders = Database::orders();

    // Create orders table if first run
    $conn_orders->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT NOT NULL UNIQUE,
        customer_id TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Initial schema (redundant if already created)
    $conn->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id TEXT NOT NULL UNIQUE,
        company_name TEXT NOT NULL,
        website TEXT,
        contact_person TEXT,
        address TEXT,
        email TEXT,
        phone TEXT,
        shipping_address TEXT,
        internal_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // CRM Migration
    $cols = $conn->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
    $has_cb = false; $has_msg = false;
    foreach($cols as $c) {
        if ($c['name'] === 'callback_date') $has_cb = true;
        if ($c['name'] === 'message_date') $has_msg = true;
    }
    if (!$has_cb) $conn->exec("ALTER TABLE customers ADD COLUMN callback_date TEXT DEFAULT ''");
    if (!$has_msg) $conn->exec("ALTER TABLE customers ADD COLUMN message_date TEXT DEFAULT ''");

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
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

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>

<!-- Load dedicated registry styles -->


<div class="form-side">
    <header>
        <h1>Active Customers</h1>
        <p class="subtitle">Select a customer below or register a new one to begin.</p>
    </header>

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
        
        // Fetch All Customers with Aggregated Order Data using Cross-DB Join
        try {
            Database::attach($conn, 'orders', 'orders_db');
            
            $sql = "
                SELECT c.*, 
                    (SELECT COUNT(*) FROM orders_db.orders o WHERE o.customer_id = c.customer_id) as total_orders,
                    (SELECT SUM(i.quantity * i.unit_price) FROM orders_db.items i WHERE i.customer_id = c.customer_id) as lifetime_value,
                    (SELECT MAX(o.created_at) FROM orders_db.orders o WHERE o.customer_id = c.customer_id) as last_order_date,
                    (SELECT COUNT(*) FROM orders_db.orders o WHERE o.customer_id = c.customer_id AND o.status IN ('finalized', 'paid', 'dispatched', 'canceled')) as completed_count,
                    (SELECT COUNT(*) FROM orders_db.orders o WHERE o.customer_id = c.customer_id AND o.status NOT IN ('finalized', 'paid', 'dispatched', 'canceled')) as active_count
                FROM customers c
            ";
        } catch (Exception $e) {
            // Fallback if orders DB is not available
            $sql = "SELECT *, 0 as total_orders, 0 as lifetime_value, '0000-00-00 00:00:00' as last_order_date, 0 as completed_count, 0 as active_count FROM customers";
        }
        
        $stmt = $conn->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        }
 else {
            echo "<div class='empty-state' style='padding: 40px;'>No customers registered yet.</div>";
        }
        ?>
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


