<?php 
require_once 'core/database.php';
include 'core/auth.php'; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Entry | IQA Metal</title>
    <meta name="description" content="IQA Metal Order Management and Warehouse Control System. Efficiently manage batches,      , and customer fulfillments.">

    <!-- Optimize Third-Party Connections (Non-blocking Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap">
    </noscript>

    <!-- Primary Stylesheet (LCP Priority) -->
    <link rel="stylesheet" href="assets/styles/style.css?v=<?= filemtime('assets/styles/style.css') ?>">

    <!-- Conditional Style Discovery -->
    <?php
        $view = $_GET['view'] ?? 'default';
        $is_new_order = isset($_GET['customer_id']);
        
        $routes = [
            'register'  => ['page' => 'pages/new_customer.php',     'css' => 'customer_registry.css'],
            'orders'    => ['page' => 'pages/orders.php',           'css' => 'orders.css'],
            'leads'     => ['page' => 'pages/leads.php',            'css' => 'leads.css'],
            'warehouse' => ['page' => 'pages/warehouse.php',        'css' => 'warehouse.css'],
            'import_warehouse' => ['page' => 'pages/import_warehouse.php', 'css' => 'warehouse.css'],
            'settings'  => ['page' => 'pages/settings.php',         'css' => 'style.css'],
            'default'   => ['page' => 'pages/customer_registry.php', 'css' => 'customer_registry.css'],
            'new_order' => ['page' => 'pages/new_order.php',         'css' => 'new_order.css']
        ];

        $active_key = $is_new_order ? 'new_order' : (isset($routes[$view]) ? $view : 'default');
        
        // --- ROLE BASED ACCESS CONTROL ---
        $user_role = $_SESSION['role'] ?? 'Operator';
        if ($user_role !== 'Admin') {
            // Restrict operators to only Warehouse and Personal Settings
            $allowed_operator_keys = ['warehouse', 'import_warehouse', 'settings'];
            if (!in_array($active_key, $allowed_operator_keys)) {
                $active_key = 'warehouse';
            }
        }
        
        $active_route = $routes[$active_key];

        if ($active_route['css'] !== 'style.css') {
            $css_path = 'assets/styles/' . $active_route['css'];
            echo '<link rel="stylesheet" href="'.$css_path.'?v='.filemtime($css_path).'">';
        }
    ?>

    <link rel="icon" type="image/png" href="assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">

    <!-- Logic Initialization (Deferred) -->
    <script src="assets/js/inventory_data.js?v=<?= filemtime('assets/js/inventory_data.js') ?>" defer></script>
</head>

<body>
    <div class="breadcrumb-container" role="banner" style="max-width: 800px; margin: 0 auto 20px auto; width: 100%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <?php if ($user_role === 'Admin'): ?>
                <a href="index.php"
                    class="crumb <?= !isset($_GET['customer_id']) && !isset($_GET['view']) ? 'active' : '' ?>">
                    <span class="step-num">1</span> Customers
                </a>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'register'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">2</span> Register
                </a>
                <?php endif; ?>

                <?php if (isset($_GET['customer_id'])): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">2</span> Order Entry
                </a>
                <?php endif; ?>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">⚙️</span> Settings
                </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="index.php?view=warehouse" class="crumb <?= !isset($_GET['view']) || $_GET['view'] === 'warehouse' ? 'active' : '' ?>">
                    <span class="step-num">🏬</span> Warehouse Portal
                </a>
                <?php if (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">⚙️</span> Personal Settings
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <nav class="breadcrumbs" style="display: flex; gap: 20px; align-items: center;">
            <?php if ($user_role === 'Admin'): ?>
                <a href="index.php?view=leads" class="crumb <?= isset($_GET['view']) && $_GET['view'] === 'leads' ? 'active' : '' ?>" style="margin:0;">
                    🎯 Leads
                </a>
            <?php endif; ?>
            
            <a href="index.php?view=warehouse" class="crumb <?= isset($_GET['view']) && $_GET['view'] === 'warehouse' ? 'active' : '' ?>" style="margin:0;">
                🏬 Warehouse
            </a>

            <?php if ($user_role === 'Admin'): ?>
                <a href="index.php?view=orders" class="crumb <?= isset($_GET['view']) && $_GET['view'] === 'orders' ? 'active' : '' ?>" style="margin:0;">
                    📦 All Orders
                </a>
            <?php endif; ?>

            <a href="index.php?view=settings" class="crumb icon-only <?= isset($_GET['view']) && $_GET['view'] === 'settings' ? 'active' : '' ?>" style="margin:0;" title="Settings">⚙️</a>
        </nav>
    </div>

    <div class="container <?= in_array($active_key, ['new_order', 'orders', 'warehouse', 'leads', 'import_warehouse']) ? 'order-view' : '' ?>" role="main">
        <?php
        // Global State Initialization
        $selected_sector = $_GET['sector'] ?? 'Laptops';
        $selected_loc = $_GET['loc'] ?? null;

        // Order Creation Logic
        if (isset($_GET['action']) && $_GET['action'] === 'create_new_order' && isset($_GET['customer_id'])) {
            $conn_o = Database::orders();
            $new_order_id = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmt = $conn_o->prepare("INSERT INTO orders (order_id, customer_id, status) VALUES (?, ?, 'active')");
            $stmt->execute([$new_order_id, $_GET['customer_id']]);

            header("Location: index.php?customer_id=" . urlencode($_GET['customer_id']) . "&order_id=" . $new_order_id);
            exit();
        }

        if ($is_new_order) {
            $current_customer = $_GET['customer_id'];
            $current_order = $_GET['order_id'] ?? null;
        }

        include $active_route['page'];
        ?>
    </div>
    <footer class="footer" role="contentinfo">
        <p>&copy; <?= date('M Y') ?> IQA Metal | Managed Inventory & Order Fulfillments <a href="../labels/">Labels</a></p>
    </footer>
    <!-- Load view-specific JavaScript -->
    <?php if ($active_key === 'new_order'): ?>
        <script src="assets/js/new_order.js?v=<?= filemtime('assets/js/new_order.js') ?>" defer></script>
    <?php elseif ($active_key === 'warehouse' || $active_key === 'import_warehouse'): ?>
        <script src="assets/js/warehouse.js?v=<?= filemtime('assets/js/warehouse.js') ?>" defer></script>
    <?php elseif ($active_key === 'default' || $active_key === 'register'): ?>
        <script src="assets/js/customer_registry.js?v=<?= filemtime('assets/js/customer_registry.js') ?>" defer></script>
    <?php elseif ($active_key === 'leads' && file_exists('assets/js/leads.js')): ?>
        <script src="assets/js/leads.js?v=<?= filemtime('assets/js/leads.js') ?>" defer></script>
    <?php elseif ($active_key === 'orders'): ?>
        <script src="assets/js/orders.js?v=<?= filemtime('assets/js/orders.js') ?>" defer></script>
    <?php endif; ?>
</body>

</html>