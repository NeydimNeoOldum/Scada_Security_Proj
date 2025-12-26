<?php
// setup_users.php - Generates 100+ random LDAP users
require 'includes/ldap_connect.php';

// Arrays for random generation
$first_names = ["James", "Mary", "John", "Patricia", "Robert", "Jennifer", "Michael", "Linda", "William", "Elizabeth", "David", "Barbara", "Richard", "Susan", "Joseph", "Jessica", "Thomas", "Sarah", "Charles", "Karen"];
$last_names = ["Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas", "Taylor", "Moore", "Jackson", "Martin"];
$departments = ["Engineering", "HR", "Operations", "Sales", "Security", "Legal", "Maintenance", "Executive"];

echo "<h1>Initializing ScadaCorp LDAP...</h1>";

for ($i = 1; $i <= 100; $i++) {
    // 1. Pick Random Attributes
    $fn = $first_names[array_rand($first_names)];
    $ln = $last_names[array_rand($last_names)];
    $dept = $departments[array_rand($departments)];
    
    // 2. Generate Username (e.g., james.smith42)
    $uid = strtolower($fn . "." . $ln . $i);
    $cn = "$fn $ln";
    $dn = "cn=$cn,dc=scada,dc=local"; // Distinguished Name
    
    // 3. Prepare Data for LDAP
    $info = [
        "cn" => $cn,
        "sn" => $ln,
        "uid" => $uid,
        "ou" => $dept,
        "userPassword" => "Password123!", // Weak default password
        "objectClass" => ["inetOrgPerson", "top"]
    ];

    // 4. Add to Server (We suppress errors with @ for demo smoothness)
    // Note: In a real app, you'd handle errors properly.
    $add = @ldap_add($ldap_conn, $dn, $info);

    if ($add) {
        echo "Created: $uid ($dept)<br>";
    } else {
        // If it fails, it usually means the user already exists
        echo "<span style='color:red'>Failed: $uid (Error: " . ldap_error($ldap_conn) . ")</span><br>";
    }
}

echo "<h2>Done! 100 users generated.</h2>";
?>