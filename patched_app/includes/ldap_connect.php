<?php
// includes/ldap_connect.php - SECURE LDAPS VERSION

$ldap_host = "ldaps://openldap_patched:636"; 

$ldap_dn = "cn=admin,dc=scada,dc=local"; // Admin User
$ldap_password = "admin"; // Admin Password

// 1. Connect
// Note: This only initializes the library. The actual connection happens at 'bind'.
$ldap_conn = ldap_connect($ldap_host);

if (!$ldap_conn) {
    // This rarely fails unless the library itself is missing
    die("Fatal Error: Could not initialize LDAP connection.");
}

// 2. Configure Options
// REQUIRED: Use LDAP Protocol Version 3
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);

// REQUIRED: Disable Referrals (Critical for performance; prevents long timeouts)
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// 3. Bind (Authenticate as Admin)
// We use @ to suppress raw PHP warnings on the screen; we handle errors manually.
$bind = @ldap_bind($ldap_conn, $ldap_dn, $ldap_password);

if (!$bind) {
    // Log the specific error to the docker logs (check with: docker logs scada_web_patched)
    error_log("LDAP Bind Failed: " . ldap_error($ldap_conn));
    
    // NOTE: We do not die() here because we might want the Login page 
    // to handle the "System Offline" message gracefully.
}
?>