<?php
// Simple SCADA state database
require_once 'db_connect.php';

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS scada_state (
    id INTEGER PRIMARY KEY DEFAULT 1,
    water_level INTEGER DEFAULT 1240,
    pump_status TEXT DEFAULT 'OFF',
    valve_position INTEGER DEFAULT 50,
    inflow_rate INTEGER DEFAULT 120,
    outflow_rate INTEGER DEFAULT 100,
    min_level INTEGER DEFAULT 500,
    max_level INTEGER DEFAULT 2000,
    overflow_protection TEXT DEFAULT 'ON'
)");

// Insert default if empty
$count = $db->query("SELECT COUNT(*) FROM scada_state")->fetchColumn();
if ($count == 0) {
    $db->exec("INSERT INTO scada_state (id, water_level, pump_status, valve_position, inflow_rate, outflow_rate, min_level, max_level, overflow_protection)
               VALUES (1, 1240, 'OFF', 50, 120, 100, 500, 2000, 'ON')");
}

// Get current state
function get_scada() {
    global $db;
    return $db->query("SELECT * FROM scada_state WHERE id=1")->fetch(PDO::FETCH_ASSOC);
}

// Update state
function update_scada($field, $value) {
    global $db;
    $stmt = $db->prepare("UPDATE scada_state SET $field = ? WHERE id=1");
    $stmt->execute([$value]);
}
?>
