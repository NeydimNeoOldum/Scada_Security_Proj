<?php
// tab_session.php - Multi-tab session support
// Allows multiple concurrent sessions in the same browser

// Get or create tab ID from GET parameter
$tab_id = $_GET['tab_id'] ?? $_POST['tab_id'] ?? null;

if (!$tab_id) {
    // No tab ID provided - this will be handled by JavaScript
    // For now, use default session
    $tab_id = 'default';
}

// Store tab sessions in a multi-dimensional array in the main session
if (!isset($_SESSION['tab_sessions'])) {
    $_SESSION['tab_sessions'] = [];
}

if (!isset($_SESSION['tab_sessions'][$tab_id])) {
    $_SESSION['tab_sessions'][$tab_id] = [];
}

// Helper functions to get/set tab-specific session data
function get_tab_session($key, $default = null) {
    global $tab_id;
    return $_SESSION['tab_sessions'][$tab_id][$key] ?? $default;
}

function set_tab_session($key, $value) {
    global $tab_id;
    $_SESSION['tab_sessions'][$tab_id][$key] = $value;
}

function is_tab_logged_in() {
    return get_tab_session('user_dn') !== null;
}

function get_tab_user_name() {
    return get_tab_session('user_name', 'Guest');
}

function destroy_tab_session() {
    global $tab_id;
    if (isset($_SESSION['tab_sessions'][$tab_id])) {
        unset($_SESSION['tab_sessions'][$tab_id]);
    }
}

// Function to generate URL with tab_id parameter
function add_tab_id($url) {
    global $tab_id;
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $separator . 'tab_id=' . urlencode($tab_id);
}
?>