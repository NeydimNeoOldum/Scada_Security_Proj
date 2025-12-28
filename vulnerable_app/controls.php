<?php
session_start();
require 'includes/scada_db.php';
require 'includes/functions.php';
require 'includes/ldap_connect.php';
require 'includes/check_role.php';

if (!isset($_SESSION['user_dn'])) {
    header("Location: index.php");
    exit;
}

// Only admin can access controls
if (!is_admin()) {
    die("<h1>Access Denied</h1><p>Only administrators can access SCADA controls.</p><a href='dashboard.php'>Back to Dashboard</a>");
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'pump') {
        $current = get_scada();
        $new_status = ($current['pump_status'] == 'ON') ? 'OFF' : 'ON';
        update_scada('pump_status', $new_status);
        log_event("PUMP_CONTROL", "Pump turned $new_status");
        $message = "Pump is now $new_status";
    }

    if ($action == 'valve') {
        $valve = $_POST['valve_position'];
        update_scada('valve_position', $valve);
        log_event("VALVE_CONTROL", "Valve set to $valve%");
        $message = "Valve set to $valve%";
    }

    if ($action == 'levels') {
        $min = $_POST['min_level'];
        $max = $_POST['max_level'];
        update_scada('min_level', $min);
        update_scada('max_level', $max);
        log_event("LEVEL_LIMITS", "Min: $min, Max: $max");
        $message = "Water levels updated";
    }

    if ($action == 'overflow') {
        $current = get_scada();
        $new = ($current['overflow_protection'] == 'ON') ? 'OFF' : 'ON';
        update_scada('overflow_protection', $new);
        log_event("OVERFLOW_TOGGLE", "Overflow protection $new");
        $message = "Overflow protection $new";
    }
}

$scada = get_scada();
?>
<!DOCTYPE html>
<html>
<head>
    <title>SCADA Controls</title>
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
        body { background: var(--bg-color); color: var(--text-main); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .header { border-bottom: 2px solid var(--accent); padding-bottom: 15px; margin-bottom: 30px; }
        .control-box { background: var(--panel-bg); padding: 25px; margin: 20px 0; border-radius: 5px; }
        .control-box h3 { color: var(--text-muted); font-size: 14px; text-transform: uppercase; margin-bottom: 15px; }
        .status { font-size: 32px; font-weight: bold; margin: 15px 0; }
        .on { color: var(--success); }
        .off { color: var(--text-muted); }
        button { padding: 12px 24px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; width: 100%; margin: 5px 0; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        input[type="range"] { width: 100%; }
        .message { background: rgba(67, 181, 129, 0.2); color: var(--success); padding: 10px; margin-bottom: 20px; }
        a { color: var(--text-muted); text-decoration: none; }
    </style>
</head>
<body>

<div class="header">
    <h1>SCADA Control Panel</h1>
    <a href="dashboard.php">← Back to Dashboard</a>
</div>

<?php if($message): ?>
    <div class="message"><?php echo $message; ?></div>
<?php endif; ?>

<div class="control-box">
    <h3>Pump Control</h3>
    <div class="status <?php echo $scada['pump_status'] == 'ON' ? 'on' : 'off'; ?>">
        <?php echo $scada['pump_status']; ?>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="pump">
        <button class="<?php echo $scada['pump_status'] == 'ON' ? 'btn-danger' : 'btn-success'; ?>">
            <?php echo $scada['pump_status'] == 'ON' ? 'STOP PUMP' : 'START PUMP'; ?>
        </button>
    </form>
</div>

<div class="control-box">
    <h3>Valve Position</h3>
    <div class="status" style="color: var(--accent);"><?php echo $scada['valve_position']; ?>%</div>
    <form method="POST">
        <input type="hidden" name="action" value="valve">
        <input type="range" name="valve_position" min="0" max="100" value="<?php echo $scada['valve_position']; ?>"
               oninput="this.nextElementSibling.value = this.value + '%'">
        <output><?php echo $scada['valve_position']; ?>%</output>
        <br><br>
        <button class="btn-success">SET VALVE</button>
    </form>
</div>

<div class="control-box">
    <h3>Water Level Limits</h3>
    <form method="POST">
        <input type="hidden" name="action" value="levels">
        <label style="color: var(--text-muted); font-size: 12px;">Min Level (m³)</label>
        <input type="number" name="min_level" value="<?php echo $scada['min_level']; ?>" style="width: 100%; padding: 10px; margin-bottom: 10px;">
        <label style="color: var(--text-muted); font-size: 12px;">Max Level (m³)</label>
        <input type="number" name="max_level" value="<?php echo $scada['max_level']; ?>" style="width: 100%; padding: 10px; margin-bottom: 15px;">
        <button class="btn-success">UPDATE LIMITS</button>
    </form>
</div>

<div class="control-box">
    <h3>Overflow Protection</h3>
    <div class="status <?php echo $scada['overflow_protection'] == 'ON' ? 'on' : 'off'; ?>">
        <?php echo $scada['overflow_protection']; ?>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="overflow">
        <button class="<?php echo $scada['overflow_protection'] == 'ON' ? 'btn-danger' : 'btn-success'; ?>">
            <?php echo $scada['overflow_protection'] == 'ON' ? 'DISABLE' : 'ENABLE'; ?>
        </button>
    </form>
</div>

</body>
</html>
