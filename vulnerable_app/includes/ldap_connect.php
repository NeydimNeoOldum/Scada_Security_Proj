<?php
// ldap_connect.php - PHP 8.3 Compatible

// Connection Settings
$ldap_host = "ldap://localhost:389"; // UPDATED: Host and Port combined in one string
$ldap_dn = "cn=admin,dc=scada,dc=local"; // Admin User
$ldap_password = "admin"; // Admin Password

// 1. Connect
// Old way: ldap_connect($host, $port) -> DEPRECATED
// New way: ldap_connect($uri)
$ldap_conn = ldap_connect($ldap_host);

// 2. Configure Options
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// 3. Bind (Login as Admin to add users/search)
$bind = @ldap_bind($ldap_conn, $ldap_dn, $ldap_password);

if (!$bind) {
    // If bind fails, print error but keep script alive for the login page to handle it
    // Use error_log to avoid messing up the UI
    error_log("LDAP Bind Failed: " . ldap_error($ldap_conn));
}
?>