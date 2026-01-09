<?php
session_start();
require 'includes/tab_session.php';
require 'includes/db_connect.php';
require 'includes/ldap_connect.php';
require 'includes/check_role.php';
require 'includes/functions.php';

// ADMIN ONLY ACCESS
if (!is_tab_logged_in()) {
    header("Location: " . add_tab_id("index.php"));
    exit;
}

if (!is_admin()) {
    die("Access Denied: Admin privileges required");
}

// Create settings table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS security_settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle toggle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_rate_limit'])) {
    $current = $db->query("SELECT value FROM security_settings WHERE key = 'rate_limit_enabled'")->fetchColumn();
    $new_value = ($current === '1') ? '0' : '1';

    $stmt = $db->prepare("INSERT OR REPLACE INTO security_settings (key, value, updated_at) VALUES ('rate_limit_enabled', ?, datetime('now'))");
    $stmt->execute([$new_value]);

    $admin_user = get_tab_user_name();
    $action = $new_value === '1' ? 'enabled' : 'disabled';
    log_event("SECURITY_CONFIG", "Admin $admin_user $action rate limiting");

    $success_msg = "Rate limiting has been " . $action;
}

// Get current settings
$rate_limit_enabled = $db->query("SELECT value FROM security_settings WHERE key = 'rate_limit_enabled'")->fetchColumn();
if ($rate_limit_enabled === false) {
    // Default: enabled
    $db->exec("INSERT INTO security_settings (key, value) VALUES ('rate_limit_enabled', '1')");
    $rate_limit_enabled = '1';
}

// Get recent settings changes
$recent_changes = $db->query("SELECT * FROM logs WHERE action = 'SECURITY_CONFIG' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Settings - Admin</title>
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success { background: rgba(67, 181, 129, 0.2); color: var(--success); border-left: 4px solid var(--success); }
        .alert-warning { background: rgba(250, 166, 26, 0.2); color: var(--warning); border-left: 4px solid var(--warning); }

        .panel {
            background: var(--panel-bg);
            padding: 25px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .panel h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--accent);
            text-transform: uppercase;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #0a0c0f;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid var(--info);
        }

        .setting-info h3 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .setting-info p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .toggle-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s;
        }

        .toggle-btn.enabled {
            background: var(--success);
            color: white;
        }

        .toggle-btn.disabled {
            background: var(--danger);
            color: white;
        }

        .toggle-btn:hover {
            opacity: 0.8;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .status-enabled { background: var(--success); color: white; }
        .status-disabled { background: var(--danger); color: white; }

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

        .warning-box {
            background: rgba(250, 166, 26, 0.1);
            border: 1px solid var(--warning);
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .warning-box strong {
            color: var(--warning);
        }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>Security Settings</h1>
        <p style="color: var(--text-muted); font-size: 12px;">Configure system security features</p>
    </div>
    <a href="<?php echo add_tab_id('dashboard.php'); ?>" class="back-btn">← Back to Dashboard</a>
</div>

<?php if (isset($success_msg)): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<div class="panel">
    <h2>Authentication & Rate Limiting</h2>

    <div class="setting-item">
        <div class="setting-info">
            <h3>
                Rate Limiting on Login
                <span class="status-badge <?php echo $rate_limit_enabled === '1' ? 'status-enabled' : 'status-disabled'; ?>">
                    <?php echo $rate_limit_enabled === '1' ? 'ENABLED' : 'DISABLED'; ?>
                </span>
            </h3>
            <p>
                Limits failed login attempts to 5 per 10 minutes from a single IP address.
                When enabled, prevents brute force attacks by blocking repeated login attempts.
            </p>
            <?php if ($rate_limit_enabled === '0'): ?>
                <div class="warning-box">
                    <strong>⚠ WARNING:</strong> Rate limiting is currently disabled. Your system is vulnerable to brute force attacks.
                    This should only be disabled for testing purposes.
                </div>
            <?php endif; ?>
        </div>
        <form method="POST">
            <button type="submit" name="toggle_rate_limit" class="toggle-btn <?php echo $rate_limit_enabled === '1' ? 'enabled' : 'disabled'; ?>">
                <?php echo $rate_limit_enabled === '1' ? 'DISABLE' : 'ENABLE'; ?>
            </button>
        </form>
    </div>
</div>

<div class="panel">
    <h2>Recent Configuration Changes</h2>
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>IP Address</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($recent_changes)): ?>
                <?php foreach ($recent_changes as $change): ?>
                <tr>
                    <td style="font-size: 10px;"><?php echo $change['timestamp']; ?></td>
                    <td><code><?php echo htmlspecialchars($change['user_ip']); ?></code></td>
                    <td><?php echo htmlspecialchars($change['action']); ?></td>
                    <td><?php echo htmlspecialchars($change['details']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; color: var(--text-muted);">No recent changes</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>