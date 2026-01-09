<?php
session_start();
require 'includes/tab_session.php';
require 'includes/db_connect.php';
require 'includes/ldap_connect.php';
require 'includes/check_role.php';

// ADMIN ONLY ACCESS
if (!is_tab_logged_in()) {
    header("Location: " . add_tab_id("index.php"));
    exit;
}

if (!is_admin()) {
    die("Access Denied: Admin privileges required");
}

// Handle actions (block IP, unblock IP, mark as resolved)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $log_id = $_POST['log_id'] ?? 0;
    $ip_address = $_POST['ip_address'] ?? '';
    $admin_user = get_tab_user_name();

    if ($action === 'block_ip' && $ip_address) {
        // Add IP to blacklist
        try {
            $stmt = $db->prepare("INSERT INTO blacklist (ip_address, reason, blocked_by) VALUES (?, ?, ?)");
            $stmt->execute([$ip_address, 'Blocked via Security Monitor', $admin_user]);

            // Log the action
            $stmt = $db->prepare("INSERT INTO action_history (log_id, action_type, performed_by, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$log_id, 'IP_BLOCKED', $admin_user, "Blocked IP: $ip_address"]);

            $success_msg = "IP $ip_address has been blocked";
        } catch (Exception $e) {
            $error_msg = "Failed to block IP: " . $e->getMessage();
        }
    }

    if ($action === 'unblock_ip' && $ip_address) {
        // Remove IP from blacklist
        $stmt = $db->prepare("DELETE FROM blacklist WHERE ip_address = ?");
        $stmt->execute([$ip_address]);

        // Log the action
        $stmt = $db->prepare("INSERT INTO action_history (log_id, action_type, performed_by, details, reversible) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$log_id, 'IP_UNBLOCKED', $admin_user, "Unblocked IP: $ip_address", 1]);

        $success_msg = "IP $ip_address has been unblocked";
    }

    if ($action === 'mark_resolved' && $log_id) {
        $stmt = $db->prepare("UPDATE logs SET status = 'RESOLVED', action_taken = ? WHERE id = ?");
        $stmt->execute(['Marked as resolved by ' . $admin_user, $log_id]);

        $success_msg = "Log entry marked as resolved";
    }
}

// Fetch statistics
$stats = [
    'total_attacks' => $db->query("SELECT COUNT(*) FROM logs WHERE attack_type IS NOT NULL")->fetchColumn(),
    'critical' => $db->query("SELECT COUNT(*) FROM logs WHERE severity = 'CRITICAL'")->fetchColumn(),
    'high' => $db->query("SELECT COUNT(*) FROM logs WHERE severity = 'HIGH'")->fetchColumn(),
    'medium' => $db->query("SELECT COUNT(*) FROM logs WHERE severity = 'MEDIUM'")->fetchColumn(),
    'blocked_ips' => $db->query("SELECT COUNT(*) FROM blacklist WHERE is_active = 1")->fetchColumn(),
];

// Fetch attack logs with filtering
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_clauses = ["attack_type IS NOT NULL"];
if ($filter === 'critical') $where_clauses[] = "severity = 'CRITICAL'";
if ($filter === 'high') $where_clauses[] = "severity = 'HIGH'";
if ($filter === 'unresolved') $where_clauses[] = "(status IS NULL OR status != 'RESOLVED')";
if ($search) $where_clauses[] = "(user_ip LIKE '%$search%' OR attack_payload LIKE '%$search%')";

$where_sql = implode(' AND ', $where_clauses);
$attack_logs = $db->query("SELECT * FROM logs WHERE $where_sql ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Fetch blocked IPs
$blocked_ips = $db->query("SELECT * FROM blacklist WHERE is_active = 1 ORDER BY banned_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent actions
$recent_actions = $db->query("SELECT * FROM action_history ORDER BY performed_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Monitoring Center - Admin</title>
    <meta http-equiv="refresh" content="30">
    <style>
        :root {
            --bg-color: #0a0c0f;
            --panel-bg: #151921;
            --accent: #e6b450;
            --danger: #e04f5f;
            --success: #43b581;
            --warning: #faa61a;
            --info: #4fa3d1;
            --text-main: #dcddde;
            --text-muted: #72767d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--accent);
        }

        .header h1 { color: var(--accent); font-size: 28px; }
        .back-btn {
            background: var(--panel-bg);
            color: var(--text-main);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #333;
        }
        .back-btn:hover { background: #222; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--panel-bg);
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .stat-card.critical { border-color: var(--danger); }
        .stat-card.high { border-color: var(--warning); }
        .stat-card.medium { border-color: var(--info); }
        .stat-card.success { border-color: var(--success); }

        .stat-card h3 {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-main);
        }

        .filters {
            background: var(--panel-bg);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-btn {
            padding: 8px 16px;
            background: #222;
            color: var(--text-main);
            border: 1px solid #333;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
        }
        .filter-btn.active { background: var(--accent); color: #000; font-weight: bold; }
        .filter-btn:hover { background: #333; }

        .search-box {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 8px 12px;
            background: #222;
            border: 1px solid #333;
            color: var(--text-main);
            border-radius: 4px;
        }
        .search-box button {
            padding: 8px 20px;
            background: var(--info);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .panel {
            background: var(--panel-bg);
            padding: 20px;
            border-radius: 6px;
        }

        .panel h2 {
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--accent);
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #333;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #222;
            font-size: 12px;
        }

        .severity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .severity-CRITICAL { background: var(--danger); color: white; }
        .severity-HIGH { background: var(--warning); color: #000; }
        .severity-MEDIUM { background: var(--info); color: white; }
        .severity-LOW { background: #666; color: white; }

        .action-btn {
            padding: 4px 10px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
        }
        .btn-block { background: var(--danger); color: white; }
        .btn-unblock { background: var(--success); color: white; }
        .btn-resolve { background: var(--info); color: white; }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success { background: rgba(67, 181, 129, 0.2); color: var(--success); border-left: 4px solid var(--success); }
        .alert-error { background: rgba(224, 79, 95, 0.2); color: var(--danger); border-left: 4px solid var(--danger); }

        .blocked-ip-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .blocked-ip-item {
            padding: 10px;
            background: #0a0c0f;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 3px solid var(--danger);
        }

        .action-item {
            padding: 8px;
            background: #0a0c0f;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 11px;
        }

        code {
            background: #000;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>Security Monitoring Center</h1>
        <p style="color: var(--text-muted); font-size: 12px;">Real-time Attack Detection & Response System</p>
    </div>
    <a href="<?php echo add_tab_id('dashboard.php'); ?>" class="back-btn">← Back to Dashboard</a>
</div>

<?php if (isset($success_msg)): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-error"><?php echo $error_msg; ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card critical">
        <h3>Critical Threats</h3>
        <div class="stat-value"><?php echo $stats['critical']; ?></div>
    </div>
    <div class="stat-card high">
        <h3>High Severity</h3>
        <div class="stat-value"><?php echo $stats['high']; ?></div>
    </div>
    <div class="stat-card medium">
        <h3>Medium Severity</h3>
        <div class="stat-value"><?php echo $stats['medium']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Total Attacks</h3>
        <div class="stat-value"><?php echo $stats['total_attacks']; ?></div>
    </div>
    <div class="stat-card success">
        <h3>Blocked IPs</h3>
        <div class="stat-value"><?php echo $stats['blocked_ips']; ?></div>
    </div>
</div>

<div class="filters">
    <a href="<?php echo add_tab_id('?filter=all'); ?>" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Attacks</a>
    <a href="<?php echo add_tab_id('?filter=critical'); ?>" class="filter-btn <?php echo $filter === 'critical' ? 'active' : ''; ?>">Critical</a>
    <a href="<?php echo add_tab_id('?filter=high'); ?>" class="filter-btn <?php echo $filter === 'high' ? 'active' : ''; ?>">High</a>
    <a href="<?php echo add_tab_id('?filter=unresolved'); ?>" class="filter-btn <?php echo $filter === 'unresolved' ? 'active' : ''; ?>">Unresolved</a>

    <form method="GET" class="search-box">
        <input type="hidden" name="tab_id" value="<?php echo htmlspecialchars($tab_id); ?>">
        <input type="text" name="search" placeholder="Search by IP or payload..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>
</div>

<div class="content-grid">
    <div class="panel">
        <h2>Detected Attacks & Threats</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>Source IP</th>
                    <th>Attack Type</th>
                    <th>Severity</th>
                    <th>Payload</th>
                    <th>Recommended Action</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attack_logs as $log): ?>
                <tr>
                    <td><?php echo $log['id']; ?></td>
                    <td style="font-size: 10px;"><?php echo $log['timestamp']; ?></td>
                    <td><code><?php echo htmlspecialchars($log['user_ip']); ?></code></td>
                    <td><?php echo htmlspecialchars($log['attack_type'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="severity-badge severity-<?php echo $log['severity'] ?? 'LOW'; ?>">
                            <?php echo $log['severity'] ?? 'LOW'; ?>
                        </span>
                    </td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                        <code><?php echo htmlspecialchars(substr($log['attack_payload'] ?? '', 0, 50)); ?></code>
                    </td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($log['recommended_action'] ?? 'Review manually'); ?></td>
                    <td>
                        <?php if ($log['status'] !== 'RESOLVED'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                <input type="hidden" name="ip_address" value="<?php echo $log['user_ip']; ?>">
                                <button type="submit" name="action" value="block_ip" class="action-btn btn-block">Block IP</button>
                                <button type="submit" name="action" value="mark_resolved" class="action-btn btn-resolve">Resolve</button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--success); font-size: 11px;">✓ Resolved</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <div class="panel">
            <h2>Blocked IP Addresses</h2>
            <div class="blocked-ip-list">
                <?php foreach ($blocked_ips as $blocked): ?>
                <div class="blocked-ip-item">
                    <strong><?php echo htmlspecialchars($blocked['ip_address']); ?></strong><br>
                    <small style="color: var(--text-muted);">
                        Blocked: <?php echo $blocked['banned_at']; ?><br>
                        By: <?php echo htmlspecialchars($blocked['blocked_by']); ?><br>
                        Reason: <?php echo htmlspecialchars($blocked['reason']); ?>
                    </small>
                    <form method="POST" style="margin-top: 8px;">
                        <input type="hidden" name="ip_address" value="<?php echo $blocked['ip_address']; ?>">
                        <button type="submit" name="action" value="unblock_ip" class="action-btn btn-unblock">Unblock</button>
                    </form>
                </div>
                <?php endforeach; ?>

                <?php if (empty($blocked_ips)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 20px;">No blocked IPs</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel" style="margin-top: 20px;">
            <h2>Recent Actions</h2>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($recent_actions as $action): ?>
                <div class="action-item">
                    <strong><?php echo htmlspecialchars($action['action_type']); ?></strong><br>
                    <small style="color: var(--text-muted);">
                        <?php echo $action['performed_at']; ?> by <?php echo htmlspecialchars($action['performed_by']); ?><br>
                        <?php echo htmlspecialchars($action['details']); ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>