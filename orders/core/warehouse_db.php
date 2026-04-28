<?php
require_once __DIR__ . '/database.php';

try {
    $conn_wh = Database::warehouse();

    // 1. Warehouse Sectors / Zones Table
    $conn_wh->exec("CREATE TABLE IF NOT EXISTS sectors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT,
        icon TEXT,
        color_theme TEXT
    )");

    // 2. Inventory Items Table
    $conn_wh->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_owner TEXT NOT NULL,          -- Linking to username in users.db
        sector TEXT NOT NULL,               -- Gaming, Laptops, Desktops, Electronics
        location_code TEXT DEFAULT 'ZONE-0', 
        brand TEXT NOT NULL,
        model TEXT NOT NULL,
        specs_json TEXT,                    -- Flexible metadata based on sector
        quantity INTEGER DEFAULT 0,
        status TEXT DEFAULT 'stocked',      -- stocked, reserved, out, maintenance
        last_updated_by TEXT,               -- Track who made the most recent change
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration for last_updated_by
    $cols = $conn_wh->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
    $has_updated_by = false;
    foreach($cols as $c) if($c['name'] === 'last_updated_by') $has_updated_by = true;
    if(!$has_updated_by) $conn_wh->exec("ALTER TABLE inventory ADD COLUMN last_updated_by TEXT");

    // Seed default sectors if empty
    $stmt = $conn_wh->query("SELECT COUNT(*) FROM sectors");
    if ($stmt->fetchColumn() == 0) {
        $sectors = [
            ['Laptops', 'Standard portable computing hardware', '💻', '#3b82f6'],
            ['Gaming', 'High-performance GPUs and gaming rigs', '🎮', '#8b5cf6'],
            ['Desktops', 'Workstations and office towers', '🖥️', '#6366f1'],
            ['Electronics', 'Consumer electronics and peripherals', '🔌', '#f59e0b']
        ];
        $stmt_s = $conn_wh->prepare("INSERT INTO sectors (name, description, icon, color_theme) VALUES (?, ?, ?, ?)");
        foreach ($sectors as $s) {
            $stmt_s->execute($s);
        }
    }

} catch (PDOException $e) {
    die("Warehouse DB Error: " . $e->getMessage());
}
?>
