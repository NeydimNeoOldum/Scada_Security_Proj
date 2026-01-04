<?php
require __DIR__ . '/includes/db_connect.php';

echo "=== Recent System Logs ===\n\n";

$logs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

foreach($logs as $log) {
    echo "[{$log['timestamp']}] {$log['action']}: {$log['details']}\n";
}
?>
