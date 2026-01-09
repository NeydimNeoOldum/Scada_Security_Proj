#!/usr/bin/env php
<?php
/**
 * SCADA Water Reservoir Simulation Engine
 * Runs continuously in background, updating water levels every 5 minutes
 */

require __DIR__ . '/includes/db_connect.php'; // Database connection for logging
require __DIR__ . '/includes/scada_db.php';
require __DIR__ . '/includes/functions.php';

// Override $_SERVER for CLI context
if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

echo "=== SCADA Water Reservoir Simulation Started ===\n";
echo "Updating every 5 minutes (300 seconds)\n\n";

while (true) {
    try {
        $scada = get_scada();

        echo "[" . date('Y-m-d H:i:s') . "] Running simulation cycle...\n";

        // Calculate water flow
        $pump_on = ($scada['pump_status'] == 'ON');
        $inflow = $pump_on ? $scada['inflow_rate'] : 0;
        $outflow = $scada['outflow_rate'];

        // Valve affects outflow (0% = no outflow, 100% = full outflow)
        $valve_factor = $scada['valve_position'] / 100;
        $actual_outflow = $outflow * $valve_factor;

        // Net change over 5 minutes
        $net_flow_per_minute = $inflow - $actual_outflow;
        $change = $net_flow_per_minute * 5;

        // Update water level
        $old_level = $scada['water_level'];
        $new_level = $old_level + $change;

        // Prevent negative water level
        if ($new_level < 0) $new_level = 0;

        update_scada('water_level', round($new_level));

        echo "  Pump: {$scada['pump_status']} | Valve: {$scada['valve_position']}%\n";
        echo "  Inflow: {$inflow} m³/min | Outflow: " . round($actual_outflow, 1) . " m³/min\n";
        echo "  Water Level: {$old_level} → " . round($new_level) . " m³ (Δ " . round($change, 1) . ")\n";

        // Log the level change
        log_event("LEVEL_CHANGE", "Water level: {$old_level} → " . round($new_level) . " m³ (Net flow: " . round($net_flow_per_minute, 1) . " m³/min)");

        // Check for OVERFLOW
        if ($new_level >= $scada['max_level']) {
            echo "  ⚠️  OVERFLOW ALERT! Level: " . round($new_level) . " m³ (Max: {$scada['max_level']})\n";
            log_event("EMERGENCY_OVERFLOW", "⚠️ OVERFLOW! Level: " . round($new_level) . " m³ (exceeds max: {$scada['max_level']})");

            // Auto-shutdown if overflow protection is ON
            if ($scada['overflow_protection'] == 'ON' && $pump_on) {
                update_scada('pump_status', 'OFF');
                log_event("PUMP_CONTROL", "EMERGENCY SHUTDOWN - Overflow protection triggered");
                echo "  🛑 Pump automatically stopped by overflow protection!\n";
            }
        }

        // Check for LOW LEVEL
        if ($new_level <= $scada['min_level']) {
            echo "  ⚠️  LOW LEVEL WARNING! Level: " . round($new_level) . " m³ (Min: {$scada['min_level']})\n";
            log_event("LOW_LEVEL_WARNING", "⚠️ LOW LEVEL: " . round($new_level) . " m³ (below min: {$scada['min_level']})");
        }

        // Log pump state changes (detect if pump was turned on/off)
        static $last_pump_status = null;
        if ($last_pump_status !== null && $last_pump_status !== $scada['pump_status']) {
            log_event("PUMP_STATUS_CHANGE", "Pump turned {$scada['pump_status']}");
            echo "  🔄 Pump status changed: {$last_pump_status} → {$scada['pump_status']}\n";
        }
        $last_pump_status = $scada['pump_status'];

        echo "\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n\n";
    }

    // Wait 5 minutes (300 seconds)
    sleep(300);
}
?>
