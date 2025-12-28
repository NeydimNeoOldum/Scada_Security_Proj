<?php
// Path to the SQLite database file
$db_file = __DIR__ . '/../../scada_data.db';

try {
    // Connect (creates file if missing)
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Tables if they don't exist
    $query = "CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_ip TEXT,
        action TEXT,
        details TEXT
    )";
    $db->exec($query);

} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>