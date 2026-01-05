<?php
session_start();
require 'includes/db_connect.php';
require 'includes/scada_db.php';
require 'includes/ldap_connect.php';
require 'includes/check_role.php';

if (!isset($_SESSION['user_dn'])) {
    header("Location: index.php");
    exit;
}

$is_admin = is_admin();

// Get live SCADA data
$scada = get_scada();

// SQL Injection vulnerability (same as original dashboard.php)
$log_results = [];
$filter_error = "";

$blacklist = [
    "UNION", "SELECT", "INSERT", "UPDATE", "DELETE", "DROP", "ALTER", "CREATE",
    "TRUNCATE", "EXEC", "SHUTDOWN", "MERGE", "GRANT", "REVOKE", "COMMIT",
    "ROLLBACK", "REPLACE", "HANDLER", "CALL", "DO", "PREPARE", "EXECUTE",
    "DEALLOCATE", "DESCRIBE", "EXPLAIN", "USE", "LOCK", "UNLOCK", "RENAME",
    "SET", "SHOW", "PRAGMA", "CAST", "CHAR", "VARCHAR", "NCHAR", "OR"
];

if (isset($_GET['log_id'])) {
    $input = $_GET['log_id'];
    $detected = false;
    foreach ($blacklist as $word) {
        if (strpos($input, $word) !== false) {
            $detected = true;
            $filter_error = "SECURITY ALERT: Malicious keyword '$word' detected!";
            log_event("SECURITY_ALERT", "SQL Injection blocked. Keyword: '$word' in input: '$input'");
            break;
        }
    }

    if (!$detected) {
        $query = "SELECT * FROM logs WHERE id = " . $input;
        try {
            $result = $db->query($query);
            if ($result) {
                $log_results = $result->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $filter_error = "Query Error: " . $e->getMessage();
        }
    }
} else {
    $log_results = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="30">
    <title>SCADA Dashboard</title>
    <style>
        :root {
            --bg-color: #1e2124;
            --panel-bg: #282b30;
            --accent: #4fa3d1;
            --danger: #e04f5f;
            --success: #43b581;
            --text-main: #ffffff;
            --text-muted: #b9bbbe;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--accent); padding-bottom: 15px; margin-bottom: 30px; }
        .user-info { font-size: 14px; color: var(--text-muted); }
        .logout { color: var(--danger); text-decoration: none; font-weight: bold; margin-left: 15px; }

        .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .panel { background: var(--panel-bg); padding: 20px; border-radius: 5px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .panel h3 { margin: 0 0 10px 0; font-size: 14px; color: var(--text-muted); text-transform: uppercase; }
        .value { font-size: 32px; font-weight: bold; color: var(--accent); }
        .status-ok { color: var(--success); }

        .log-section { background: var(--panel-bg); padding: 25px; border-radius: 5px; }
        .search-bar { display: flex; margin-bottom: 20px; }
        input { flex-grow: 1; padding: 10px; background: #1e2124; border: 1px solid #42454a; color: white; margin-right: 10px; }
        button { padding: 10px 20px; background: var(--accent); border: none; color: white; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #42454a; font-size: 14px; }
        th { color: var(--text-muted); text-transform: uppercase; font-size: 12px; }

        .error-msg { background: rgba(224, 79, 95, 0.2); color: var(--danger); padding: 10px; border-left: 3px solid var(--danger); margin-bottom: 15px; }
        .btn-control { background: var(--accent); color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 13px; margin-left: 10px; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1 style="margin:0">MUNICIPAL WATER CONTROL</h1>
        <small>Node: SCADA-04 | Reservoir A | Last Update: <?php echo date('Y-m-d H:i:s'); ?> | Auto-refresh: 30s</small>
    </div>
    <div class="user-info">
        User: <strong><?php echo $_SESSION['user_name'] ?? 'Unknown'; ?></strong>
        <?php if($is_admin): ?>
            <a href="controls.php" class="btn-control">⚙ CONTROLS</a>
            <a href="users.php" class="btn-control" style="background: #5865f2;">👥 USERS</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">LOGOUT</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <h3>Water Level</h3>
        <div class="value"><?php echo $scada['water_level']; ?> m³</div>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px;">
            Min: <?php echo $scada['min_level']; ?> | Max: <?php echo $scada['max_level']; ?>
        </div>
    </div>
    <div class="panel">
        <h3>Inflow Rate</h3>
        <div class="value" style="color: var(--success);"><?php echo $scada['inflow_rate']; ?> m³/min</div>
    </div>
    <div class="panel">
        <h3>Outflow Rate</h3>
        <div class="value" style="color: #faa61a;"><?php echo $scada['outflow_rate']; ?> m³/min</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <h3>Pump Status</h3>
        <div class="value status-ok"><?php echo $scada['pump_status']; ?></div>
    </div>
    <div class="panel">
        <h3>Valve Position</h3>
        <div class="value"><?php echo $scada['valve_position']; ?>%</div>
    </div>
    <div class="panel">
        <h3>Overflow Protection</h3>
        <div class="value <?php echo $scada['overflow_protection'] == 'ON' ? 'status-ok' : ''; ?>"
             style="<?php echo $scada['overflow_protection'] == 'OFF' ? 'color: var(--danger);' : ''; ?>">
            <?php echo $scada['overflow_protection']; ?>
        </div>
    </div>
</div>

<div class="log-section">
    <h2 style="margin-top:0">System Event Logs</h2>
    <p style="color: var(--text-muted); font-size: 13px;">Use ID to filter events.</p>

    <form method="GET" class="search-bar">
        <input type="text" name="log_id" placeholder="Enter Log ID..." autocomplete="off">
        <button type="submit">FILTER</button>
    </form>

    <?php if($filter_error): ?>
        <div class="error-msg"><?php echo $filter_error; ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Timestamp</th>
                <th>IP</th>
                <th>Event</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($log_results)): ?>
                <?php foreach ($log_results as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['timestamp'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['user_ip'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['action'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['details'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; color: var(--text-muted);">No logs found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
