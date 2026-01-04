<?php
// functions.php - Handles Logging for the Monitoring System requirement
require 'db_connect.php';

function log_event($type, $details) {
    global $db;

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // We use a prepared statement here ONLY for the logger 
        // because we don't want the logging system itself to crash easily.
        // The vulnerabilities are in the DASHBOARD, not here.
        $stmt = $db->prepare("INSERT INTO logs (user_ip, action, details) VALUES (:ip, :type, :details)");
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':details', $details);
        $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if logging fails
    }
}
?>