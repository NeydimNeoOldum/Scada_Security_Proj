<?php
// START SESSION
session_start();
require 'includes/tab_session.php';
require 'includes/ldap_connect.php';
require 'includes/functions.php';

$error = "";

// Check if already logged in this tab
if (is_tab_logged_in() && $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . add_tab_id("dashboard.php"));
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Ensure $db is available (it is included via functions.php -> db_connect.php)
    global $db;

    // Check if rate limiting is enabled
    $rate_limit_enabled = $db->query("SELECT value FROM security_settings WHERE key = 'rate_limit_enabled'")->fetchColumn();
    if ($rate_limit_enabled === false) {
        $rate_limit_enabled = '1'; // Default: enabled
    }

    // --- FIX #1: RATE LIMITING (5 attempts per 10 mins) ---
    // We check the database for recent failures from this IP
    $limit = 5;
    $time_window = "-10 minutes";

    $stmt = $db->prepare("SELECT COUNT(*) FROM logs WHERE user_ip = :ip AND action = 'LOGIN_FAIL' AND timestamp > datetime('now', :window)");
    $stmt->execute([':ip' => $ip_address, ':window' => $time_window]);
    $failed_attempts = $stmt->fetchColumn();

    if ($rate_limit_enabled === '1' && $failed_attempts >= $limit) {
        $error = "Too many failed attempts. Please try again in 10 minutes.";
        // Log this specific blocking event so admins know an attack is happening
        log_event("SECURITY_ALERT", "Rate limit blocking IP: " . $ip_address);
    }
    else {
        // Only proceed if under the limit
        log_event("LOGIN_ATTEMPT", "Attempt for User: " . $user_input);

        // --- FIX #2: LDAP INJECTION PREVENTION ---
        // ldap_escape() sanitizes special characters (like * ( ) \ NUL)
        // LDAP_ESCAPE_FILTER ensures it is safe to use inside a filter string
        $safe_user = ldap_escape($user_input, "", LDAP_ESCAPE_FILTER);
        
        $filter = "(uid=" . $safe_user . ")";
        
        // Execute search
        $search = @ldap_search($ldap_conn, "dc=scada,dc=local", $filter);

        if ($search === false) {
            $error = "Invalid search";
            log_event("LOGIN_FAIL", "LDAP error for: " . $user_input);
        } else {
            $info = ldap_get_entries($ldap_conn, $search);

            if ($info["count"] > 0) {
                $user_dn = $info[0]["dn"];
                $user_cn = $info[0]["cn"][0];

                $bind = @ldap_bind($ldap_conn, $user_dn, $pass_input);

                if ($bind) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    set_tab_session('user_dn', $user_dn);
                    set_tab_session('user_name', $user_cn);

                    log_event("LOGIN_SUCCESS", "User logged in: " . $user_cn);
                    header("Location: " . add_tab_id("dashboard.php"));
                    exit;
                } else {
                    $error = "Authentication Failed";
                    log_event("LOGIN_FAIL", "Wrong password for: " . $user_input);
                }
            } else {
                $error = "User Unknown";
                log_event("LOGIN_FAIL", "User does not exist: " . $user_input);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SCADA Access Control</title>
    <script src="includes/tab_session.js"></script>
    <style>
        /* Modern Industrial Minimalist Theme */
        :root {
            --bg-color: #1e2124;
            --card-bg: #282b30;
            --accent: #4fa3d1; /* Industrial Blue */
            --text-main: #ffffff;
            --text-muted: #72767d;
            --error: #e04f5f;
        }

        body { 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            font-family: 'Segoe UI', Roboto, Helvetica, sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }

        .login-card { 
            background-color: var(--card-bg); 
            width: 320px; 
            padding: 40px; 
            border-radius: 4px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border-top: 3px solid var(--accent);
        }

        h2 { 
            margin: 0 0 10px 0; 
            font-size: 18px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: var(--text-main);
            font-weight: 600;
        }

        .subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 30px;
            display: block;
        }

        label {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 20px; 
            background: #1e2124; 
            border: 1px solid #42454a; 
            color: var(--text-main); 
            border-radius: 3px; 
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus {
            border-color: var(--accent);
        }

        button { 
            width: 100%; 
            padding: 12px; 
            background: var(--accent); 
            color: white; 
            border: none; 
            border-radius: 3px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: background 0.2s;
        }

        button:hover { 
            background: #408bb5; 
        }

        .alert { 
            background: rgba(224, 79, 95, 0.1); 
            color: var(--error); 
            padding: 10px; 
            font-size: 13px;
            text-align: center; 
            margin-bottom: 20px; 
            border-left: 3px solid var(--error);
        }

        .footer-status {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="login-card">
    <h2>System Access</h2>
    <span class="subtitle">MUNICIPAL RESERVOIR NETWORK</span>
    
    <?php if($error): ?>
        <div class="alert"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Operator ID</label>
        <input type="text" name="username" autocomplete="off" required>
        
        <label>Password</label>
        <input type="password" name="password" required>
        
        <button type="submit">Connect</button>
    </form>
    
    <div class="footer-status">
        STATUS: ONLINE &bull; NODE: RES-04
    </div>
</div>

</body>
</html>