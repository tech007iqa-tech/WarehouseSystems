<?php
/**
 * debug/verify_mapping.php
 * Diagnostic tool to ensure the Hardware Mapping Layer is fully synced.
 * 
 * Checks:
 * 1. PHP mapping constant exists.
 * 2. JS mapping file exists and matches PHP keys.
 * 3. Database columns exist for all mapped fields.
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

echo "<h1>🛠️ Mapping Layer Verification</h1>";

$F = HW_FIELDS;
$js_file = '../assets/js/hardware_mapping.js';
$errors = [];
$warnings = [];

// 1. Check PHP Mapping
echo "<h3>1. PHP Mapping Check</h3>";
if (empty($F)) {
    echo "❌ Error: HW_FIELDS constant is empty or not defined.<br>";
    $errors[] = "PHP Mapping empty";
} else {
    echo "✅ Success: Found " . count($F) . " mapped fields.<br>";
}

// 2. Check JS Mapping (Heuristic check)
echo "<h3>2. JS Mapping Check</h3>";
if (!file_exists($js_file)) {
    echo "❌ Error: JS mapping file not found at $js_file<br>";
    $errors[] = "JS File missing";
} else {
    $js_content = file_get_contents($js_file);
    $all_synced = true;
    foreach ($F as $key => $val) {
        if (strpos($js_content, "$key:") === false) {
            echo "⚠️ Warning: Key '$key' not found in JS mapping.<br>";
            $warnings[] = "JS missing key: $key";
            $all_synced = false;
        }
    }
    if ($all_synced) {
        echo "✅ Success: All PHP keys found in JS file.<br>";
    }
}

// 3. Database Schema Sync Check
echo "<h3>3. Database Schema Sync</h3>";
try {
    $stmt = $pdo_labels->query("PRAGMA table_info(items)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $db_cols = array_column($columns, 'name');

    $missing_cols = [];
    foreach ($F as $key => $col_name) {
        if (!in_array($col_name, $db_cols)) {
            $missing_cols[] = $col_name;
        }
    }

    if (empty($missing_cols)) {
        echo "✅ Success: All mapped columns exist in 'items' table.<br>";
    } else {
        echo "❌ Error: The following columns are mapped but MISSING in the database:<br>";
        echo "<ul>";
        foreach ($missing_cols as $mc) echo "<li>$mc</li>";
        echo "</ul>";
        $errors[] = "Database columns missing";
        
        echo "<p><i>Tip: Run includes/schema_guard.php (if it handles these) or update your SQLite DB.</i></p>";
    }

} catch (Exception $e) {
    echo "❌ DB Error: " . $e->getMessage() . "<br>";
    $errors[] = "DB connection/query failed";
}

echo "<hr>";
if (empty($errors)) {
    echo "<h2 style='color:green;'>✅ INTEGRITY VERIFIED</h2>";
    echo "<p>The hardware mapping layer is healthy and consistent across the stack.</p>";
} else {
    echo "<h2 style='color:red;'>⚠️ INTEGRITY FAILURE</h2>";
    echo "<p>Please address the errors above to ensure system stability.</p>";
}

if (!empty($warnings)) {
    echo "<h4>Warnings:</h4><ul>";
    foreach ($warnings as $w) echo "<li>$w</li>";
    echo "</ul>";
}
?>
