<?php
/**
 * Server-Sent Events (SSE) Stream
 * Watches the SQLite database files for modifications (including WAL writes)
 * and notifies clients immediately when a change is detected.
 */

// Prevent script execution timeouts (limit connection length to 25 seconds)
set_time_limit(30);

// Disable output compression and all buffering for instant SSE delivery
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_implicit_flush(1);

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');

// Absolute paths to database files
$db_files = [
    __DIR__ . '/../../db/customers.db',
    __DIR__ . '/../../db/orders.db',
    __DIR__ . '/../../db/warehouse.db'
];

// Fetch the most recent modification time of any db or its WAL file
function get_db_mtime($files) {
    $max_mtime = 0;
    foreach ($files as $db_path) {
        $wal_path = $db_path . '-wal';
        clearstatcache(true, $db_path);
        clearstatcache(true, $wal_path);

        $t1 = file_exists($db_path) ? filemtime($db_path) : 0;
        $t2 = file_exists($wal_path) ? filemtime($wal_path) : 0;

        $max_mtime = max($max_mtime, $t1, $t2);
    }
    return $max_mtime;
}

$last_mtime = get_db_mtime($db_files);

// Send 2KB padding to force browsers to start processing the stream immediately
echo ":" . str_repeat(" ", 2048) . "\n\n";
echo "retry: 1000\n\n"; // Suggest reconnecting in 1s if connection drops
flush();

$start_time = time();

// Keep the stream open for 25 seconds (then recycle the connection to avoid memory limits)
while (time() - $start_time < 25) {
    $current_mtime = get_db_mtime($db_files);

    if ($current_mtime !== $last_mtime) {
        echo "event: database-change\n";
        echo "data: " . json_encode(['mtime' => $current_mtime]) . "\n\n";
        flush();
        $last_mtime = $current_mtime;
    }

    // Check every 500ms (fast response time, extremely low CPU load)
    usleep(500000);
}
