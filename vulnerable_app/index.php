<?php
// START SESSION
session_start();
require 'includes/ldap_connect.php';
require 'includes/functions.php';

$error = "";

// ---------------------------------------------------------
// AUTHENTICATION LOGIC (SAME AS BEFORE)
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    log_event("LOGIN_ATTEMPT", "Attempt for User: " . $user_input);

    // VULNERABILITY #1: LDAP INJECTION
    $filter = "(uid=" . $user_input . ")"; 
    $search = ldap_search($ldap_conn, "dc=scada,dc=local", $filter);
    $info = ldap_get_entries($ldap_conn, $search);

    if ($info["count"] > 0) {
        $user_dn = $info[0]["dn"];
        $user_cn = $info[0]["cn"][0];

        // VULNERABILITY #2: NO RATE LIMITING
        $bind = @ldap_bind($ldap_conn, $user_dn, $pass_input);

        if ($bind) {
            $_SESSION['user_dn'] = $user_dn;
            $_SESSION['user_name'] = $user_cn;
            
            log_event("LOGIN_SUCCESS", "User logged in: " . $user_cn);
            header("Location: dashboard.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SCADA Access Control</title>
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