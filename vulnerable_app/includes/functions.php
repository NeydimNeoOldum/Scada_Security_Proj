<?php
// functions.php - Handles Logging for the Monitoring System requirement
require 'db_connect.php';

function log_event($type, $details) {
    global $db;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // AUTOMATED METADATA COLLECTION (Satisfies "Actionable Metadata")
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
        
        // We pack all this data into the 'details' column since we can't change the DB schema easily
        $enriched_details = "$details [META: Method=$method | UA=$ua | URI=$uri]";

        $stmt = $db->prepare("INSERT INTO logs (user_ip, action, details) VALUES (:ip, :type, :details)");
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':details', $enriched_details);
        $stmt->execute();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>