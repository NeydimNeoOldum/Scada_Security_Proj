<?php
// Simple role check
function get_user_role($user_dn) {
    global $ldap_conn;

    $search = @ldap_read($ldap_conn, $user_dn, "(objectClass=*)");
    if (!$search) return "user";

    $entry = ldap_get_entries($ldap_conn, $search);

    if (isset($entry[0]['ou'][0])) {
        return strtolower($entry[0]['ou'][0]);
    }

    return "user";
}

function is_admin() {
    // Check tab-specific user DN
    $user_dn = get_tab_session('user_dn');
    if (!$user_dn) return false;

    // Always check LDAP directly (don't cache in session)
    $role = get_user_role($user_dn);
    set_tab_session('role', $role); // Update session for display purposes

    return $role === 'admin';
}
?>
