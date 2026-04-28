<?php
/**
 * Self-Healing Schema Guard for Marketing Module
 */

function marketing_schema_guard($pdo) {
    try {
        // 1. Leads Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company TEXT,
            email TEXT,
            phone TEXT,
            source TEXT,
            status TEXT DEFAULT 'New',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Campaigns Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            type TEXT, -- Email, Social, Cold Call
            status TEXT DEFAULT 'Draft',
            start_date DATE,
            end_date DATE,
            budget NUMERIC,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 3. Model Templates Table (Specs Library)
        $pdo->exec("CREATE TABLE IF NOT EXISTS model_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_name TEXT UNIQUE NOT NULL,
            category TEXT,
            base_specs TEXT,
            marketing_copy TEXT,
            hero_image_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 4. Audit Logs Table (Project Requirement)
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            action TEXT NOT NULL,
            summary TEXT,
            old_value TEXT,
            new_value TEXT,
            user_name TEXT DEFAULT 'System',
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // --- Automated Migrations ---
        $stmt_info = $pdo->query("PRAGMA table_info(leads)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');
        
        // Example migration: if we need to add a 'last_contacted' column
        if (!in_array('last_contacted', $col_names)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN last_contacted DATETIME");
        }

        return true;
    } catch (Exception $e) {
        error_log("Marketing Schema Guard Error: " . $e->getMessage());
        return false;
    }
}
?>
