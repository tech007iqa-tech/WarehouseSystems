<?php
/**
 * Dashboard Health Check
 * Focus: http://localhost:3000/index.php
 */

$target_url = "http://localhost:3000/index.php";

echo "🧪 Starting Dashboard Health Check...\n";
echo "🔗 Target: $target_url\n\n";

// 1. Check if the server is up
$ch = curl_init($target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "❌ [FAIL] Server returned HTTP $http_code. Is it running on port 3000?\n";
    exit(1);
}
echo "✅ [PASS] HTTP 200 OK\n";

// 2. Main Title Presence
if (strpos($response, 'Worker Dashboard') !== false) {
    echo "✅ [PASS] Dashboard Title Found\n";
} else {
    echo "❌ [FAIL] 'Worker Dashboard' not found in HTML\n";
}

// 3. Stats Panels Presence
$panels = ['Warehouse', 'Sales', 'Leads'];
foreach ($panels as $panel) {
    if (strpos($response, $panel) !== false) {
        echo "✅ [PASS] Panel '$panel' Found\n";
    } else {
        echo "❌ [FAIL] Panel '$panel' missing\n";
    }
}

// 4. Quick Actions Check
if (strpos($response, 'new_label.php') !== false && strpos($response, 'new_order.php') !== false) {
    echo "✅ [PASS] Quick Actions (New Label, B2B Form) Found\n";
} else {
    echo "❌ [FAIL] Quick Actions links missing\n";
}

// 5. Database Health Check (Conditional)
if (strpos($response, '🔴 System Alert') !== false) {
    echo "⚠️  [WARN] Dashboard is reporting a Critical System Alert (Database Issue)\n";
} else {
    echo "✅ [PASS] No System Alerts detected\n";
}

echo "\n🏁 Dashboard Health Check Complete.\n";
