<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
require_once 'core/database.php';

// Handle live pricing matrix updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_pricing_matrix') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $category = $input['category'] ?? '';
    $cpu_gen = $input['cpu_gen'] ?? '';
    $grade = $input['grade'] ?? '';
    $price = isset($input['price']) ? (float)$input['price'] : 0.00;
    
    if (!empty($category) && !empty($cpu_gen) && !empty($grade)) {
        try {
            $conn_wh = Database::warehouse();
            $stmt = $conn_wh->prepare("UPDATE pricing_rules SET price = ? WHERE category = ? AND cpu_gen = ? AND grade = ?");
            $stmt->execute([$price, $category, $cpu_gen, $grade]);
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$date_condition = "";
if ($filter === '30d') {
    $date_condition = "AND orders.created_at >= date('now', '-30 days')";
} elseif ($filter === 'ytd') {
    $date_condition = "AND orders.created_at >= date('now', 'start of year')";
}

try {
    $db = Database::orders();

    // 1. Fetch Sales Velocity (Top Brands/Models) + Inventory Check + Customer Names + Order IDs
    $velocity = Database::queryIntegrated('orders', ['w' => 'warehouse', 'c' => 'customers'], "
        SELECT items.brand, items.model, items.series, items.cpu, items.description, SUM(items.quantity) as total_qty, ROUND(AVG(items.unit_price), 2) as avg_price,
               (SELECT SUM(quantity) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status = '') as in_stock,
               (SELECT GROUP_CONCAT(DISTINCT location_code) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status = '') as stock_locations,
               (SELECT SUM(quantity) FROM w.inventory WHERE brand = items.brand AND model = items.model AND status != '') as incoming_stock,
               GROUP_CONCAT(DISTINCT c.customers.company_name) as buyer_names,
               GROUP_CONCAT(DISTINCT items.order_id || '|' || SUBSTR(orders.created_at, 1, 10) || '|' || orders.customer_id) as order_ids
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        LEFT JOIN c.customers ON items.customer_id = c.customers.customer_id
        WHERE 1=1 $date_condition
        GROUP BY items.brand, items.model, items.series, items.cpu, items.description
        ORDER BY total_qty DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Pricing Trends over Time
    $price_history = $db->query("
        SELECT strftime('%Y-%m', orders.created_at) as sales_month,
               ROUND(AVG(items.unit_price), 2) as avg_price,
               SUM(items.quantity) as total_qty,
               ROUND(SUM(items.unit_price * items.quantity), 2) as total_valuation
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE 1=1 $date_condition
        GROUP BY sales_month
        ORDER BY sales_month DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch CPU Architectures Distribution (categorized in PHP)
    $raw_cpu_distribution = $db->query("
        SELECT items.cpu,
               items.quantity,
               items.unit_price
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE items.cpu IS NOT NULL $date_condition
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Classification helper
    function categorizeCpu($cpuStr) {
        $cpu = strtolower(trim($cpuStr));
        if (empty($cpu) || $cpu === '—' || $cpu === '-' || $cpu === 'em dash') {
            return 'Apple';
        }

        // Apple Silicon / Apple general
        if (strpos($cpu, 'apple') !== false || strpos($cpu, 'm1') !== false || strpos($cpu, 'm2') !== false || strpos($cpu, 'm3') !== false || strpos($cpu, 'm4') !== false || strpos($cpu, 'silicon') !== false) {
            return 'Apple';
        }

        // AMD Ryzen / AMD general
        if (strpos($cpu, 'ryzen') !== false || strpos($cpu, 'amd') !== false) {
            return 'Ryzen';
        }

        // Core 2 Duo
        if (strpos($cpu, 'core 2') !== false || strpos($cpu, 'core2') !== false || strpos($cpu, 'duo') !== false) {
            return 'Core 2 Duo';
        }

        // Generations check
        $is2nd3rd = (strpos($cpu, '2nd') !== false || strpos($cpu, '3rd') !== false);
        $is4th5th = (strpos($cpu, '4th') !== false || strpos($cpu, '5th') !== false);
        $is6th7th = (strpos($cpu, '6th') !== false || strpos($cpu, '7th') !== false);

        if ($is2nd3rd) return '2nd & 3rd Gen';
        if ($is4th5th) return '4th & 5th Gen';
        if ($is6th7th) return '6th & 7th Gen';

        // Check 8th to 14th Gen explicitly
        $gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
        foreach ($gens as $gen) {
            if (strpos($cpu, strtolower($gen)) !== false) {
                if (strpos($cpu, 'i3') !== false) return "$gen Gen i3";
                if (strpos($cpu, 'i5') !== false) return "$gen Gen i5";
                if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return "$gen Gen i7";
                return "$gen Gen i5"; // default fallback for this gen
            }
        }

        // Regex check for model numbers (e.g. i5-10300H or i7-8550U)
        if (preg_match('/i(3|5|7|9)-(\d{1,2})\d{3}/', $cpu, $matches)) {
            $tier = 'i' . ($matches[1] == '9' ? '7' : $matches[1]);
            $num = intval($matches[2]);
            if ($num >= 8 && $num <= 14) {
                return $num . 'th Gen ' . $tier;
            }
        }

        // Fallback modern Intel Core i series
        if (strpos($cpu, 'i3') !== false) return '8th Gen i3';
        if (strpos($cpu, 'i5') !== false) return '8th Gen i5';
        if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return '8th Gen i7';

        return 'Other';
    }

    $categories = [
        'Core 2 Duo'     => ['total_qty' => 0, 'prices' => []],
        '2nd & 3rd Gen'  => ['total_qty' => 0, 'prices' => []],
        '4th & 5th Gen'  => ['total_qty' => 0, 'prices' => []],
        '6th & 7th Gen'  => ['total_qty' => 0, 'prices' => []],
    ];

    $gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
    $tiers = ['i3', 'i5', 'i7'];
    foreach ($gens as $gen) {
        foreach ($tiers as $tier) {
            $categories["$gen Gen $tier"] = ['total_qty' => 0, 'prices' => []];
        }
    }

    $categories['Apple'] = ['total_qty' => 0, 'prices' => []];
    $categories['Ryzen'] = ['total_qty' => 0, 'prices' => []];

    foreach ($raw_cpu_distribution as $row) {
        $cat = categorizeCpu($row['cpu']);
        if (!isset($categories[$cat])) continue;

        $qty = intval($row['quantity']);
        $price = floatval($row['unit_price']);

        $categories[$cat]['total_qty'] += $qty;
        for ($i = 0; $i < $qty; $i++) {
            $categories[$cat]['prices'][] = $price;
        }
    }

    $cpu_distribution = [];
    foreach ($categories as $name => $data) {
        if ($data['total_qty'] > 0) {
            $prices = $data['prices'];
            $avg = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
            $min = count($prices) > 0 ? min($prices) : 0;
            $max = count($prices) > 0 ? max($prices) : 0;

            $cpu_distribution[] = [
                'cpu' => $name,
                'total_qty' => $data['total_qty'],
                'avg_price' => round($avg, 2),
                'min_price' => round($min, 2),
                'max_price' => round($max, 2)
            ];
        }
    }

    // 4. Summary metrics
    $totals = $db->query("
        SELECT SUM(items.quantity) as total_qty, COUNT(DISTINCT items.order_id) as total_orders, ROUND(AVG(items.unit_price * items.quantity), 2) as avg_order_val
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE 1=1 $date_condition
    ")->fetch(PDO::FETCH_ASSOC);

    // 5. Customer Insights
    $customer_insights = Database::queryIntegrated('orders', ['c' => 'customers'], "
        SELECT c.customers.company_name,
               COUNT(DISTINCT items.order_id) as total_orders,
               SUM(items.quantity) as total_units_bought,
               MIN(orders.created_at) as first_order_date,
               MAX(orders.created_at) as last_order_date
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        JOIN c.customers ON items.customer_id = c.customers.customer_id
        WHERE 1=1 $date_condition
        GROUP BY items.customer_id
        ORDER BY total_units_bought DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6. Additional computed metrics
    $top_buyer_name = "None";
    $top_buyer_qty = 0;
    if (!empty($customer_insights)) {
        $top_buyer_name = $customer_insights[0]['company_name'];
        $top_buyer_qty = $customer_insights[0]['total_units_bought'];
    }

    $popular_brand = "None";
    $popular_brand_qty = 0;
    $brand_stats = $db->query("
        SELECT items.brand, SUM(items.quantity) as qty
        FROM items
        JOIN orders ON items.order_id = orders.order_id
        WHERE 1=1 $date_condition
        GROUP BY items.brand
        ORDER BY qty DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($brand_stats) {
        $popular_brand = $brand_stats['brand'];
        $popular_brand_qty = $brand_stats['qty'];
    }

    $peak_month = "N/A";
    $peak_month_val = 0;
    foreach ($price_history as $hist) {
        $val = $hist['total_valuation'] ?? ($hist['avg_price'] * $hist['total_qty']);
        if ($val > $peak_month_val) {
            $peak_month_val = $val;
            $peak_month = $hist['sales_month'];
        }
    }

    $total_ryzen_sold = 0;
    foreach ($cpu_distribution as $cpu) {
        if ($cpu['cpu'] === 'Ryzen') {
            $total_ryzen_sold = $cpu['total_qty'];
        }
    }
    
    // Fetch Pricing Matrix Rules
    $conn_wh = Database::warehouse();
    $pricing_rules_raw = $conn_wh->query("SELECT * FROM pricing_rules ORDER BY category ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $pricing_matrix = [];
    foreach ($pricing_rules_raw as $rule) {
        $pricing_matrix[$rule['category']][$rule['cpu_gen']][$rule['grade']] = $rule['price'];
    }

} catch (Exception $e) {
    // Graceful fallbacks for empty schema or startup database
    $velocity = [];
    $price_history = [];
    $cpu_distribution = [];
    $customer_insights = [];
    $totals = ['total_qty' => 0, 'total_orders' => 0, 'avg_order_val' => 0.00];
    $top_buyer_name = "None";
    $top_buyer_qty = 0;
    $popular_brand = "None";
    $popular_brand_qty = 0;
    $peak_month = "N/A";
    $total_ryzen_sold = 0;
    $pricing_matrix = [];
}

// Fallback seed mock data if database is empty so the user can see beautiful visualizations!
$is_using_mock_data = false;
if (empty($velocity)) {
    $is_using_mock_data = true;
    $velocity = [
        ['brand' => 'Apple', 'model' => 'MacBook Air A1932', 'total_qty' => 148, 'avg_price' => 245.00, 'in_stock' => 12, 'stock_locations' => 'A1, B2', 'incoming_stock' => 2, 'buyer_names' => 'Acme Corp, Global Tech', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME, ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'Lenovo', 'model' => 'ThinkPad T480', 'total_qty' => 112, 'avg_price' => 165.00, 'in_stock' => 8, 'stock_locations' => 'C3', 'incoming_stock' => 0, 'buyer_names' => 'Acme Corp', 'order_ids' => 'ORD-771C3|2026-05-10|CUST-ACME'],
        ['brand' => 'Dell', 'model' => 'Latitude 7490', 'total_qty' => 95, 'avg_price' => 135.00, 'in_stock' => 0, 'stock_locations' => '', 'incoming_stock' => 4, 'buyer_names' => 'Global Tech, Stark Industries', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME, ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'HP', 'model' => 'EliteBook 840 G5', 'total_qty' => 74, 'avg_price' => 155.00, 'in_stock' => 5, 'stock_locations' => 'D1', 'incoming_stock' => 1, 'buyer_names' => 'Stark Industries', 'order_ids' => 'ORD-882B2|2026-04-25|CUST-GLOBAL'],
        ['brand' => 'Apple', 'model' => 'MacBook Pro A1708', 'total_qty' => 58, 'avg_price' => 220.00, 'in_stock' => 2, 'stock_locations' => 'A3', 'incoming_stock' => 0, 'buyer_names' => 'Acme Corp', 'order_ids' => 'ORD-993A7|2026-05-10|CUST-ACME']
    ];
}

if (empty($price_history)) {
    $price_history = [
        ['sales_month' => '2026-05', 'avg_price' => 210.00, 'total_qty' => 380, 'total_valuation' => 79800.00],
        ['sales_month' => '2026-04', 'avg_price' => 195.00, 'total_qty' => 420, 'total_valuation' => 81900.00],
        ['sales_month' => '2026-03', 'avg_price' => 225.00, 'total_qty' => 310, 'total_valuation' => 69750.00],
        ['sales_month' => '2026-02', 'avg_price' => 180.00, 'total_qty' => 290, 'total_valuation' => 52200.00],
        ['sales_month' => '2026-01', 'avg_price' => 205.00, 'total_qty' => 340, 'total_valuation' => 69700.00],
        ['sales_month' => '2025-12', 'avg_price' => 190.00, 'total_qty' => 450, 'total_valuation' => 85500.00]
    ];
}

if (empty($cpu_distribution)) {
    $cpu_distribution = [
        ['cpu' => '8th Gen+ i5', 'total_qty' => 259, 'avg_price' => 210.00, 'min_price' => 180.00, 'max_price' => 310.00],
        ['cpu' => '8th Gen+ i7', 'total_qty' => 180, 'avg_price' => 260.00, 'min_price' => 200.00, 'max_price' => 390.00],
        ['cpu' => '2nd & 3rd Gen', 'total_qty' => 185, 'avg_price' => 150.00, 'min_price' => 120.00, 'max_price' => 220.00],
        ['cpu' => '4th & 5th Gen', 'total_qty' => 124, 'avg_price' => 185.00, 'min_price' => 140.00, 'max_price' => 280.00],
        ['cpu' => 'Apple', 'total_qty' => 92, 'avg_price' => 295.00, 'min_price' => 250.00, 'max_price' => 450.00],
        ['cpu' => 'Ryzen', 'total_qty' => 64, 'avg_price' => 240.00, 'min_price' => 190.00, 'max_price' => 320.00]
    ];
}

if (empty($customer_insights)) {
    $customer_insights = [
        ['company_name' => 'Acme Corp', 'total_orders' => 12, 'total_units_bought' => 150, 'first_order_date' => '2025-01-15', 'last_order_date' => '2026-05-10'],
        ['company_name' => 'Global Tech', 'total_orders' => 8, 'total_units_bought' => 120, 'first_order_date' => '2025-03-20', 'last_order_date' => '2026-04-25'],
        ['company_name' => 'Stark Industries', 'total_orders' => 5, 'total_units_bought' => 85, 'first_order_date' => '2025-06-11', 'last_order_date' => '2026-05-01']
    ];
}

if (!$totals || $totals['total_qty'] == 0) {
    $totals = ['total_qty' => 487, 'total_orders' => 32, 'avg_order_val' => 2845.50];
}

if ($is_using_mock_data) {
    $top_buyer_name = "Acme Corp";
    $top_buyer_qty = 150;
    $popular_brand = "Apple";
    $popular_brand_qty = 206;
    $peak_month = "2026-04";
    $total_ryzen_sold = 64;
}
?>

<div class="trends-container">
    <div class="trends-header">
        <div>
            <h1 style="font-weight: 900; font-size: 1.8rem; margin: 0; display: flex; align-items: center; gap: 10px;">
                📈 Sales & Item Trends Center
            </h1>
            <p class="subtitle" style="margin-top: 4px;">
                Analyzing historical line-items from the database to discover price curves and asset velocities.
                <?php if ($is_using_mock_data): ?>
                    <span style="color: var(--accent-color); font-weight: 700;">(Showing demo data until your first orders are placed)</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select id="trends-filter" onchange="window.location.href='?view=trends&filter='+this.value" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-main); font-weight: 600;">
                <option value="30d" <?= $filter === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="ytd" <?= $filter === 'ytd' ? 'selected' : '' ?>>Year to Date</option>
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
        </div>
    </div>

    <!-- Widgets Board Controller Bar -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn-main dark" id="toggle-widgets-btn" onclick="toggleWidgetBoard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; box-shadow: none;">📊 Show Summary Cards</button>
        <button type="button" class="btn-main" id="config-widgets-btn" onclick="toggleConfigPanel()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; display: none; background: var(--bg-surface-2); color: var(--text-main) !important; border: 1px solid var(--border-color); box-shadow: none;">⚙️ Customize Board</button>
    </div>

    <!-- Widget Configurations Panel -->
    <div id="widgets-config-panel" style="display: none; background: var(--bg-panel); border: 1px solid var(--border-color); padding: 20px; border-radius: var(--border-radius-lg); margin-bottom: 25px;">
        <h3 style="margin-top: 0; font-size: 1rem; margin-bottom: 12px; font-weight: 800;">Toggle Metrics Visibility</h3>
        <div id="widget-toggles-container" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
            <!-- Populated dynamically by JS -->
        </div>
        <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 15px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn-main" onclick="addNewNoteCard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #8b5cf6; box-shadow: none;">+ Add Note Card</button>
            <button type="button" class="btn-main" onclick="addNewCustomMetricCard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #3b82f6; box-shadow: none;">+ Add Custom Metric</button>
            <button type="button" class="btn-main dark" onclick="resetWidgetsToDefault()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #ef4444; box-shadow: none;">Reset Board</button>
        </div>
    </div>

    <!-- Overview Stats Grid (Dynamic Widget Board) -->
    <div id="widget-board" class="trends-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); margin-bottom: 25px;">
        <!-- Populated dynamically by JS -->
    </div>

    <!-- Interactive Navigation Tabs -->
    <div class="tab-nav">
        <button type="button" class="tab-btn active" onclick="switchTrendsTab('tab-velocity')">🔥 Model Demand</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-pricing')">📊 Pricing Curves</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-cpu')">💻 CPU Generations</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-customers')">👥 Customer Insights</button>
        <button type="button" class="tab-btn" onclick="switchTrendsTab('tab-matrix')">💵 Pricing Matrix</button>
    </div>

    <!-- Global Flexible Search Input -->
    <div class="trends-search-wrapper" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: var(--bg-surface); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color);">
        <input type="text" id="trends-search" class="trends-search-input" placeholder="🔍 Type to filter rows (by model, specs, price, CPU etc)..." oninput="handleSearch(this.value)" style="flex: 1; border: none; background: transparent; color: var(--text-main); font-size: 0.95rem; outline: none;">
        <button type="button" id="clear-search" class="clear-search-btn" onclick="clearSearchInput()" style="display: none; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; padding: 0 5px;">✕</button>
    </div>

    <!-- Tab 1: Demand Velocity (Best-selling Laptops) -->
    <div id="tab-velocity" class="tab-content active">
        <div class="trends-grid" style="display: flex; flex-direction: column;">

            <!-- Interactive Table -->
            <div class="trend-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <h2 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                        🥇 Table
                    </h2>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <label for="inStockOnly" style="font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" id="inStockOnly" class="in-stock-only-checkbox" onchange="filterActiveTable()"> In Stock Only
                        </label>
                    </div>
                </div>

                <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
                <div class="trends-table-container">
                    <table class="trends-table" id="table-velocity">
                        <thead>
                            <tr>
                                <th onclick="sortTable('table-velocity', 0, 'num')">
                                    <span class="rank-header">Rank</span>
                                    <span class="buyer-header" style="display: none;">Customer</span>
                                </th>
                                <th onclick="sortTable('table-velocity', 1, 'str')">Brand</th>
                                <th onclick="sortTable('table-velocity', 2, 'str')">Model</th>
                                <th onclick="sortTable('table-velocity', 3, 'str')">Details</th>
                                <th onclick="sortTable('table-velocity', 4, 'date')">
                                    <span class="stock-header">Latest Sold</span>
                                    <span class="order-header" style="display: none;">Customer Order</span>
                                </th>
                                <th onclick="sortTable('table-velocity', 5, 'num')">Avg Price</th>
                                <th onclick="sortTable('table-velocity', 6, 'num')" class="sort-desc">Units Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($velocity as $idx => $item): ?>
                                <?php
                                    $search_blob = strtolower(
                                        $item['brand'] . ' ' .
                                        $item['model'] . ' ' .
                                        ($item['series'] ?? '') . ' ' .
                                        ($item['cpu'] ?? '') . ' ' .
                                        ($item['description'] ?? '') . ' ' .
                                        $item['avg_price'] . ' ' .
                                        ($item['buyer_names'] ?? '') . ' ' .
                                        ($item['order_ids'] ?? '')
                                    );
                                    $in_stock = $item['in_stock'] ?? 0;
                                    $incoming = $item['incoming_stock'] ?? 0;
                                    $unique_dates = [];
                                    $first_date = '';
                                    if (!empty($item['order_ids'])) {
                                        $ords = explode(',', $item['order_ids']);
                                        foreach ($ords as $ord) {
                                            $parts = explode('|', trim($ord));
                                            $o_date = $parts[1] ?? '';
                                            if ($o_date && !in_array($o_date, $unique_dates)) {
                                                $unique_dates[] = $o_date;
                                            }
                                        }
                                    }
                                    rsort($unique_dates);
                                    $first_date = $unique_dates[0] ?? '';
                                ?>
                                <tr data-search="<?= htmlspecialchars($search_blob) ?>" data-instock="<?= $in_stock ?>">
                                    <td>
                                        <span class="rank-cell" style="font-weight: 900; color: var(--accent-color);">#<?= $idx + 1 ?></span>
                                        <span class="buyer-cell" style="display: none; font-size: 0.8rem; font-weight: 700; color: var(--accent-color);"><?= htmlspecialchars($item['buyer_names'] ?: '—') ?></span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($item['brand']) ?></strong></td>
                                    <td><?= htmlspecialchars($item['model']) ?></td>
                                    <td>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                            <?= htmlspecialchars($item['series'] ?? '') ?>
                                            <?= !empty($item['cpu']) ? ' • ' . htmlspecialchars($item['cpu']) : '' ?>
                                            <?= !empty($item['description']) ? ' • ' . htmlspecialchars($item['description']) : '' ?>
                                        </div>
                                    </td>
                                    <td data-sort-val="<?= htmlspecialchars($first_date) ?>">
                                        <div class="stock-cell">
                                            <?php
                                            if (!empty($first_date)) {
                                                $current_year = date('Y');
                                                $date_parts = explode('-', $first_date);
                                                $display_date = $first_date;
                                                if (count($date_parts) === 3) {
                                                    if ($date_parts[0] === $current_year) {
                                                        $display_date = $date_parts[1] . '-' . $date_parts[2];
                                                    }
                                                }
                                                echo htmlspecialchars($display_date);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </div>
                                         <div class="order-cell" style="display: none; font-size: 0.8rem; font-family: monospace;">
                                             <?php
                                             if (!empty($item['order_ids'])) {
                                                 $ords = explode(',', $item['order_ids']);
                                                 $rendered = [];
                                                 $current_year = date('Y');
                                                 foreach ($ords as $ord) {
                                                     $parts = explode('|', trim($ord));
                                                     $o_id = $parts[0] ?? '';
                                                     $o_date = $parts[1] ?? '';
                                                     if ($o_id) {
                                                         $display_date = '';
                                                         if ($o_date) {
                                                             $date_parts = explode('-', $o_date);
                                                             $display_date = $o_date;
                                                             if (count($date_parts) === 3) {
                                                                 if ($date_parts[0] === $current_year) {
                                                                     $display_date = $date_parts[1] . '-' . $date_parts[2];
                                                                 }
                                                             }
                                                             $display_date = ' <span style="font-size: 0.7rem; color: var(--text-secondary); font-family: var(--font-main);">(' . htmlspecialchars($display_date) . ')</span>';
                                                         }
                                                         $rendered[] = '<span><a href="#" onclick="openOrderPreviewModal(event, \'' . htmlspecialchars($o_id) . '\')" class="order-preview-link"><code>' . htmlspecialchars($o_id) . '</code></a>' . $display_date . '</span>';
                                                     }
                                                 }
                                                 echo implode(', ', $rendered);
                                             } else {
                                                 echo '—';
                                             }
                                             ?>
                                         </div>
                                     </td>
                                    <td data-sort-val="<?= $item['avg_price'] ?>">$<?= number_format($item['avg_price'], 2) ?></td>
                                    <td data-sort-val="<?= $item['total_qty'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $item['total_qty'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CSS Bar Chart Visualization -->
            <div class="trend-card">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📊 Volume Share</h2>
                <?php
                $chart_velocity = array_slice($velocity, 0, 10);
                $max_qty = count($chart_velocity) > 0 ? max(array_column($chart_velocity, 'total_qty')) : 1;
                ?>
                <div class="chart-placeholder" style="margin-top: 10px;">
                    <?php foreach ($chart_velocity as $item):
                        $height = ($item['total_qty'] / $max_qty) * 100;
                    ?>
                        <div class="bar-container">
                            <div class="chart-bar" style="height: <?= $height ?>%;" title="<?= $item['total_qty'] ?> units"></div>
                            <div class="bar-label" title="<?= htmlspecialchars($item['model']) ?>"><?= htmlspecialchars($item['model']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Pricing History (Price Curves over past months) -->
    <div id="tab-pricing" class="tab-content">
        <!-- New side-by-side or responsive grid for interactive charts -->
        <div class="trends-grid" style="margin-bottom: 20px;">
            <div class="trend-card">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📉 Average Selling Price Timeline</h2>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="aspChart"></canvas>
                </div>
            </div>

            <div class="trend-card">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📈 Monthly Valuation Trend</h2>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="valuationChart"></canvas>
                </div>
            </div>
        </div>

        <div class="trends-grid">
            <div class="trend-card" style="flex: 1;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📅 Pricing & Valuation History</h2>

                <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
                <div class="trends-table-container">
                    <table class="trends-table" id="table-pricing">
                        <thead>
                            <tr>
                                <th onclick="sortTable('table-pricing', 0, 'str')" class="sort-desc">Month</th>
                                <th onclick="sortTable('table-pricing', 1, 'num')">Units Moved</th>
                                <th onclick="sortTable('table-pricing', 2, 'num')">Avg Price</th>
                                <th onclick="sortTable('table-pricing', 3, 'num')">Total Valuation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($price_history as $history): ?>
                                <?php
                                    $valuation = $history['total_valuation'] ?? ($history['avg_price'] * $history['total_qty']);
                                    $search_blob = strtolower($history['sales_month'] . ' ' . $history['avg_price']);
                                ?>
                                <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                    <td>📅 <strong><?= htmlspecialchars($history['sales_month']) ?></strong></td>
                                    <td data-sort-val="<?= $history['total_qty'] ?>"><?= $history['total_qty'] ?> units</td>
                                    <td data-sort-val="<?= $history['avg_price'] ?>">$<?= number_format($history['avg_price'], 2) ?></td>
                                    <td data-sort-val="<?= $valuation ?>" class="stat-value">$<?= number_format($valuation, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: CPU Generations Distribution -->
    <div id="tab-cpu" class="tab-content">
        <div class="trends-grid">
            <div class="trend-card" style="flex: 1.2;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">💻 CPU Family Dominance</h2>

                <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
                <div class="trends-table-container">
                    <table class="trends-table" id="table-cpu">
                        <thead>
                            <tr>
                                <th onclick="sortTable('table-cpu', 0, 'str')">Processor / CPU Family</th>
                                <th onclick="sortTable('table-cpu', 1, 'num')">Min Price</th>
                                <th onclick="sortTable('table-cpu', 2, 'num')">Max Price</th>
                                <th onclick="sortTable('table-cpu', 3, 'num')">Avg Price</th>
                                <th onclick="sortTable('table-cpu', 4, 'num')" class="sort-desc">Units Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cpu_distribution as $cpu): ?>
                                <?php
                                    $cpu_name = $cpu['cpu'] ? $cpu['cpu'] : 'Other Generations';
                                    $min_p = $cpu['min_price'] ?? $cpu['avg_price'];
                                    $max_p = $cpu['max_price'] ?? $cpu['avg_price'];
                                    $search_blob = strtolower($cpu_name . ' ' . $cpu['avg_price'] . ' ' . $min_p . ' ' . $max_p);
                                ?>
                                <tr class="clickable-row" data-search="<?= htmlspecialchars($search_blob) ?>" onclick="openCpuPricingModal('<?= htmlspecialchars($cpu_name) ?>')">
                                    <td>⚙️ <strong><?= htmlspecialchars($cpu_name) ?></strong></td>
                                    <td data-sort-val="<?= $min_p ?>"><span style="color: #10b981; font-weight: 600;">$<?= number_format($min_p, 2) ?></span></td>
                                    <td data-sort-val="<?= $max_p ?>"><span style="color: #3b82f6; font-weight: 600;">$<?= number_format($max_p, 2) ?></span></td>
                                    <td data-sort-val="<?= $cpu['avg_price'] ?>"><strong>$<?= number_format($cpu['avg_price'], 2) ?></strong></td>
                                    <td data-sort-val="<?= $cpu['total_qty'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $cpu['total_qty'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="trend-card" style="flex: 0.8;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">📊 CPU Manufacturer Share</h2>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="cpuBrandChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 4: Customer Insights -->
    <div id="tab-customers" class="tab-content">
        <div class="trends-grid">
            <div class="trend-card" style="flex: 1;">
                <h2 style="font-weight: 800; font-size: 1.1rem; margin-top: 0;">🤝 Top B2B Clients by Volume</h2>

                <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
                <div class="trends-table-container">
                    <table class="trends-table" id="table-customers">
                        <thead>
                            <tr>
                                <th onclick="sortTable('table-customers', 0, 'str')">Client Company</th>
                                <th onclick="sortTable('table-customers', 1, 'num')">Total Orders</th>
                                <th onclick="sortTable('table-customers', 2, 'num')" class="sort-desc">Units Bought</th>
                                <th onclick="sortTable('table-customers', 3, 'date')">First Purchase</th>
                                <th onclick="sortTable('table-customers', 4, 'date')">Last Purchase</th>
                                <th onclick="sortTable('table-customers', 5, 'str')">Activity Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_insights as $idx => $cust):
                                $last_order = new DateTime($cust['last_order_date']);
                                $now = new DateTime();
                                $days_since = $now->diff($last_order)->days;
                                $status_text = ($days_since > 60) ? "⚠️ Idle ($days_since days ago)" : "🟢 Active ($days_since days ago)";
                                $search_blob = strtolower($cust['company_name'] . ' ' . $status_text);
                            ?>
                                <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                    <td><strong><?= htmlspecialchars($cust['company_name'] ?? 'Unknown Company') ?></strong></td>
                                    <td data-sort-val="<?= $cust['total_orders'] ?>"><?= $cust['total_orders'] ?> orders</td>
                                    <td data-sort-val="<?= $cust['total_units_bought'] ?>"><span class="qty-chip" style="box-shadow: none; font-size: 0.75rem; padding: 4px 10px;"><?= $cust['total_units_bought'] ?></span></td>
                                    <td data-sort-val="<?= htmlspecialchars($cust['first_order_date']) ?>"><?= substr($cust['first_order_date'], 0, 10) ?></td>
                                    <td data-sort-val="<?= htmlspecialchars($cust['last_order_date']) ?>"><?= substr($cust['last_order_date'], 0, 10) ?></td>
                                    <td>
                                        <?php if ($days_since > 60): ?>
                                            <span style="font-size: 0.75rem; color: #f59e0b; font-weight: 700;"><?= $status_text ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #10b981; font-weight: 700;"><?= $status_text ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 5: Pricing Matrix Editor -->
    <div id="tab-matrix" class="tab-content">
        <div class="trends-grid" style="display: flex; flex-direction: column; gap: 24px;">
            <div class="trend-card">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px;">
                    <div>
                        <h2 style="font-weight: 800; font-size: 1.25rem; margin: 0;">💵 Live Pricing Matrix Reference</h2>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">Directly edit the values below. Updates will automatically apply to incoming inventory CSV imports.</p>
                    </div>
                </div>

                <!-- Regular Laptop Pricing Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 15px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">💻 Regular Laptops pricing</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 250px;">CPU Generation</th>
                                <th style="width: 200px;">Untested</th>
                                <th style="width: 200px;">Parts</th>
                                <th style="width: 200px;">C Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $gens = ['4th-5th', '6th-7th', 'i5-8th', 'i7-8th', 'i5-9th', 'i7-9th', 'i5-10th', 'i7-10th', 'i5-11th', 'i7-11th', 'i5-12th', 'i7-12th'];
                            foreach ($gens as $gen):
                            ?>
                                <tr data-search="regular <?= htmlspecialchars(strtolower($gen)) ?>">
                                     <td><strong><?= htmlspecialchars($gen) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Regular'][$gen]['Untested']) ? number_format($pricing_matrix['Regular'][$gen]['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Regular', '<?= htmlspecialchars($gen) ?>', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Regular'][$gen]['Parts']) ? number_format($pricing_matrix['Regular'][$gen]['Parts'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Regular', '<?= htmlspecialchars($gen) ?>', 'Parts', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Regular'][$gen]['C Grade']) ? number_format($pricing_matrix['Regular'][$gen]['C Grade'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Regular', '<?= htmlspecialchars($gen) ?>', 'C Grade', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                <!-- Apple Devices Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">🍏 Apple Devices</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 250px;">Model</th>
                                <th style="width: 200px;">Tested</th>
                                <th style="width: 200px;">Untested</th>
                                <th style="width: 200px;">For Parts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $apple_models = ['A1261', 'A1278', 'A1286', 'A1342', 'A1398', 'A1425', 'A1465', 'A1466', 'A1502', 'A1534', 'A1706', 'A1707', 'A1708', 'A1932', 'A2179'];
                            foreach ($apple_models as $model):
                            ?>
                                <tr data-search="apple <?= htmlspecialchars(strtolower($model)) ?>">
                                     <td><strong><?= htmlspecialchars($model) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Apple'][$model]['Tested']) ? number_format($pricing_matrix['Apple'][$model]['Tested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Apple', '<?= htmlspecialchars($model) ?>', 'Tested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Apple'][$model]['Untested']) ? number_format($pricing_matrix['Apple'][$model]['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Apple', '<?= htmlspecialchars($model) ?>', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Apple'][$model]['For Parts']) ? number_format($pricing_matrix['Apple'][$model]['For Parts'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Apple', '<?= htmlspecialchars($model) ?>', 'For Parts', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                    </table>
                </div>

                <!-- Rugged Devices Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">🏔️ Rugged Devices</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 250px;">CPU Generation</th>
                                <th style="width: 200px;">Untested Complete</th>
                                <th style="width: 200px;">Untested Parts</th>
                                <th style="width: 200px;">Tested Complete</th>
                                <th style="width: 200px;">Tested No Battery</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rugged_gens = ['4th-5th', '6th-7th', 'i5-8th', 'i7-8th', 'i5-9th', 'i7-9th', 'i5-10th', 'i7-10th'];
                            foreach ($rugged_gens as $gen):
                            ?>
                                <tr data-search="rugged <?= htmlspecialchars(strtolower($gen)) ?>">
                                     <td><strong><?= htmlspecialchars($gen) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Rugged'][$gen]['Untested Complete']) ? number_format($pricing_matrix['Rugged'][$gen]['Untested Complete'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Rugged', '<?= htmlspecialchars($gen) ?>', 'Untested Complete', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Rugged'][$gen]['Untested Parts']) ? number_format($pricing_matrix['Rugged'][$gen]['Untested Parts'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Rugged', '<?= htmlspecialchars($gen) ?>', 'Untested Parts', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Rugged'][$gen]['Tested Complete']) ? number_format($pricing_matrix['Rugged'][$gen]['Tested Complete'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Rugged', '<?= htmlspecialchars($gen) ?>', 'Tested Complete', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Rugged'][$gen]['Tested No Battery']) ? number_format($pricing_matrix['Rugged'][$gen]['Tested No Battery'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Rugged', '<?= htmlspecialchars($gen) ?>', 'Tested No Battery', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                                 <!-- Microsoft Devices Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">💻 Microsoft Surface Devices</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 300px;">Model / SKU Specification</th>
                                <th style="width: 200px;">Tested</th>
                                <th style="width: 200px;">Untested</th>
                                <th style="width: 200px;">For Parts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $microsoft_models = [
                                'Surface Laptop 1 (1769)',
                                'Surface Laptop 2 (1769)',
                                'Surface Laptop 2 (1782)',
                                'Surface Laptop 3 (1867/1868)',
                                'Surface Laptop 4 (1950/1951)',
                                'Surface Laptop 5 (1950/1951)',
                                'Surface Laptop 6 (2033/2035)',
                                'Surface Laptop Go (1943)',
                                'Surface Book 1 (1703)',
                                'Surface Book 2 (1823)',
                                'Surface Book 2 (1834/1835)',
                                'Surface Book 3 (1899)',
                                'Surface Book 3 (1900)',
                                '15" Surface Book 3 (1899)',
                                'Surface Pro 1 (1514)',
                                'Surface Pro 2 (1601)',
                                'Surface Pro 3 (1631)',
                                'Surface Pro 4 (1724)',
                                'Surface Pro 5 (1796)',
                                'Surface Pro 5 (1807)',
                                'Surface Pro 6 (1796)',
                                'Surface Pro 7 (1866)',
                                'Surface Pro 7+ (1960)',
                                'Surface Pro 8 (1983)',
                                'Surface Pro 9 (2038)',
                                'Surface Pro 10 (2079)',
                                'Surface Pro 8 (Default)',
                                'Surface Pro 9 (Default)',
                                'Surface Pro 10 (Default)'
                            ];
                            foreach ($microsoft_models as $model):
                            ?>
                                <tr data-search="microsoft surface <?= htmlspecialchars(strtolower($model)) ?>">
                                     <td><strong><?= htmlspecialchars($model) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Microsoft'][$model]['Tested']) ? number_format($pricing_matrix['Microsoft'][$model]['Tested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Microsoft', '<?= htmlspecialchars($model) ?>', 'Tested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Microsoft'][$model]['Untested']) ? number_format($pricing_matrix['Microsoft'][$model]['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Microsoft', '<?= htmlspecialchars($model) ?>', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Microsoft'][$model]['For Parts']) ? number_format($pricing_matrix['Microsoft'][$model]['For Parts'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Microsoft', '<?= htmlspecialchars($model) ?>', 'For Parts', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                                 <!-- Chromebooks Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">🔌 Chromebooks</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 300px;">Brand / Model</th>
                                <th style="width: 200px;">Untested Lot</th>
                                <th style="width: 200px;">Tested - Clean (A/B)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $chromebook_models = [
                                'Dell Chromebook 3180 / HP G5 EE',
                                'HP Chromebook 11 G6 EE',
                                'HP Chromebook 11A G6 EE',
                                'HP Chromebook 11 G7 EE',
                                'Lenovo 100e / 300e 2nd Gen (MTK)',
                                'Samsung Chromebook 4 (11")',
                                'Dell 3100 / 3100 2-in-1',
                                'HP Chromebook 11 G8 EE',
                                'HP Chromebook 11A G8 EE',
                                'HP x360 11 G3 EE (Convertible)',
                                'Lenovo 100e / 300e 2nd Gen (Intel)',
                                'Lenovo 500e 2nd Gen (Convertible)',
                                'HP x360 11 G4 EE (Convertible)',
                                'Dell Chromebook 3110 / 2-in-1',
                                'HP Chromebook 11 G9 EE',
                                'Lenovo 100e / 300e 3rd Gen',
                                'HP Chromebook 11 G10 EE',
                                'Dell Chromebook 3120'
                            ];
                            foreach ($chromebook_models as $model):
                            ?>
                                <tr data-search="chromebook <?= htmlspecialchars(strtolower($model)) ?>">
                                     <td><strong><?= htmlspecialchars($model) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Chromebook'][$model]['Untested Lot']) ? number_format($pricing_matrix['Chromebook'][$model]['Untested Lot'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Chromebook', '<?= htmlspecialchars($model) ?>', 'Untested Lot', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Chromebook'][$model]['Tested - Clean (A/B)']) ? number_format($pricing_matrix['Chromebook'][$model]['Tested - Clean (A/B)'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Chromebook', '<?= htmlspecialchars($model) ?>', 'Tested - Clean (A/B)', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                 <!-- Other Categories Pricing Tables -->
                 <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">🏷️ Other Device Categories</h3>
                 <div class="trends-table-container" style="margin-bottom: 30px;">
                     <table class="trends-table">
                         <thead>
                             <tr style="background: #0f172a; color: white;">
                                 <th style="width: 250px;">Category</th>
                                 <th style="width: 200px;">Untested</th>
                                 <th style="width: 200px;">Parts</th>
                                 <th style="width: 200px;">C Grade</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php
                             $cats = ['Gaming'];
                             foreach ($cats as $cat):
                             ?>
                                 <tr data-search="<?= htmlspecialchars(strtolower($cat)) ?>">
                                     <td><strong><?= htmlspecialchars($cat) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix[$cat]['Default']['Untested']) ? number_format($pricing_matrix[$cat]['Default']['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('<?= htmlspecialchars($cat) ?>', 'Default', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix[$cat]['Default']['Parts']) ? number_format($pricing_matrix[$cat]['Default']['Parts'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('<?= htmlspecialchars($cat) ?>', 'Default', 'Parts', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix[$cat]['Default']['C Grade']) ? number_format($pricing_matrix[$cat]['Default']['C Grade'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('<?= htmlspecialchars($cat) ?>', 'Default', 'C Grade', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                <!-- Memory (RAM) Pricing Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">🧠 Memory (RAM) Pricing</h3>
                <div class="trends-table-container" style="margin-bottom: 30px;">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 250px;">Specification</th>
                                <th style="width: 200px;">untested</th>
                                <th style="width: 200px;">tested</th>
                                <th style="width: 200px;">C grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $ram_specs = [
                                '2GB DDR3', '4GB DDR3', '8GB DDR3', '16GB DDR3', '32GB DDR3',
                                '2GB DDR4', '4GB DDR4', '8GB DDR4', '16GB DDR4', '32GB DDR4'
                            ];
                            foreach ($ram_specs as $spec):
                            ?>
                                <tr data-search="ram <?= htmlspecialchars(strtolower($spec)) ?>">
                                     <td><strong><?= htmlspecialchars($spec) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['RAM'][$spec]['Untested']) ? number_format($pricing_matrix['RAM'][$spec]['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('RAM', '<?= htmlspecialchars($spec) ?>', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['RAM'][$spec]['Tested']) ? number_format($pricing_matrix['RAM'][$spec]['Tested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('RAM', '<?= htmlspecialchars($spec) ?>', 'Tested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['RAM'][$spec]['C Grade']) ? number_format($pricing_matrix['RAM'][$spec]['C Grade'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('RAM', '<?= htmlspecialchars($spec) ?>', 'C Grade', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>

                <!-- Storage Pricing Table -->
                <h3 style="font-weight: 800; font-size: 1rem; margin-top: 25px; margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center; gap: 6px;">💾 Storage Pricing</h3>
                <div class="trends-table-container">
                    <table class="trends-table">
                        <thead>
                            <tr style="background: #0f172a; color: white;">
                                <th style="width: 250px;">Capacity</th>
                                <th style="width: 200px;">Untested</th>
                                <th style="width: 200px;">Tested</th>
                                <th style="width: 200px;">C Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $storage_specs = ['128GB M.2', '256GB M.2', '512GB M.2', '1TB M.2', '2TB M.2'];
                            foreach ($storage_specs as $spec):
                            ?>
                                <tr data-search="storage <?= htmlspecialchars(strtolower($spec)) ?>">
                                     <td><strong><?= htmlspecialchars($spec) ?></strong></td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Storage'][$spec]['Untested']) ? number_format($pricing_matrix['Storage'][$spec]['Untested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Storage', '<?= htmlspecialchars($spec) ?>', 'Untested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Storage'][$spec]['Tested']) ? number_format($pricing_matrix['Storage'][$spec]['Tested'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Storage', '<?= htmlspecialchars($spec) ?>', 'Tested', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                     <td>
                                         <div style="position: relative; display: flex; align-items: center;">
                                             <span style="position: absolute; left: 10px; font-weight: 800; color: var(--text-secondary);">$</span>
                                             <input type="number" step="any" class="matrix-cell-input" value="<?= isset($pricing_matrix['Storage'][$spec]['C Grade']) ? number_format($pricing_matrix['Storage'][$spec]['C Grade'], 2, '.', '') : '0.00' ?>" onchange="updateMatrixCell('Storage', '<?= htmlspecialchars($spec) ?>', 'C Grade', this.value)" style="width: 100%; height: 38px; padding-left: 22px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 700;">
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    filterActiveTable();

    const searchInput = document.getElementById('trends-search');
    const clearBtn = document.getElementById('clear-search');

    searchInput.addEventListener('input', () => {
        clearBtn.style.display = searchInput.value ? 'block' : 'none';
        filterActiveTable();
    });
});

function switchTrendsTab(tabId) {
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(c => c.classList.remove('active'));

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(b => b.classList.remove('active'));

    const targetContent = document.getElementById(tabId);
    if (targetContent) targetContent.classList.add('active');

    const activeBtn = Array.from(buttons).find(b => b.getAttribute('onclick').includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');

    filterActiveTable();
}

function filterActiveTable() {
    const query = document.getElementById('trends-search').value.trim().toLowerCase();
    const activeTab = document.querySelector('.tab-content.active');
    if (!activeTab) return;

    const table = activeTab.querySelector('.trends-table');
    if (!table) return;

    const showInStockOnly = activeTab.querySelector('.in-stock-only-checkbox')?.checked || false;
    const rows = table.querySelectorAll('tbody tr:not(.no-results-row)');
    let visibleCount = 0;

    // Split query into individual keywords
    const queryWords = query.split(/\s+/).filter(w => w.length > 0);
    const isSearchActive = queryWords.length > 0;

    if (table.id === 'table-velocity') {
        // Toggle Rank vs Customer and Latest Sold vs Customer Order columns based on search state
        const rankHeaders = table.querySelectorAll('.rank-header');
        const buyerHeaders = table.querySelectorAll('.buyer-header');
        const rankCells = table.querySelectorAll('.rank-cell');
        const buyerCells = table.querySelectorAll('.buyer-cell');

        const stockHeaders = table.querySelectorAll('.stock-header');
        const orderHeaders = table.querySelectorAll('.order-header');
        const stockCells = table.querySelectorAll('.stock-cell');
        const orderCells = table.querySelectorAll('.order-cell');

        rankHeaders.forEach(el => el.style.display = isSearchActive ? 'none' : '');
        buyerHeaders.forEach(el => el.style.display = isSearchActive ? '' : 'none');
        rankCells.forEach(el => el.style.display = isSearchActive ? 'none' : '');
        buyerCells.forEach(el => el.style.display = isSearchActive ? '' : 'none');

        stockHeaders.forEach(el => el.style.display = isSearchActive ? 'none' : '');
        orderHeaders.forEach(el => el.style.display = isSearchActive ? '' : 'none');
        stockCells.forEach(el => el.style.display = isSearchActive ? 'none' : '');
        orderCells.forEach(el => el.style.display = isSearchActive ? '' : 'none');
    }

    rows.forEach(row => {
        const searchText = row.getAttribute('data-search') || '';
        const inStock = parseInt(row.getAttribute('data-instock') || '1', 10);

        // Multi-keyword flexible matching: every keyword must be present
        const matchesSearch = !isSearchActive || queryWords.every(word => searchText.includes(word));
        const matchesStock = !showInStockOnly || inStock > 0;

        if (matchesSearch && matchesStock) {
            row.style.display = '';
            visibleCount++;
            highlightRowText(row, queryWords);
        } else {
            row.style.display = 'none';
            clearHighlight(row);
        }
    });

    let noResultsRow = table.querySelector('.no-results-row');
    if (visibleCount === 0) {
        if (!noResultsRow) {
            const cols = table.querySelectorAll('thead th').length;
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `<td colspan="${cols}" style="text-align: center; padding: 30px; font-style: italic; color: var(--text-secondary);">No records match the current filters.</td>`;
            table.querySelector('tbody').appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}

function handleSearch(val) {
    const clearBtn = document.getElementById('clear-search');
    if (clearBtn) clearBtn.style.display = val ? 'block' : 'none';
    filterActiveTable();
}

function clearSearchInput() {
    const searchInput = document.getElementById('trends-search');
    searchInput.value = '';
    handleSearch('');
    searchInput.focus();
}

function highlightRowText(row, queryWords) {
    clearHighlight(row);
    if (!queryWords || queryWords.length === 0) return;

    const cells = row.querySelectorAll('td');
    cells.forEach(cell => {
        highlightNodeWords(cell, queryWords);
    });
}

function highlightNodeWords(node, queryWords) {
    if (node.nodeType === 3) {
        const val = node.nodeValue;
        let earliestIndex = -1;
        let matchedWord = '';

        queryWords.forEach(word => {
            const idx = val.toLowerCase().indexOf(word);
            if (idx > -1 && (earliestIndex === -1 || idx < earliestIndex)) {
                earliestIndex = idx;
                matchedWord = word;
            }
        });

        if (earliestIndex > -1 && matchedWord) {
            const span = document.createElement('span');
            span.className = 'highlight-container';

            const before = val.substring(0, earliestIndex);
            const match = val.substring(earliestIndex, earliestIndex + matchedWord.length);
            const after = val.substring(earliestIndex + matchedWord.length);

            const txtBefore = document.createTextNode(before);
            const mark = document.createElement('mark');
            mark.className = 'match-highlight';
            mark.appendChild(document.createTextNode(match));
            const txtAfter = document.createTextNode(after);

            span.appendChild(txtBefore);
            span.appendChild(mark);
            span.appendChild(txtAfter);

            node.parentNode.replaceChild(span, node);
            highlightNodeWords(txtAfter, queryWords);
        }
    } else if (node.nodeType === 1 && node.childNodes && !node.classList.contains('match-highlight') && node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE') {
        const children = Array.from(node.childNodes);
        children.forEach(child => {
            highlightNodeWords(child, queryWords);
        });
    }
}

function clearHighlight(row) {
    const highlights = row.querySelectorAll('.highlight-container');
    highlights.forEach(hl => {
        const textNode = document.createTextNode(hl.textContent);
        hl.parentNode.replaceChild(textNode, hl);
    });
}

function sortTable(tableId, colIndex, type) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results-row)'));
    const headers = table.querySelectorAll('thead th');
    const clickedHeader = headers[colIndex];

    const isAsc = !clickedHeader.classList.contains('sort-asc');

    headers.forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
    });

    clickedHeader.classList.add(isAsc ? 'sort-asc' : 'sort-desc');

    const searchInput = document.getElementById('trends-search');
    const isSearchActive = searchInput && searchInput.value.trim().length > 0;

    rows.sort((a, b) => {
        const cellA = a.cells[colIndex];
        const cellB = b.cells[colIndex];

        let valA, valB;
        let currentType = type;

        if (tableId === 'table-velocity') {
            if (colIndex === 0) {
                if (isSearchActive) {
                    currentType = 'str';
                    const buyerA = cellA.querySelector('.buyer-cell');
                    const buyerB = cellB.querySelector('.buyer-cell');
                    valA = buyerA ? buyerA.textContent.trim() : '';
                    valB = buyerB ? buyerB.textContent.trim() : '';
                } else {
                    currentType = 'num';
                    const rankA = cellA.querySelector('.rank-cell');
                    const rankB = cellB.querySelector('.rank-cell');
                    valA = rankA ? rankA.textContent.trim().replace('#', '') : '';
                    valB = rankB ? rankB.textContent.trim().replace('#', '') : '';
                }
            } else if (colIndex === 4) {
                if (isSearchActive) {
                    currentType = 'str';
                    const orderA = cellA.querySelector('.order-cell');
                    const orderB = cellB.querySelector('.order-cell');
                    valA = orderA ? orderA.textContent.trim() : '';
                    valB = orderB ? orderB.textContent.trim() : '';
                } else {
                    currentType = 'date';
                    valA = cellA.getAttribute('data-sort-val') ?? '';
                    valB = cellB.getAttribute('data-sort-val') ?? '';
                }
            } else {
                valA = cellA.getAttribute('data-sort-val') ?? cellA.textContent.trim();
                valB = cellB.getAttribute('data-sort-val') ?? cellB.textContent.trim();
            }
        } else {
            valA = cellA.getAttribute('data-sort-val') ?? cellA.textContent.trim();
            valB = cellB.getAttribute('data-sort-val') ?? cellB.textContent.trim();
        }

        if (currentType === 'num') {
            valA = parseFloat(valA.replace(/[^0-9.-]/g, '')) || 0;
            valB = parseFloat(valB.replace(/[^0-9.-]/g, '')) || 0;
        } else if (currentType === 'date') {
            valA = new Date(valA).getTime() || 0;
            valB = new Date(valB).getTime() || 0;
        } else {
            valA = valA.toLowerCase();
            valB = valB.toLowerCase();
        }

        if (valA < valB) return isAsc ? -1 : 1;
        if (valA > valB) return isAsc ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}

// Pricing curves charts initialization
document.addEventListener("DOMContentLoaded", () => {
    initializePricingCharts();
    initializeCpuCharts();
});

function initializeCpuCharts() {
    const cpuData = <?= json_encode($cpu_distribution) ?>;
    if (!cpuData || cpuData.length === 0) return;

    const labels = cpuData.map(d => d.cpu);
    const quantities = cpuData.map(d => parseInt(d.total_qty || 0, 10));

    const baseColors = {
        'Core 2 Duo': '#94a3b8',
        '2nd & 3rd Gen': '#cbd5e1',
        '4th & 5th Gen': '#64748b',
        '6th & 7th Gen': '#475569',
        'Apple': '#a855f7',
        'Ryzen': '#f97316'
    };

    const categoryColors = { ...baseColors };
    const gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
    const tiers = ['i3', 'i5', 'i7'];
    const genHue = {
        '8th': '#93c5fd',
        '9th': '#60a5fa',
        '10th': '#3b82f6',
        '11th': '#2563eb',
        '12th': '#1d4ed8',
        '13th': '#1e3a8a',
        '14th': '#0f172a'
    };

    gens.forEach(gen => {
        tiers.forEach(tier => {
            categoryColors[`${gen} Gen ${tier}`] = genHue[gen];
        });
    });

    const colors = labels.map(label => categoryColors[label] || '#a1a1aa');

    const ctxCpu = document.getElementById('cpuBrandChart')?.getContext('2d');
    if (ctxCpu) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const styles = getComputedStyle(document.documentElement);
        const textSecondary = styles.getPropertyValue('--text-secondary').trim() || (isDark ? '#cbd5e1' : '#4b5563');

        const cpuBrandChart = new Chart(ctxCpu, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: quantities,
                    backgroundColor: colors,
                    borderWidth: isDark ? 2 : 1,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11 },
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.parsed;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ' ' + context.label + ': ' + val.toLocaleString() + ' units (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        const observer = new MutationObserver(() => {
            const isDarkNew = document.documentElement.getAttribute('data-theme') === 'dark';
            const stylesNew = getComputedStyle(document.documentElement);
            const textSecNew = stylesNew.getPropertyValue('--text-secondary').trim() || (isDarkNew ? '#cbd5e1' : '#4b5563');

            cpuBrandChart.options.plugins.legend.labels.color = textSecNew;
            cpuBrandChart.data.datasets[0].borderColor = isDarkNew ? '#1e293b' : '#ffffff';
            cpuBrandChart.data.datasets[0].borderWidth = isDarkNew ? 2 : 1;
            cpuBrandChart.update();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }
}

function initializePricingCharts() {
    const priceData = <?= json_encode(array_reverse(array_slice($price_history, 0, 12))) ?>;
    if (!priceData || priceData.length === 0) return;

    const labels = priceData.map(d => d.sales_month);
    const avgPrices = priceData.map(d => parseFloat(d.avg_price));
    const valuations = priceData.map(d => parseFloat(d.total_valuation || (d.avg_price * d.total_qty)));

    const getThemeColors = () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const styles = getComputedStyle(document.documentElement);
        const accent = styles.getPropertyValue('--accent-color').trim() || (isDark ? '#a3e635' : '#8cc63f');
        const textMain = styles.getPropertyValue('--text-main').trim() || (isDark ? '#f8fafc' : '#0f172a');
        const textSecondary = styles.getPropertyValue('--text-secondary').trim() || (isDark ? '#cbd5e1' : '#4b5563');
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
        return { accent, textMain, textSecondary, gridColor };
    };

    let colors = getThemeColors();

    // ASP Line Chart
    const canvasAsp = document.getElementById('aspChart');
    if (!canvasAsp) return;
    const ctxAsp = canvasAsp.getContext('2d');
    let aspChart;
    if (ctxAsp) {
        const gradient = ctxAsp.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, colors.accent + '33');
        gradient.addColorStop(1, colors.accent + '00');

        aspChart = new Chart(ctxAsp, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avg Selling Price ($)',
                    data: avgPrices,
                    borderColor: colors.accent,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.accent,
                    pointBorderColor: '#fff',
                    pointHoverRadius: 7,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Avg Price: $' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: colors.gridColor },
                        ticks: {
                            color: colors.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 10 },
                            callback: function(value) { return '$' + value; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: colors.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 10 }
                        }
                    }
                }
            }
        });
    }

    // Valuation Bar Chart
    const canvasVal = document.getElementById('valuationChart');
    if (!canvasVal) return;
    const ctxVal = canvasVal.getContext('2d');
    let valuationChart;
    if (ctxVal) {
        valuationChart = new Chart(ctxVal, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Valuation ($)',
                    data: valuations,
                    backgroundColor: '#3b82f6',
                    hoverBackgroundColor: '#2563eb',
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Valuation: $' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: colors.gridColor },
                        ticks: {
                            color: colors.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 10 },
                            callback: function(value) {
                                if (value >= 1000) return '$' + (value / 1000) + 'k';
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: colors.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 10 }
                        }
                    }
                }
            }
        });
    }

    // Observe theme switch to update colors dynamically
    const observer = new MutationObserver(() => {
        const newColors = getThemeColors();
        if (aspChart) {
            aspChart.options.scales.y.grid.color = newColors.gridColor;
            aspChart.options.scales.y.ticks.color = newColors.textSecondary;
            aspChart.options.scales.x.ticks.color = newColors.textSecondary;
            aspChart.data.datasets[0].borderColor = newColors.accent;
            aspChart.data.datasets[0].pointBackgroundColor = newColors.accent;

            const newGrad = ctxAsp.createLinearGradient(0, 0, 0, 260);
            newGrad.addColorStop(0, newColors.accent + '33');
            newGrad.addColorStop(1, newColors.accent + '00');
            aspChart.data.datasets[0].backgroundColor = newGrad;

            aspChart.update();
        }
        if (valuationChart) {
            valuationChart.options.scales.y.grid.color = newColors.gridColor;
            valuationChart.options.scales.y.ticks.color = newColors.textSecondary;
            valuationChart.options.scales.x.ticks.color = newColors.textSecondary;
            valuationChart.update();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
}

// --- DYNAMIC DRAGGABLE WIDGET BOARD MANAGER ---
const WIDGET_METRICS_DATA = {
    'units_sold': { emoji: '📦', title: 'Total Units Sold', value: '<?= number_format($totals['total_qty']) ?>' },
    'orders_count': { emoji: '📝', title: 'Total Orders', value: '<?= number_format($totals['total_orders']) ?>' },
    'avg_value': { emoji: '💵', title: 'Avg. Order Value', value: '$<?= number_format($totals['avg_order_val'], 2) ?>' },
    'top_buyer': { emoji: '🤝', title: 'Top Buyer', value: '<?= htmlspecialchars($top_buyer_name) ?> (<?= number_format($top_buyer_qty) ?> units)' },
    'popular_brand': { emoji: '👑', title: 'Most Popular Brand', value: '<?= htmlspecialchars($popular_brand) ?> (<?= number_format($popular_brand_qty) ?> units)' },
    'peak_month': { emoji: '🔥', title: 'Peak Sales Month', value: '📅 <?= htmlspecialchars($peak_month) ?>' },
    'ryzen_qty': { emoji: '💻', title: 'Ryzen Units Sold', value: '<?= number_format($total_ryzen_sold) ?> units' }
};

const DEFAULT_WIDGET_CONFIG = [
    { id: 'units_sold', type: 'metric', visible: true },
    { id: 'orders_count', type: 'metric', visible: true },
    { id: 'avg_value', type: 'metric', visible: true },
    { id: 'top_buyer', type: 'metric', visible: false },
    { id: 'popular_brand', type: 'metric', visible: false },
    { id: 'peak_month', type: 'metric', visible: false },
    { id: 'ryzen_qty', type: 'metric', visible: false }
];

let widgetsConfig = [];
let dragSourceEl = null;

// Initialize
document.addEventListener("DOMContentLoaded", () => {
    loadWidgetConfig();
    renderWidgetToggles();
    renderWidgetBoard();
});

function loadWidgetConfig() {
    const saved = localStorage.getItem('trends_widgets_config');
    if (saved) {
        try {
            widgetsConfig = JSON.parse(saved);
        } catch (e) {
            widgetsConfig = [...DEFAULT_WIDGET_CONFIG];
        }
    } else {
        widgetsConfig = [...DEFAULT_WIDGET_CONFIG];
    }
    
    // Ensure all default metrics are in config
    DEFAULT_WIDGET_CONFIG.forEach(def => {
        if (!widgetsConfig.some(w => w.id === def.id)) {
            widgetsConfig.push(def);
        }
    });

    // Handle board visibility state
    const boardVisible = localStorage.getItem('trends_widgets_board_visible') === 'true';
    const board = document.getElementById('widget-board');
    const toggleBtn = document.getElementById('toggle-widgets-btn');
    const configBtn = document.getElementById('config-widgets-btn');
    
    if (boardVisible) {
        board.style.display = 'grid';
        toggleBtn.textContent = '🙈 Hide Summary Cards';
        toggleBtn.classList.remove('dark');
        configBtn.style.display = 'inline-block';
    } else {
        board.style.display = 'none';
        toggleBtn.textContent = '📊 Show Summary Cards';
        toggleBtn.classList.add('dark');
        configBtn.style.display = 'none';
        document.getElementById('widgets-config-panel').style.display = 'none';
    }
}

function saveWidgetConfig() {
    localStorage.setItem('trends_widgets_config', JSON.stringify(widgetsConfig));
}

function toggleWidgetBoard() {
    const board = document.getElementById('widget-board');
    const toggleBtn = document.getElementById('toggle-widgets-btn');
    const configBtn = document.getElementById('config-widgets-btn');
    const isHidden = board.style.display === 'none';

    if (isHidden) {
        board.style.display = 'grid';
        toggleBtn.textContent = '🙈 Hide Summary Cards';
        toggleBtn.classList.remove('dark');
        configBtn.style.display = 'inline-block';
        localStorage.setItem('trends_widgets_board_visible', 'true');
    } else {
        board.style.display = 'none';
        toggleBtn.textContent = '📊 Show Summary Cards';
        toggleBtn.classList.add('dark');
        configBtn.style.display = 'none';
        document.getElementById('widgets-config-panel').style.display = 'none';
        localStorage.setItem('trends_widgets_board_visible', 'false');
    }
}

function toggleConfigPanel() {
    const panel = document.getElementById('widgets-config-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function renderWidgetToggles() {
    const container = document.getElementById('widget-toggles-container');
    if (!container) return;
    container.innerHTML = '';

    widgetsConfig.forEach(item => {
        if (item.type !== 'metric') return;
        const data = WIDGET_METRICS_DATA[item.id];
        if (!data) return;

        const label = document.createElement('label');
        label.style.cssText = 'display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; background: var(--bg-surface-2); padding: 8px 14px; border-radius: 20px; border: 1px solid var(--border-color);';
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.style.cssText = 'width: 16px; height: 16px; margin: 0; cursor: pointer;';
        checkbox.checked = item.visible;
        checkbox.addEventListener('change', () => {
            item.visible = checkbox.checked;
            saveWidgetConfig();
            renderWidgetBoard();
        });

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(' ' + data.emoji + ' ' + data.title));
        container.appendChild(label);
    });
}

function renderWidgetBoard() {
    const board = document.getElementById('widget-board');
    if (!board) return;
    board.innerHTML = '';

    widgetsConfig.forEach(item => {
        if (!item.visible) return;

        const card = document.createElement('div');
        card.className = 'trend-card';
        card.setAttribute('draggable', 'true');
        card.setAttribute('data-id', item.id);
        card.style.cssText = 'position: relative; align-items: center; text-align: center; gap: 8px; cursor: grab; padding: 20px;';

        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragover', handleDragOver);
        card.addEventListener('dragenter', handleDragEnter);
        card.addEventListener('dragleave', handleDragLeave);
        card.addEventListener('drop', handleDrop);
        card.addEventListener('dragend', handleDragEnd);

        if (item.type !== 'metric') {
            const delBtn = document.createElement('button');
            delBtn.innerHTML = '✕';
            delBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1rem; font-weight: bold; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;';
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeWidget(item.id);
            });
            card.appendChild(delBtn);
        }

        if (item.type === 'metric') {
            const data = WIDGET_METRICS_DATA[item.id];
            if (!data) return;
            card.innerHTML += `
                <div style="font-size: 2rem;">${data.emoji}</div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${data.title}</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: ${item.id === 'avg_value' ? 'var(--accent-color)' : 'var(--text-main)'};">${data.value}</div>
            `;
        } else if (item.type === 'note') {
            const noteText = item.text || '';
            card.innerHTML += `
                <div style="font-size: 2rem;">📝</div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${item.title || 'Note'}</div>
                <textarea style="width: 100%; height: 80px; background: var(--bg-surface-2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); font-family: inherit; font-size: 0.85rem; padding: 8px; resize: none; outline: none; margin-top: 4px;" 
                          placeholder="Type your notes here..." 
                          oninput="updateNoteText('${item.id}', this.value)">${noteText}</textarea>
            `;
        } else if (item.type === 'custom') {
            if (item.editing) {
                const POPULAR_EMOJIS = ['⭐', '📦', '📝', '💵', '🤝', '👑', '🔥', '💻', '📈', '📉', '🎯', '🚀', '💡', '🛡️', '📅', '👥', '🔋', '🔌', '🖥️', '⌨️', '🖱️', '💾', '💿', '🖨️', '⚙️', '🔔', '❤️', '👍', '🏆', '🎉'];
                let emojiButtonsHtml = '';
                POPULAR_EMOJIS.forEach(emo => {
                    emojiButtonsHtml += `<button type="button" style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.15s;" onclick="selectPickerEmoji(event, '${item.id}', '${emo}')" onmouseover="this.style.background='var(--bg-surface-2)'" onmouseout="this.style.background='transparent'">${emo}</button>`;
                });

                card.innerHTML += `
                    <div style="display: flex; flex-direction: column; gap: 8px; width: 100%; text-align: left;">
                        <div style="display: flex; gap: 6px; position: relative;">
                            <div style="position: relative; display: inline-block;">
                                <input type="text" id="cust-emoji-${item.id}" value="${item.emoji || '⭐'}" placeholder="Emoji" style="width: 50px; text-align: center; height: 36px; padding: 0;" onclick="toggleEmojiPicker(event, '${item.id}')">
                                <div id="emoji-picker-${item.id}" style="display: none; position: absolute; top: calc(100% + 5px); left: 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: var(--border-radius-md); box-shadow: var(--shadow-lg); padding: 8px; width: 180px; z-index: 1000; grid-template-columns: repeat(5, 1fr); gap: 4px;">
                                    ${emojiButtonsHtml}
                                </div>
                            </div>
                            <input type="text" id="cust-title-${item.id}" value="${item.title || ''}" placeholder="Title" style="flex: 1; height: 36px; padding: 0 8px;">
                        </div>
                        <input type="text" id="cust-val-${item.id}" value="${item.value || ''}" placeholder="Value" style="width: 100%; height: 36px; padding: 0 8px;">
                        <button type="button" class="btn-main" onclick="saveCustomMetricCard('${item.id}')" style="height: 32px; padding: 0; font-size: 0.8rem; border-radius: 6px; box-shadow: none;">Save Card</button>
                    </div>
                `;
            } else {
                card.innerHTML += `
                    <div style="font-size: 2rem;">${item.emoji || '⭐'}</div>
                    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${item.title || 'Custom Metric'}</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: var(--text-main);">${item.value || '0'}</div>
                    <button type="button" style="background: transparent; border: none; color: var(--accent-color); font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-top: 4px;" onclick="editCustomMetricCard('${item.id}')">✏️ Edit</button>
                `;
            }
        }

        board.appendChild(card);
    });

    if (board.children.length === 0) {
        board.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 30px; border: 1px dashed var(--border-color); border-radius: var(--border-radius-lg); color: var(--text-secondary); font-style: italic;">No summary cards are currently visible. Click "Customize Board" to add cards.</div>`;
    }
}

function toggleEmojiPicker(event, id) {
    event.stopPropagation();
    document.querySelectorAll('[id^="emoji-picker-"]').forEach(el => {
        if (el.id !== `emoji-picker-${id}`) el.style.display = 'none';
    });
    const picker = document.getElementById(`emoji-picker-${id}`);
    if (picker) {
        picker.style.display = picker.style.display === 'grid' ? 'none' : 'grid';
    }
}

function selectPickerEmoji(event, id, emoji) {
    event.stopPropagation();
    const input = document.getElementById(`cust-emoji-${id}`);
    if (input) input.value = emoji;
    const picker = document.getElementById(`emoji-picker-${id}`);
    if (picker) picker.style.display = 'none';
}

// Global click handler to dismiss pickers
document.addEventListener('click', () => {
    document.querySelectorAll('[id^="emoji-picker-"]').forEach(el => {
        el.style.display = 'none';
    });
});

function updateNoteText(id, val) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.text = val;
        saveWidgetConfig();
    }
}

function addNewNoteCard() {
    const newId = 'note_' + Math.random().toString(36).substr(2, 9);
    widgetsConfig.push({
        id: newId,
        type: 'note',
        visible: true,
        title: 'Board Note',
        text: ''
    });
    saveWidgetConfig();
    renderWidgetBoard();
}

function addNewCustomMetricCard() {
    const newId = 'custom_' + Math.random().toString(36).substr(2, 9);
    widgetsConfig.push({
        id: newId,
        type: 'custom',
        visible: true,
        emoji: '⭐',
        title: 'New Metric',
        value: '0',
        editing: true
    });
    saveWidgetConfig();
    renderWidgetBoard();
}

function saveCustomMetricCard(id) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.emoji = document.getElementById(`cust-emoji-${id}`).value || '⭐';
        item.title = document.getElementById(`cust-title-${id}`).value || 'Custom Metric';
        item.value = document.getElementById(`cust-val-${id}`).value || '0';
        item.editing = false;
        saveWidgetConfig();
        renderWidgetBoard();
    }
}

function editCustomMetricCard(id) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.editing = true;
        renderWidgetBoard();
    }
}

function removeWidget(id) {
    widgetsConfig = widgetsConfig.filter(w => w.id !== id);
    saveWidgetConfig();
    renderWidgetBoard();
}

function resetWidgetsToDefault() {
    if (confirm("Reset layout, custom metrics, and notes back to system defaults?")) {
        widgetsConfig = JSON.parse(JSON.stringify(DEFAULT_WIDGET_CONFIG));
        saveWidgetConfig();
        renderWidgetToggles();
        renderWidgetBoard();
    }
}

function handleDragStart(e) {
    dragSourceEl = this;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', this.getAttribute('data-id'));
    this.style.opacity = '0.4';
    this.style.border = '2px dashed var(--accent-color)';
}

function handleDragOver(e) {
    if (e.preventDefault) e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragEnter(e) {
    this.style.border = '2px dashed var(--accent-color)';
}

function handleDragLeave(e) {
    this.style.border = '1px solid var(--border-color)';
}

function handleDrop(e) {
    e.stopPropagation();
    const draggedId = e.dataTransfer.getData('text/plain');
    const targetId = this.getAttribute('data-id');

    if (draggedId && targetId && draggedId !== targetId) {
        const idxA = widgetsConfig.findIndex(w => w.id === draggedId);
        const idxB = widgetsConfig.findIndex(w => w.id === targetId);
        if (idxA > -1 && idxB > -1) {
            const temp = widgetsConfig[idxA];
            widgetsConfig[idxA] = widgetsConfig[idxB];
            widgetsConfig[idxB] = temp;
            saveWidgetConfig();
            renderWidgetBoard();
        }
    }
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '1';
    this.style.border = '1px solid var(--border-color)';
    const cols = document.querySelectorAll('#widget-board .trend-card');
    cols.forEach(col => col.style.border = '1px solid var(--border-color)');
}
</script>

<!-- Order Preview Modal -->
<div id="orderPreviewModal" class="modal-overlay no-print" onclick="if(event.target === this) closeOrderPreviewModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-box" onclick="event.stopPropagation()" style="background:var(--bg-panel); border-radius:20px; width:90%; max-width:650px; padding:25px; box-shadow:var(--shadow-lg); border: 1px solid var(--border-color); display:flex; flex-direction:column; max-height:85vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size: 1.5rem;">📦</span>
                <div>
                    <h3 id="preview-order-id" style="font-weight: 800; font-size: 1.25rem; margin:0; font-family: monospace; color: var(--text-main);">Order</h3>
                    <span id="preview-company-name" style="font-size: 0.85rem; font-weight: 700; color: var(--accent-color);">Account Name</span>
                </div>
            </div>
            <button type="button" onclick="closeOrderPreviewModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:var(--text-secondary); opacity:0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</button>
        </div>
        
        <div id="preview-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; gap: 15px;">
            <div class="preview-spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Loading manifest details...</span>
        </div>

        <div id="preview-error" style="display:none; text-align: center; padding: 30px 0; color: #ef4444; font-weight: 700;">
            ⚠️ Failed to load order details.
        </div>

        <div id="preview-body" style="display:none; overflow-y:auto; flex:1; padding-right:5px;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 20px; font-size: 0.85rem; background: var(--bg-surface-2); padding: 12px 16px; border-radius: 10px;">
                <div>
                    <span style="color:var(--text-secondary); font-weight: 600;">Status:</span>
                    <span id="preview-status" class="order-badge" style="font-weight: 800; text-transform: uppercase; margin-left: 5px;">Active</span>
                </div>
                <div>
                    <span style="color:var(--text-secondary); font-weight: 600;">Date Created:</span>
                    <span id="preview-date" style="font-weight: 700; color: var(--text-main); margin-left: 5px;">-</span>
                </div>
            </div>

            <table class="preview-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 800;">
                        <th style="padding: 10px 0;">Item Description</th>
                        <th style="padding: 10px 0; text-align: center; width: 60px;">Qty</th>
                        <th style="padding: 10px 0; text-align: right; width: 100px;">Price</th>
                        <th style="padding: 10px 0; text-align: right; width: 100px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="preview-items-list">
                    <!-- Items inserted dynamically -->
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <a id="preview-full-details-link" href="#" class="btn-main" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; text-decoration: none; background: var(--accent-color); color: white;">
                Edit Full Order →
            </a>
            <button type="button" onclick="closeOrderPreviewModal()" class="btn-main dark" style="padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; border: none; box-shadow: none;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- CPU Pricing Details Modal -->
<div id="cpuPricingModal" class="modal-overlay no-print" onclick="if(event.target === this) closeCpuPricingModal()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal-box" onclick="event.stopPropagation()" style="background:var(--bg-panel); border-radius:20px; width:90%; max-width:800px; padding:25px; box-shadow:var(--shadow-lg); border: 1px solid var(--border-color); display:flex; flex-direction:column; max-height:85vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size: 1.5rem;">💻</span>
                <div>
                    <h3 id="cpu-pricing-title" style="font-weight: 800; font-size: 1.25rem; margin:0; color: var(--text-main);">CPU Family Details</h3>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--accent-color);">Pricing, Models & Recent Sales</span>
                </div>
            </div>
            <button type="button" onclick="closeCpuPricingModal()" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:var(--text-secondary); opacity:0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">&times;</button>
        </div>
        
        <div id="cpu-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; gap: 15px;">
            <div class="preview-spinner" style="width: 40px; height: 40px; border: 4px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Loading CPU metrics...</span>
        </div>

        <div id="cpu-error" style="display:none; text-align: center; padding: 30px 0; color: #ef4444; font-weight: 700;">
            ⚠️ Failed to load CPU pricing details.
        </div>

        <div id="cpu-body" style="display:none; overflow-y:auto; flex:1; padding-right:5px;">
            <h4 style="margin-top: 0; margin-bottom: 10px; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">Model Pricing Summary</h4>
            <div class="trends-table-container" style="margin-bottom: 25px; max-height: 250px; overflow-y: auto;">
                <table class="preview-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 800;">
                            <th style="padding: 10px 5px;">Model</th>
                            <th style="padding: 10px 5px; text-align: center;">Units</th>
                            <th style="padding: 10px 5px; text-align: right;">Min Price</th>
                            <th style="padding: 10px 5px; text-align: right;">Max Price</th>
                            <th style="padding: 10px 5px; text-align: right;">Avg Price</th>
                        </tr>
                    </thead>
                    <tbody id="cpu-models-list">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>

            <h4 style="margin-bottom: 10px; font-weight: 800; font-size: 0.95rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px;">Latest Sales Transactions</h4>
            <div class="trends-table-container" style="max-height: 250px; overflow-y: auto;">
                <table class="preview-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 800;">
                            <th style="padding: 10px 5px;">Date</th>
                            <th style="padding: 10px 5px;">Customer</th>
                            <th style="padding: 10px 5px;">Model / Spec</th>
                            <th style="padding: 10px 5px; text-align: center;">Qty</th>
                            <th style="padding: 10px 5px; text-align: right;">Unit Price</th>
                            <th style="padding: 10px 5px; text-align: right;">Order</th>
                        </tr>
                    </thead>
                    <tbody id="cpu-sales-list">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeCpuPricingModal()" class="btn-main dark" style="padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.85rem; border: none; box-shadow: none;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
.clickable-row {
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.1s ease;
}
.clickable-row:hover {
    background-color: var(--bg-surface-2) !important;
}
.clickable-row:active {
    transform: scale(0.995);
}
.order-preview-link {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 700;
    transition: all 0.15s ease;
}
.order-preview-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
const currentTrendsFilter = '<?= htmlspecialchars($filter) ?>';
let activeOrderPreviewEscHandler = null;
let activeCpuPricingEscHandler = null;

function openOrderPreviewModal(event, orderId) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const modal = document.getElementById('orderPreviewModal');
    const loading = document.getElementById('preview-loading');
    const error = document.getElementById('preview-error');
    const body = document.getElementById('preview-body');

    document.getElementById('preview-order-id').innerText = orderId;
    document.getElementById('preview-company-name').innerText = 'Loading...';

    loading.style.display = 'flex';
    error.style.display = 'none';
    body.style.display = 'none';
    modal.style.display = 'flex';

    const localEscapeHTML = (str) => {
        if (!str) return '—';
        return str.toString().replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m]));
    };

    fetch(`api/get_order_details.php?order_id=${encodeURIComponent(orderId)}`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load');
            return res.json();
        })
        .then(data => {
            document.getElementById('preview-company-name').innerText = data.order.company_name || 'Unknown Account';
            document.getElementById('preview-date').innerText = new Date(data.order.created_at.replace(/-/g, "/")).toLocaleDateString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric'
            });

            // Set badge status class
            const statusEl = document.getElementById('preview-status');
            statusEl.innerText = data.order.status;
            statusEl.className = 'order-badge status-' + data.order.status.toLowerCase();

            // Set link to full checkout/edit order details page
            const editLink = document.getElementById('preview-full-details-link');
            editLink.href = `checkout.php?customer_id=${encodeURIComponent(data.order.customer_id)}&order_id=${encodeURIComponent(data.order.order_id)}`;

            // Populate table items
            const list = document.getElementById('preview-items-list');
            list.innerHTML = '';
            let grandTotal = 0;

            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    const qty = parseFloat(item.quantity) || 0;
                    const price = parseFloat(item.unit_price) || 0;
                    const subtotal = qty * price;
                    grandTotal += subtotal;

                    const desc = [item.series, item.cpu].filter(v => v && v !== 'N/A').join(' / ') || item.description || '';
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border-color)';
                    tr.innerHTML = `
                        <td style="padding: 12px 0;">
                            <div style="font-weight: 700; color: var(--text-main);">${localEscapeHTML(item.brand)} ${localEscapeHTML(item.model)}</div>
                            ${desc ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">${localEscapeHTML(desc)}</div>` : ''}
                        </td>
                        <td style="padding: 12px 0; text-align: center; font-weight: 700; color: var(--text-main);">${qty}</td>
                        <td style="padding: 12px 0; text-align: right; font-weight: 600; color: var(--text-secondary);">$${price.toFixed(2)}</td>
                        <td style="padding: 12px 0; text-align: right; font-weight: 700; color: var(--text-main);">$${subtotal.toFixed(2)}</td>
                    `;
                    list.appendChild(tr);
                });
            } else {
                list.innerHTML = `<tr><td colspan="4" style="padding: 30px; text-align: center; color: var(--text-secondary); font-style: italic;">No items in this batch.</td></tr>`;
            }

            // Total row
            const totalTr = document.createElement('tr');
            totalTr.style.fontWeight = '800';
            totalTr.innerHTML = `
                <td style="padding: 15px 0; font-size: 0.95rem; color: var(--text-main);">Total Valuation</td>
                <td></td>
                <td></td>
                <td style="padding: 15px 0; text-align: right; font-size: 1rem; color: var(--accent-color);">$${grandTotal.toFixed(2)}</td>
            `;
            list.appendChild(totalTr);

            loading.style.display = 'none';
            body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            document.getElementById('preview-company-name').innerText = 'Error';
            loading.style.display = 'none';
            error.style.display = 'block';
        });

    activeOrderPreviewEscHandler = (e) => {
        if (e.key === 'Escape') closeOrderPreviewModal();
    };
    window.addEventListener('keydown', activeOrderPreviewEscHandler);
}

function closeOrderPreviewModal() {
    const modal = document.getElementById('orderPreviewModal');
    modal.style.display = 'none';
    if (activeOrderPreviewEscHandler) {
        window.removeEventListener('keydown', activeOrderPreviewEscHandler);
        activeOrderPreviewEscHandler = null;
    }
}

function openCpuPricingModal(cpuCategory) {
    const modal = document.getElementById('cpuPricingModal');
    const loading = document.getElementById('cpu-loading');
    const error = document.getElementById('cpu-error');
    const body = document.getElementById('cpu-body');

    document.getElementById('cpu-pricing-title').innerText = cpuCategory;

    loading.style.display = 'flex';
    error.style.display = 'none';
    body.style.display = 'none';
    modal.style.display = 'flex';

    const localEscapeHTML = (str) => {
        if (!str) return '—';
        return str.toString().replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m]));
    };

    fetch(`api/get_cpu_pricing_details.php?cpu=${encodeURIComponent(cpuCategory)}&filter=${encodeURIComponent(currentTrendsFilter)}`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load');
            return res.json();
        })
        .then(data => {
            // Populate models table
            const modelsList = document.getElementById('cpu-models-list');
            modelsList.innerHTML = '';
            if (data.models && data.models.length > 0) {
                data.models.forEach(model => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border-color)';
                    tr.innerHTML = `
                        <td style="padding: 10px 5px;">
                            <strong>${localEscapeHTML(model.brand)}</strong> ${localEscapeHTML(model.model)}
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">${localEscapeHTML(model.series)}</div>
                        </td>
                        <td style="padding: 10px 5px; text-align: center; font-weight: 700;">${model.total_qty}</td>
                        <td style="padding: 10px 5px; text-align: right; color: #10b981;">$${parseFloat(model.min_price).toFixed(2)}</td>
                        <td style="padding: 10px 5px; text-align: right; color: #3b82f6;">$${parseFloat(model.max_price).toFixed(2)}</td>
                        <td style="padding: 10px 5px; text-align: right; font-weight: 700;">$${parseFloat(model.avg_price).toFixed(2)}</td>
                    `;
                    modelsList.appendChild(tr);
                });
            } else {
                modelsList.innerHTML = `<tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--text-secondary); font-style: italic;">No model statistics found.</td></tr>`;
            }

            // Populate sales table
            const salesList = document.getElementById('cpu-sales-list');
            salesList.innerHTML = '';
            if (data.recent_sales && data.recent_sales.length > 0) {
                data.recent_sales.forEach(sale => {
                    const desc = [sale.series, sale.cpu].filter(v => v && v !== 'N/A').join(' / ') || sale.description || '';
                    const dateObj = new Date(sale.created_at.replace(/-/g, "/"));
                    const formattedDate = dateObj.toLocaleDateString(undefined, {
                        year: 'numeric', month: 'short', day: 'numeric'
                    });
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border-color)';
                    tr.innerHTML = `
                        <td style="padding: 10px 5px; font-size: 0.8rem; white-space: nowrap;">${formattedDate}</td>
                        <td style="padding: 10px 5px; font-weight: 600; color: var(--accent-color); font-size: 0.85rem;">${localEscapeHTML(sale.company_name)}</td>
                        <td style="padding: 10px 5px;">
                            <span style="font-weight: 700;">${localEscapeHTML(sale.brand)} ${localEscapeHTML(sale.model)}</span>
                            ${desc ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 1px;">${localEscapeHTML(desc)}</div>` : ''}
                        </td>
                        <td style="padding: 10px 5px; text-align: center; font-weight: 700;">${sale.quantity}</td>
                        <td style="padding: 10px 5px; text-align: right; font-weight: 600;">$${parseFloat(sale.unit_price).toFixed(2)}</td>
                        <td style="padding: 10px 5px; text-align: right; font-family: monospace;">
                            <a href="#" onclick="openOrderPreviewModal(event, '${localEscapeHTML(sale.order_id)}')" class="order-preview-link"><code>${localEscapeHTML(sale.order_id)}</code></a>
                        </td>
                    `;
                    salesList.appendChild(tr);
                });
            } else {
                salesList.innerHTML = `<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-secondary); font-style: italic;">No recent transactions.</td></tr>`;
            }

            loading.style.display = 'none';
            body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            loading.style.display = 'none';
            error.style.display = 'block';
        });

    activeCpuPricingEscHandler = (e) => {
        if (e.key === 'Escape') closeCpuPricingModal();
    };
    window.addEventListener('keydown', activeCpuPricingEscHandler);
}

function closeCpuPricingModal() {
    const modal = document.getElementById('cpuPricingModal');
    modal.style.display = 'none';
    if (activeCpuPricingEscHandler) {
        window.removeEventListener('keydown', activeCpuPricingEscHandler);
        activeCpuPricingEscHandler = null;
    }
}

function updateMatrixCell(category, cpu_gen, grade, price) {
    const parsedPrice = parseFloat(price);
    const sanitizedPrice = isNaN(parsedPrice) ? 0.00 : parsedPrice;
    
    fetch('index.php?view=trends&action=update_pricing_matrix', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            category: category,
            cpu_gen: cpu_gen,
            grade: grade,
            price: sanitizedPrice
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (window.IQA_Notify && typeof window.IQA_Notify.success === 'function') {
                window.IQA_Notify.success(`Successfully updated ${category} - ${cpu_gen === 'Default' ? '' : cpu_gen + ' - '}${grade} to $${sanitizedPrice.toFixed(2)}`);
            }
        } else {
            if (window.IQA_Notify && typeof window.IQA_Notify.error === 'function') {
                window.IQA_Notify.error(data.error || 'Failed to update pricing rule');
            } else {
                alert(data.error || 'Failed to update pricing rule');
            }
        }
    })
    .catch(error => {
        console.error('Error updating pricing rule:', error);
        if (window.IQA_Notify && typeof window.IQA_Notify.error === 'function') {
            window.IQA_Notify.error('Error connecting to server. Please try again.');
        } else {
            alert('Error connecting to server. Please try again.');
        }
    });
}
</script>
