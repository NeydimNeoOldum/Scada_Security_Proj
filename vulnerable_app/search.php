<?php
session_start();
require 'includes/tab_session.php'; // Multi-tab session support
require 'includes/ldap_connect.php';
require 'includes/functions.php';

if (!is_tab_logged_in()) {
    header("Location: " . add_tab_id("index.php"));
    exit;
}

$results = [];
$debug_filter = "";

if (isset($_GET['query'])) {
    $raw_input = $_GET['query'];

    // VULNERABILITY: Only removes *, doesn't escape parentheses
    $safe_input = str_replace("*", "", $raw_input);

    // Add wildcards for partial name matching
    $search_term = "*" . $safe_input . "*";

    // Search by CN (Common Name)
    $filter = "(&(cn=" . $search_term . ")(objectClass=inetOrgPerson))";

    $debug_filter = $filter;

    log_event("DIRECTORY_SEARCH", "User searched for: " . htmlspecialchars($raw_input), $raw_input);
    // Execute Search
    $search = @ldap_search($ldap_conn, "dc=scada,dc=local", $filter);
    
    if ($search) {
        $entries = ldap_get_entries($ldap_conn, $search);
        // Clean up the array for the view
        unset($entries["count"]);
        $results = $entries;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Directory</title>
    <style>
        /* Same theme as Dashboard */
        :root { --bg-color: #1e2124; --panel-bg: #282b30; --accent: #4fa3d1; --text-main: #ffffff; }
        body { background-color: var(--bg-color); color: var(--text-main); font-family: 'Segoe UI', sans-serif; padding: 40px; }
        
        .container { max-width: 800px; margin: 0 auto; }
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        input { flex-grow: 1; padding: 15px; background: #151719; border: 1px solid #444; color: white; border-radius: 4px; }
        button { padding: 15px 30px; background: var(--accent); color: white; border: none; font-weight: bold; cursor: pointer; }
        
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .card { background: var(--panel-bg); padding: 15px; border-radius: 4px; border-left: 3px solid var(--accent); }
        .card h4 { margin: 0; color: var(--accent); }
        .card p { margin: 5px 0 0; font-size: 13px; color: #aaa; }
        
        .debug { background: #333; padding: 10px; margin-bottom: 20px; font-family: monospace; color: #0f0; font-size: 12px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Internal Employee Directory</h1>
    <a href="dashboard.php" style="color: #aaa; text-decoration: none;">&larr; Back to Dashboard</a>
    <br><br>

    <form method="GET" class="search-box">
        <input type="hidden" name="tab_id" value="<?php echo htmlspecialchars($tab_id); ?>">
        <input type="text" name="query" placeholder="Search by Username (e.g. james.smith1)..." autocomplete="off">
        <button type="submit">SEARCH</button>
    </form>

    <?php if($debug_filter): ?>
        <div class="debug">DEBUG: LDAP Filter Executed: <?php echo htmlspecialchars($debug_filter); ?></div>
    <?php endif; ?>

    <div class="card-grid">
        <?php if (!empty($results)): ?>
            <?php foreach ($results as $user): ?>
                <div class="card">
                    <h4><?php echo $user['cn'][0]; ?></h4>
                    <p>User: <?php echo $user['uid'][0]; ?></p>
                    <p>Dept: <?php echo $user['ou'][0] ?? 'N/A'; ?></p>
                </div>
            <?php endforeach; ?>
        <?php elseif (isset($_GET['query'])): ?>
            <p>No employees found.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>