<?php
// Setup script - Delete all users and create one admin
require 'includes/ldap_connect.php';


// Create single admin user
$admin_uid = "admin";
$admin_dn = "uid=admin,dc=scada,dc=local";

$admin_entry = [
    'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person', 'top'],
    'uid' => 'admin',
    'cn' => 'Administrator',
    'sn' => 'Admin',
    'ou' => 'admin',
    'userPassword' => 'admin123'
];

if (@ldap_add($ldap_conn, $admin_dn, $admin_entry)) {
    echo "[CREATED] Admin user\n";
    echo "\n=== Login Credentials ===\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "Role: Administrator\n";
    echo "\n Setup complete!\n";
} else {
    echo "[ERROR] Failed to create admin: " . ldap_error($ldap_conn) . "\n";
}

ldap_close($ldap_conn);
?>
