<?php
// includes/defense.php
// This script checks if the current user is banned.
// Include this at the VERY TOP of index.php and dashboard.php

require_once 'db_connect.php';

$current_ip = $_SERVER['REMOTE_ADDR'];

// Check if IP exists in blacklist
$stmt = $db->prepare("SELECT count(*) FROM blacklist WHERE ip_address = ?");
$stmt->execute([$current_ip]);
$is_banned = $stmt->fetchColumn();

if ($is_banned > 0) {
    // Log the rejected attempt (optional, keeps logs detailed)
    // We rely on the existing DB connection
    try {
        $stmt_log = $db->prepare("INSERT INTO logs (user_ip, action, details) VALUES (?, 'BLOCKED_ACCESS', 'Banned IP attempted connection')");
        $stmt_log->execute([$current_ip]);
    } catch (Exception $e) {}

    // Show Ban Message and Kill Script
    die("
    <div style='background:#1e2124; color:white; height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center; font-family:sans-serif;'>
        <h1 style='color:#e04f5f; font-size:40px;'>⛔ ACCESS DENIED</h1>
        <p>Your IP Address ($current_ip) has been blacklisted by the Administrator.</p>
        <p>Contact Security Operations if this is an error.</p>
    </div>
    ");
}
?>