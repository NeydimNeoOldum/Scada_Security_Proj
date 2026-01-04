<?php
require __DIR__ . '/includes/scada_db.php';

$scada = get_scada();

echo "=== Current SCADA Status ===\n";
echo "Water Level: {$scada['water_level']} m³\n";
echo "Pump Status: {$scada['pump_status']}\n";
echo "Valve Position: {$scada['valve_position']}%\n";
echo "Inflow Rate: {$scada['inflow_rate']} m³/min\n";
echo "Outflow Rate: {$scada['outflow_rate']} m³/min\n";
echo "Min Level: {$scada['min_level']} m³\n";
echo "Max Level: {$scada['max_level']} m³\n";
echo "Overflow Protection: {$scada['overflow_protection']}\n";
?>
