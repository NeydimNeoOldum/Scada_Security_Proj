<?php
session_start();
require 'includes/db_connect.php';

// Security: Only logged-in users can see the security monitor
if (!isset($_SESSION['user_dn'])) {
    header("Location: index.php");
    exit;
}

// Fetch all logs, newest first
$query = "SELECT * FROM logs ORDER BY id DESC LIMIT 100";
$logs = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Monitor - SCADA</title>
    <meta http-equiv="refresh" content="10"> <style>
        :root {
            --bg-color: #0f1113; /* Darker than dashboard for "Backend" feel */
            --text-main: #dcdddb;
            --accent: #e6b450;   /* Warning Yellow */
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
        
        /* Log Levels Color Coding */
        .type-LOGIN_SUCCESS { color: var(--success); }
        .type-LOGIN_FAIL { color: var(--danger); }
        .type-LOGIN_ATTEMPT { color: var(--info); }
        .type-SECURITY_ALERT { background-color: rgba(224, 79, 95, 0.2); color: var(--danger); font-weight: bold; }
        .type-ACCESS_DENIED { color: #ffcc00; font-weight: bold; } /* Orange for unauthorized access */
        .type-USER_AUDIT { color: #d67dfc; } /* Purple for admin actions */
        .type-DIRECTORY_SEARCH { color: #aaaaaa; font-style: italic; } /* Grey for searches */
    </style>
</head>
<body>

<div class="header">
    <h2>[SECURITY_EVENT_LOGS] :: LIVE STREAM</h2>
    <a href="dashboard.php" class="back-btn">< BACK TO DASHBOARD</a>
</div>

<table>
    <thead>
        <tr>
            <th width="5%">ID</th>
            <th width="15%">TIMESTAMP</th>
            <th width="15%">SOURCE IP</th>
            <th width="15%">EVENT TYPE</th>
            <th>DETAILS</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <?php 
                // Create a CSS class based on the event type (e.g., type-LOGIN_FAIL)
                $css_class = "type-" . str_replace(" ", "_", $log['action']); 
            ?>
            <tr class="<?php echo $css_class; ?>">
                <td><?php echo $log['id']; ?></td>
                <td><?php echo $log['timestamp']; ?></td>
                <td><?php echo $log['user_ip']; ?></td>
                <td><?php echo htmlspecialchars($log['action']); ?></td>
                <td><?php echo htmlspecialchars($log['details']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>