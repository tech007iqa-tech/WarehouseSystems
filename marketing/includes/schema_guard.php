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

        // 5. Photos Table (Photo Bucket)
        $pdo->exec("CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT,
            model_name TEXT,
            category TEXT,
            file_path TEXT NOT NULL,
            file_size INTEGER,
            mime_type TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // --- Automated Migrations ---
        $stmt_info = $pdo->query("PRAGMA table_info(leads)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');
        
        // Example migration: if we need to add a 'last_contacted' column
        if (!in_array('last_contacted', $col_names)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN last_contacted DATETIME");
        }

        // Add customer_id to track CRM sync
        if (!in_array('customer_id', $col_names)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN customer_id TEXT");
        }

        // 5. Performance Indexes (Optimization)
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_customer_id ON leads(customer_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_templates_model ON model_templates(model_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_logs(timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_model ON photos(model_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_category ON photos(category)");

        return true;
    } catch (Exception $e) {
        error_log("Marketing Schema Guard Error: " . $e->getMessage());
        return false;
    }
}

/**
 * CRM Schema Guard
 * Ensures the Master CRM (customers.db) is compatible with Marketing leads.
 */
function crm_schema_guard($pdo) {
    try {
        $stmt_info = $pdo->query("PRAGMA table_info(customers)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');

        // Add lead-specific columns if they don't exist
        if (!in_array('lead_source', $col_names)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN lead_source TEXT DEFAULT 'Manual'");
        }
        if (!in_array('account_status', $col_names)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN account_status TEXT DEFAULT 'Customer'");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("CRM Schema Guard Error: " . $e->getMessage());
        return false;
    }
}
?>
