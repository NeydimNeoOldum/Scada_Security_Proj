<?php
// Simple auto logger - run this manually or via cron
require 'includes/scada_db.php';
require 'includes/functions.php';

echo "Running auto logger...\n\n";

$scada = get_scada();

// Simulate water level change
$pump_on = ($scada['pump_status'] == 'ON');
$net_flow = ($pump_on ? $scada['inflow_rate'] : 0) - $scada['outflow_rate'];
$change = $net_flow / 12; // 5 minute interval

$new_level = $scada['water_level'] + $change;
if ($new_level < 0) $new_level = 0;

update_scada('water_level', round($new_level));
log_event("LEVEL_CHANGE", "Water level: {$scada['water_level']} → " . round($new_level) . " m³");

echo "Water level updated: {$scada['water_level']} → " . round($new_level) . " m³\n";

// Check overflow
if ($new_level >= $scada['max_level']) {
    log_event("EMERGENCY_OVERFLOW", "⚠️ OVERFLOW! Level: " . round($new_level) . " m³");
    echo "⚠️ OVERFLOW ALERT!\n";

    if ($scada['overflow_protection'] == 'ON' && $pump_on) {
        update_scada('pump_status', 'OFF');
        log_event("PUMP_CONTROL", "Emergency shutdown - Overflow protection");
        echo "Pump auto-stopped!\n";
    }
}

// Check low level
if ($new_level <= $scada['min_level']) {
    log_event("LOW_LEVEL_WARNING", "⚠️ LOW LEVEL: " . round($new_level) . " m³");
    echo "⚠️ LOW LEVEL WARNING!\n";
}

echo "\nDone!\n";
?>
