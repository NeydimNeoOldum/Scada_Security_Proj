<?php
session_start();
require 'includes/tab_session.php'; // Multi-tab session support
require 'includes/ldap_connect.php';
require 'includes/check_role.php';

if (!is_tab_logged_in()) {
    header("Location: " . add_tab_id("index.php"));
    exit;
}

// Only admin can access
if (!is_admin()) {
    die("<h1>Access Denied</h1><p>Only administrators can manage users.</p><a href='dashboard_live.php'>Back to Dashboard</a>");
}

$message = "";
$error = "";

// Delete user
if (isset($_GET['delete'])) {
    $uid = $_GET['delete'];
    $dn = "uid=$uid,dc=scada,dc=local";

    if (@ldap_delete($ldap_conn, $dn)) {
        $message = "User '$uid' deleted successfully!";
        log_event("USER_AUDIT", "Admin deleted user: $uid");
    } else {
        $error = "Failed to delete user: " . ldap_error($ldap_conn);
    }
}

// Add user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $uid = $_POST['uid'];
    $cn = $_POST['cn'];
    $sn = $_POST['sn'];
    $password = $_POST['password'];
    $role = $_POST['role']; // admin or user

    $dn = "uid=$uid,dc=scada,dc=local";

    $entry = [
        'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person', 'top'],
        'uid' => $uid,
        'cn' => $cn,
        'sn' => $sn,
        'ou' => $role,
        'userPassword' => $password
    ];

    if (@ldap_add($ldap_conn, $dn, $entry)) {
        $message = "User '$uid' created successfully!";
        log_event("USER_AUDIT", "Admin created new user: $uid (Role: $role)");
    } else {
        $error = "Failed to create user: " . ldap_error($ldap_conn);
    }
}

// Get all users
$search = @ldap_search($ldap_conn, "dc=scada,dc=local", "(uid=*)");
$users = [];
if ($search) {
    $entries = ldap_get_entries($ldap_conn, $search);
    for ($i = 0; $i < $entries['count']; $i++) {
        $users[] = [
            'uid' => $entries[$i]['uid'][0] ?? '',
            'cn' => $entries[$i]['cn'][0] ?? '',
            'ou' => $entries[$i]['ou'][0] ?? 'user'
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
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
        .panel { background: var(--panel-bg); padding: 25px; border-radius: 5px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #42454a; }
        th { color: var(--text-muted); text-transform: uppercase; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-admin { background: var(--danger); }
        .badge-user { background: #5865f2; }
        .btn-delete { background: var(--danger); color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        input, select { width: 100%; padding: 10px; background: #1e2124; border: 1px solid #42454a; color: white; margin-bottom: 10px; }
        button { padding: 12px 24px; background: var(--accent); border: none; color: white; font-weight: 600; cursor: pointer; border-radius: 4px; }
        .message { background: rgba(67, 181, 129, 0.2); color: var(--success); padding: 10px; margin-bottom: 20px; }
        .error { background: rgba(224, 79, 95, 0.2); color: var(--danger); padding: 10px; margin-bottom: 20px; }
        a { color: var(--text-muted); text-decoration: none; }
    </style>
</head>
<body>

<div class="header">
    <h1>User Management</h1>
    <a href="dashboard.php">← Back to Dashboard</a>
</div>

<?php if($message): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="panel">
    <h2>Current Users</h2>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['uid']); ?></td>
                    <td><?php echo htmlspecialchars($user['cn']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $user['ou']; ?>">
                            <?php echo strtoupper($user['ou']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?delete=<?php echo urlencode($user['uid']); ?>"
                           class="btn-delete"
                           onclick="return confirm('Delete user <?php echo htmlspecialchars($user['uid']); ?>?')">
                            DELETE
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Add New User</h2>
    <form method="POST">
        <label style="color: var(--text-muted); font-size: 12px;">Username</label>
        <input type="text" name="uid" required placeholder="e.g., john.doe">

        <label style="color: var(--text-muted); font-size: 12px;">Full Name</label>
        <input type="text" name="cn" required placeholder="e.g., John Doe">

        <label style="color: var(--text-muted); font-size: 12px;">Last Name</label>
        <input type="text" name="sn" required placeholder="e.g., Doe">

        <label style="color: var(--text-muted); font-size: 12px;">Password</label>
        <input type="password" name="password" required>

        <label style="color: var(--text-muted); font-size: 12px;">Role</label>
        <select name="role">
            <option value="user">User (Read-Only)</option>
            <option value="admin">Admin (Full Access)</option>
        </select>

        <br><br>
        <button type="submit" name="add_user">CREATE USER</button>
    </form>
</div>

</body>
</html>
