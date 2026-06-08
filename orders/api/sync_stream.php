<?php
/**
 * Server-Sent Events (SSE) Stream
 * Watches the SQLite database files for modifications (including WAL writes)
 * and notifies clients immediately when a change is detected.
 */

// Prevent script execution timeouts (limit connection length to 25 seconds)
set_time_limit(30);

// Disable output buffering so messages are sent instantly
if (ob_get_level() > 0) ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering on proxy servers like nginx

// Absolute paths to customers database and WAL files
$db_path = __DIR__ . '/../../db/customers.db';
$wal_path = $db_path . '-wal';

// Fetch the most recent modification time of either file
function get_db_mtime($db, $wal) {
    clearstatcache(true, $db);
    clearstatcache(true, $wal);
    
    $t1 = file_exists($db) ? filemtime($db) : 0;
    $t2 = file_exists($wal) ? filemtime($wal) : 0;
    
    return max($t1, $t2);
}

$last_mtime = get_db_mtime($db_path, $wal_path);

// Send 2KB padding to force browsers to start processing the stream immediately
echo ":" . str_repeat(" ", 2048) . "\n\n";
echo "retry: 1000\n\n"; // Suggest reconnecting in 1s if connection drops
flush();

$start_time = time();

// Keep the stream open for 25 seconds (then recycle the connection to avoid memory limits)
while (time() - $start_time < 25) {
    $current_mtime = get_db_mtime($db_path, $wal_path);
    
    if ($current_mtime !== $last_mtime) {
        echo "event: database-change\n";
        echo "data: " . json_encode(['mtime' => $current_mtime]) . "\n\n";
        flush();
        $last_mtime = $current_mtime;
    }
    
    // Check every 500ms (fast response time, extremely low CPU load)
    usleep(500000);
}
