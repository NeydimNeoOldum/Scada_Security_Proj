<?php
// ldap_connect.php - PHP 8.3 Compatible with LDAPS (LDAP over SSL/TLS)

// Connection Settings - LDAPS (LDAP over SSL on port 636)
// LDAPS provides encrypted connection from the start (unlike StartTLS)
$ldap_host = "ldaps://openldap-secure:636";
$ldap_dn = "cn=admin,dc=scada,dc=local"; // Admin User
$ldap_password = "admin"; // Admin Password

// 1. Connect to LDAPS server (SSL/TLS encrypted from start)
$ldap_conn = ldap_connect($ldap_host);

// 2. Configure LDAP Options
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// For development/testing, we don't verify SSL certificate
// In production, you should verify certificates properly
ldap_set_option($ldap_conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

// 3. Bind (Login as Admin to add users/search)
// All communication is encrypted via SSL/TLS (port 636)
$bind = @ldap_bind($ldap_conn, $ldap_dn, $ldap_password);

if (!$bind) {
    // If bind fails, print error but keep script alive for the login page to handle it
    error_log("LDAP Bind Failed: " . ldap_error($ldap_conn));
}
?>