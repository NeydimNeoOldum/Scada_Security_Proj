<?php
// Path to the SQLite database file
$db_file = '/var/www/html/scada_data.db';

try {
    // Connect (creates file if missing)
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 1); // 1 second timeout to prevent hanging
    $db->exec("PRAGMA busy_timeout = 1000"); // 1 second busy timeout

    // Create Enhanced Logs Table with Attack Classification
    $query = "CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_ip TEXT,
        action TEXT,
        details TEXT,
        attack_type TEXT,
        severity TEXT,
        user_agent TEXT,
        request_uri TEXT,
        request_method TEXT,
        attack_payload TEXT,
        session_id TEXT,
        recommended_action TEXT,
        action_taken TEXT,
        status TEXT
    )";
    $db->exec($query);

    // Create IP Blacklist Table for Manual/Automated Blocking
    $blacklist_query = "CREATE TABLE IF NOT EXISTS blacklist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT UNIQUE NOT NULL,
        banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        reason TEXT,
        blocked_by TEXT,
        expires_at DATETIME,
        is_active INTEGER DEFAULT 1
    )";
    $db->exec($blacklist_query);

    // Create Action History Table for Audit Trail
    $action_history_query = "CREATE TABLE IF NOT EXISTS action_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        log_id INTEGER,
        action_type TEXT,
        performed_by TEXT,
        performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        details TEXT,
        reversible INTEGER DEFAULT 1,
        reversed_at DATETIME,
        FOREIGN KEY (log_id) REFERENCES logs(id)
    )";
    $db->exec($action_history_query);

} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>