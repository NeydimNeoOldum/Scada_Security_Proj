#!/bin/bash
# Startup script for SCADA simulation
echo "Starting SCADA Water Reservoir Simulation..."
nohup php /var/www/html/simulate.php > /var/log/scada_simulation.log 2>&1 &
echo "Simulation started in background (PID: $!)"
echo "View logs: tail -f /var/log/scada_simulation.log"
