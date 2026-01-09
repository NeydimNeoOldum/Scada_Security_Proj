<?php
session_start();
require 'includes/tab_session.php'; // Multi-tab session support
require 'includes/db_connect.php';
// [FIX 1] Include these files so is_admin() and log_event() work
require 'includes/check_role.php';
require 'includes/functions.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_ip'])) {
    if (!is_admin()) { die("Unauthorized"); } 
    
    $ip_to_ban = $_POST['block_ip'];
    $reason = "Manual Block via Monitor";
    
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO blacklist (ip_address, reason) VALUES (?, ?)");
        $stmt->execute([$ip_to_ban, $reason]);
        
        log_event("ADMIN_ACTION", "Manually blocked IP: $ip_to_ban. [ACTION: Enforce Ban] [REVERSAL: Remove from Blacklist]");

        // Optional: Redirect to avoid re-submitting on refresh
        header("Location: " . add_tab_id("monitor.php"));
        exit;
    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

if (!is_tab_logged_in()) {
    header("Location: " . add_tab_id("index.php"));
    exit;
}
// Only admin can see the security monitor
if (!is_admin()) {
    // Stop regular users from seeing the monitor
    die("<h1>Access Denied</h1><p>Security Monitor is for Administrators only.</p><a href='dashboard.php'>Back to Dashboard</a>");
}
$query = "SELECT * FROM logs ORDER BY id DESC LIMIT 100";
$logs = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Monitor - SCADA</title>
    <meta http-equiv="refresh" content="10"> 
    <style>
        :root {
            --bg-color: #0f1113;
            --text-main: #dcdddb;
            --accent: #e6b450;
            --danger: #e04f5f;
            --success: #43b581;
            --info: #4fa3d1;
        }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Consolas', 'Monaco', monospace; margin: 0; padding: 20px; font-size: 13px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .back-btn { text-decoration: none; color: var(--text-main); border: 1px solid #555; padding: 5px 15px; border-radius: 3px; }
        .back-btn:hover { background: #333; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 2px solid #333; padding: 10px; color: #777; }
        td { padding: 8px 10px; border-bottom: 1px solid #222; }
        
        .type-LOGIN_SUCCESS { color: var(--success); }
        .type-LOGIN_FAIL { color: var(--danger); }
        .type-LOGIN_ATTEMPT { color: var(--info); }
        .type-SECURITY_ALERT { background-color: rgba(224, 79, 95, 0.2); color: var(--danger); font-weight: bold; }
        .type-ACCESS_DENIED { color: #ffcc00; font-weight: bold; } 
        .type-USER_AUDIT { color: #d67dfc; }
        .type-DIRECTORY_SEARCH { color: #aaaaaa; font-style: italic; }
        .type-CRITICAL_SQL_INJECTION { background-color: rgba(224, 79, 95, 0.4); color: white; font-weight: bold; border: 1px solid red; }
        .type-HIGH_LDAP_INJECTION { color: #ff9900; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <h2>[SECURITY_EVENT_LOGS] :: LIVE STREAM</h2>
    <a href="dashboard.php" class="back-btn">< BACK TO DASHBOARD</a>
</div>

<table>
    <thead>
        <tr> <th width="5%">ID</th>
            <th width="15%">TIMESTAMP</th>
            <th width="15%">SOURCE IP</th>
            <th width="15%">EVENT TYPE</th>
            <th>DETAILS</th>
            <th>RESPONSE</th> </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <?php 
                $css_class = "type-" . str_replace(" ", "_", $log['action']); 
            ?>
            <tr class="<?php echo $css_class; ?>">
                <td><?php echo $log['id']; ?></td>
                <td><?php echo $log['timestamp']; ?></td>
                <td><?php echo $log['user_ip']; ?></td>
                <td><?php echo htmlspecialchars($log['action']); ?></td>
                <td><?php echo htmlspecialchars($log['details']); ?></td>
                
                <td>
                    <?php 
                    // List of "Bad" events that warrant a Block Button
                    $bad_events = ['LOGIN_FAIL', 'SECURITY_ALERT', 'CRITICAL_SQL_INJECTION', 'HIGH_LDAP_INJECTION', 'ACCESS_DENIED', 'CRITICAL_SESSION_HIJACK'];
                    
                    if (in_array($log['action'], $bad_events)): ?>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to BLOCK <?php echo $log['user_ip']; ?>?');">
                            <input type="hidden" name="block_ip" value="<?php echo $log['user_ip']; ?>">
                            <button type="submit" style="
                                background-color: #e04f5f; 
                                color: white; 
                                border: none; 
                                padding: 6px 10px; 
                                border-radius: 4px; 
                                cursor: pointer; 
                                font-weight: bold; 
                                font-size: 11px;">
                                🚫 BLOCK IP
                            </button>
                        </form>

                    <?php else: ?>
                        <span style="color:#555; font-size:11px;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>