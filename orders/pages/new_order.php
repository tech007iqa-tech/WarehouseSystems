<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Create connection to SQLite database
    $conn = Database::orders();

    // NEW! Ensure orders table exists for tracking multiple batches
    $conn->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT NOT NULL UNIQUE,
        customer_id TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure items table supports grouping by order_id
    $conn->exec("CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT',
        customer_id TEXT NOT NULL,
        brand TEXT NOT NULL,
        model TEXT NOT NULL,
        series TEXT NOT NULL,
        description TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price REAL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: Check if we need to add order_id to existing table
    $columns = $conn->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC);
    $has_order_id = false;
    foreach($columns as $col) {
        if ($col['name'] === 'order_id') $has_order_id = true;
    }

    if (!$has_order_id) {
        $conn->exec("ALTER TABLE items ADD COLUMN order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT'");
    }

    // Migration Check (for CPU/Gen support)
    $has_cpu = false;
    foreach($columns as $col) {
        if ($col['name'] === 'cpu') $has_cpu = true;
    }
    if (!$has_cpu) {
        $conn->exec("ALTER TABLE items ADD COLUMN cpu TEXT DEFAULT ''");
    }

    // Handle Form Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $delete_id = $_POST['delete_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $_SESSION['message'] = "<div class='alert success'>Item removed from order.</div>";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_item') {
            $update_id = $_POST['update_id'] ?? 0;
            $qty = $_POST['update_qty'] ?? 1;
            $price = $_POST['update_price'] ?? 0.00;
            $stmt = $conn->prepare("UPDATE items SET quantity = ?, unit_price = ? WHERE id = ?");
            if ($stmt->execute([$qty, $price, $update_id])) {
                $_SESSION['message'] = "<div class='alert success'>Item updated.</div>";
            }
        } else {
            $brand = $_POST['brand'] ?? '';
            $models = $_POST['models'] ?? '';
            $series = $_POST['series'] ?? '';
            $cpu = $_POST['cpu'] ?? '';
            $description = $_POST['description'] ?? '';
            $qty = $_POST['qty'] ?? 1;
            $price = $_POST['price'] ?? 0.00;
            $order_num = $_POST['order_id'] ?? 'ORD-DEFAULT';
            $customer_id = $_POST['customer_id'] ?? 'Anonymous';

            $stmt = $conn->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt->execute([$order_num, $customer_id, $brand, $models, $series, $cpu, $description, $qty, $price])) {
                $_SESSION['message'] = "<div class='alert success'>Item added to batch <strong>{$order_num}</strong>!</div>";
            } else {
                $_SESSION['message'] = "<div class='alert error'>Error adding item.</div>";
            }
        }

        // PRG Pattern
        $cust_param = urlencode($_POST['customer_id'] ?? $current_customer);
        $order_param = urlencode($_POST['order_id'] ?? $current_order);

        $anchor = '';
        if (isset($_POST['action']) && in_array($_POST['action'], ['delete', 'update_item'])) {
            $anchor = '#order-summary';
        }

        header("Location: index.php?customer_id=" . $cust_param . "&order_id=" . $order_param . $anchor);
        exit();
    }
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

$message = $_SESSION['message'] ?? "";
unset($_SESSION['message']);
    // Fetch customer details for header
    $customer_name = 'Customer';
    try {
        $c_db = Database::customers();
        $c_stmt = $c_db->prepare("SELECT company_name FROM customers WHERE customer_id = ?");
        $c_stmt->execute([$current_customer]);
        $customer_name = $c_stmt->fetchColumn() ?: $current_customer;
    } catch(Exception $e) {}
?>

<!-- Load dedicated builder styles -->


<div class="form-side">
    <header>
        <h1>Active Batch Builder</h1>
        <p class="subtitle">Assigning items for <strong><?= htmlspecialchars($customer_name) ?></strong></p>
    </header>

    <?php echo $message; ?>

    <form action="" method="POST">
        <!-- Hidden Context -->
        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($current_customer) ?>">
        <input type="hidden" name="order_id" value="<?= htmlspecialchars($current_order) ?>">

        <a href="index.php" class="builder-back-link">← Switch Batch / Account</a>

        <div style="margin-bottom: 20px;">
            <button type="button" onclick="openWarehouseModal()" style="width: 100%; height: 44px; border-radius: 12px; border: 1px solid var(--accent-color); background: #f0fdf4; color: #166534; font-weight: 800; cursor: pointer; transition: all 0.2s;">
                🔍 Pick from Warehouse Stock
            </button>
        </div>

        <!-- Brand Selection Dropdown -->
        <div class="form-group">
            <label for="brand">Choose Brand*</label>
            <select id="brand" name="brand" required aria-label="Brand Selection">
                <option value="" selected disabled>— Select Brand —</option>
                <option value="Dell">Dell</option>
                <option value="HP">HP</option>
                <option value="Lenovo">Lenovo</option>
                <option value="Apple">Apple</option>
                <option value="Microsoft">Microsoft</option>
                <option value="MSI">MSI</option>
                <option value="Asus">Asus</option>
                <option value="Acer">Acer</option>
                <option value="Samsung">Samsung</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <!-- Main Models Searchable Selection -->
        <div class="form-group">
            <label for="models">Main Models*</label>
            <input list="model-options" id="models" name="models" placeholder="Type or select model..." required aria-label="Models Selection">
            <datalist id="model-options"></datalist>
        </div>

        <!-- Series Searchable Selection -->
        <div class="form-group">
            <label for="series">Series / Project ID*</label>
            <input list="series-options" id="series" name="series" placeholder="Type or select series..." required aria-label="Series Selection">
            <datalist id="series-options"></datalist>
        </div>

        <!-- CPU Selection -->
        <div class="form-group">
            <label for="cpu">CPU / Gen*</label>
            <input list="cpu-options" id="cpu" name="cpu" placeholder="e.g. Core i7 11th Gen" required aria-label="CPU Selection">
            <datalist id="cpu-options"></datalist>
        </div>

        <!-- Working Status Description -->
        <div class="form-group">
            <label for="description">Condition / Comments*</label>
            <input list="desc-options" id="description" name="description" placeholder="e.g. Working, Screen Damage" required aria-label="Condition Selection">
            <datalist id="desc-options">
                <option value="Tested">
                <option value="Untested">
                <option value="Parts">
            </datalist>
        </div>

        <!-- Quantity and Price -->
        <div class="builder-fields-row">
            <div class="form-group">
                <label for="qty">Quantity*</label>
                <input type="number" id="qty" name="qty" placeholder="1" value="1" min="1" required>
            </div>
            <div class="form-group">
                <label for="price">Unit Price ($)*</label>
                <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" required>
            </div>
        </div>
        <input type="submit" value="Add to Order">
    </form>
</div>

<div class="summary-side">
    <section class="item-list" id="order-summary">
        <h2>Order Summary</h2>
        <div style="margin-bottom: 15px;">
            <input type="text" id="summary-search" onkeyup="filterSummary()" placeholder="Search added items..." aria-label="Search items in this order" style="width: 100%; height: 40px; padding: 0 15px; border-radius: 10px; border: 1px solid var(--border-color); font-size: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="col-desc" style="padding-left: 0;">Item Description</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM items WHERE customer_id = ? AND order_id = ? ORDER BY id DESC");
                    $stmt->execute([$current_customer, $current_order]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($items) > 0) {
                        foreach($items as $row) {
                            echo "<tr class='summary-item-row'>
                                    <td class='col-desc' style='padding-left: 0;'>
                                        <div style='display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;'>
                                            <div class='copyable-text' style='flex: 1;'>
                                                <div style='font-weight: 700;'>" . htmlspecialchars($row['brand'] . " " . $row['model']) . "</div>
                                                <div style='font-size: 0.825rem; color: var(--text-secondary);'>" . htmlspecialchars($row['series']) . " | <span style='color: var(--accent-color); font-weight:800;'>" . htmlspecialchars($row['cpu'] ?? '') . "</span> | " . htmlspecialchars($row['description']) . "</div>
                                            </div>
                                            <div style='display: flex; flex-direction: column; gap: 4px; align-items: center;'>
                                                <button type='button' class='btn-copy no-print' onclick='copyEntry(this)' title='Copy Description' style='background: none; border: none; cursor: pointer; padding: 4px; font-size: 0.8rem; opacity: 0.3; transition: opacity 0.2s; flex-shrink: 0;'>
                                                    📋
                                                </button>
                                                <button type='button' class='btn-edit no-print' onclick='toggleInlineEdit(this)' title='Edit Quantity/Price' style='background: none; border: none; cursor: pointer; padding: 4px; font-size: 0.8rem; opacity: 0.3; transition: opacity 0.2s; flex-shrink: 0;'>
                                                    ✏️
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class='static-view qty-pricing-box' style='display:flex; flex-direction:column; align-items:flex-end; gap:4px;'>
                                            <span class='qty-chip' style='background:#f1f5f9; padding:4px 8px; border-radius:6px; font-weight:800; font-size:0.8rem; color:#475569;'>" . htmlspecialchars($row['quantity']) . "x</span>
                                            <div class='qty-unit-cost' style='font-weight:700; color:var(--text-main); font-size:1rem;'>
                                                $" . number_format($row['unit_price'] ?? 0, 2) . "
                                            </div>
                                        </div>
                                        <form method='POST' class='edit-view' style='display:none;'>
                                            <input type='hidden' name='action' value='update_item'>
                                            <input type='hidden' name='update_id' value='{$row['id']}'>
                                            <input type='hidden' name='customer_id' value='" . htmlspecialchars($current_customer) . "'>
                                            <input type='hidden' name='order_id' value='" . htmlspecialchars($current_order) . "'>
                                            <div class='qty-pricing-box' style='display:flex; gap: 8px; align-items:center;'>
                                                <input type='number' name='update_qty' value='" . htmlspecialchars($row['quantity']) . "' min='1' style='width: 75px; height: 32px; padding: 0 8px; border-radius: 6px; border: 1px solid var(--border-color); font-weight: 700;' onchange='this.form.submit()'>
                                                <div style='display:flex; align-items:center;'>
                                                    <span style='margin-right: 4px; font-weight:700;'>$</span>
                                                    <input type='number' step='0.01' name='update_price' value='" . number_format($row['unit_price'] ?? 0, 2, '.', '') . "' min='0' style='width: 85px; height: 32px; padding: 0 8px; border-radius: 6px; border: 1px solid var(--border-color); font-weight: 700;' onchange='this.form.submit()'>
                                                </div>
                                            </div>
                                            <button type='submit' style='margin-top: 6px; width: 100%; border: none; background: #e2e8f0; color: var(--text-main); border-radius: 6px; cursor: pointer; height: 26px; font-size: 0.75rem; font-weight: 800;'>Done</button>
                                        </form>
                                    </td>
                                    <td class='action-cell'>
                                        <form method='POST' style='display:inline;' onsubmit=\"return confirm('Remove this item?');\">
                                            <input type='hidden' name='action' value='delete'>
                                            <input type='hidden' name='delete_id' value='{$row['id']}'>
                                            <button type='submit' class='btn-delete' title='Remove Item'>&times;</button>
                                        </form>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>
                                <div class='empty-state'>
                                    <p>Your order is empty</p>
                                    <small>Add items from the left to start building your order.</small>
                                </div>
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if (count($items) > 0): ?>
            <div class="builder-footer">
                <a href="checkout.php?customer_id=<?= urlencode($current_customer) ?>&order_id=<?= urlencode($current_order) ?>" class="btn-checkout-link">
                    Complete & Checkout
                </a>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Warehouse Picker Modal -->
<div id="wh-modal" class="modal-overlay no-print" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this) closeWarehouseModal()">
    <div style="background:white; border-radius:24px; width:90%; max-width:800px; max-height:85vh; padding:30px; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-weight:900;">🏬 Pick from Warehouse</h2>
            <button type="button" onclick="closeWarehouseModal()" style="background:none; border:none; font-size:2rem; cursor:pointer;">&times;</button>
        </div>

        <div style="margin-bottom:20px; display:flex; gap:10px;">
            <input type="text" id="wh-modal-search" onkeyup="searchWarehouseItems()" placeholder="Search by Brand, Model, or Location..." style="flex:1; height:48px; border-radius:12px; border:1px solid #ddd; padding:0 20px; font-size:1rem;">
            <select id="wh-modal-sector" onchange="searchWarehouseItems()" style="height:48px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:700;">
                <option value="Laptops">Laptops</option>
                <option value="Gaming">Gaming</option>
                <option value="Desktops">Desktops</option>
                <option value="Electronics">Electronics</option>
            </select>
        </div>

        <div id="wh-results" style="flex:1; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:15px; padding-right:10px;">
            <!-- Results populated via JS -->
            <div style="grid-column:1/-1; padding:40px; text-align:center; color:#94a3b8;">Type above to search stock...</div>
        </div>
    </div>
</div>


