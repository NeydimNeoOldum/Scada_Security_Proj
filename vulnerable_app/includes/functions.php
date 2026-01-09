<?php
// functions.php - Handles Logging for the Monitoring System requirement

function log_event($type, $details, $payload = '') {
    global $db;

    try {
        $ip = $_SERVER['REMOTE_ADDR'];

        // AUTOMATED METADATA COLLECTION (Satisfies "Actionable Metadata")
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
        $session_id = session_id() ?? 'Unknown';

        // ATTACK CLASSIFICATION LOGIC
        $attack_type = null;
        $severity = null;
        $recommended_action = null;
        $attack_payload = $payload;

        // Detect attack patterns based on event type
        if (strpos($type, 'SQL_INJECTION') !== false || strpos($type, 'CRITICAL_SQL_INJECTION') !== false) {
            $attack_type = 'SQL_INJECTION';
            $severity = 'CRITICAL';
            $recommended_action = 'Block IP immediately. Review query logs. Check for data exfiltration.';
        }
        elseif (strpos($type, 'LDAP_INJECTION') !== false || strpos($type, 'HIGH_LDAP_INJECTION') !== false) {
            $attack_type = 'LDAP_INJECTION';
            $severity = 'HIGH';
            $recommended_action = 'Block IP. Audit directory access logs. Reset compromised credentials.';
        }
        elseif ($type === 'LOGIN_FAIL') {
            // Check for brute force patterns (multiple failures from same IP)
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM logs WHERE user_ip = :ip AND action = 'LOGIN_FAIL' AND timestamp > datetime('now', '-5 minutes')");
                $stmt->execute([':ip' => $ip]);
                $fail_count = $stmt->fetchColumn();

                if ($fail_count >= 3) {
                    $attack_type = 'BRUTE_FORCE';
                    $severity = 'HIGH';
                    $recommended_action = 'Temporary IP ban. Enable rate limiting. Notify user of suspicious activity.';
                }
            } catch (Exception $e) {
                // Skip brute force detection if DB is busy
            }
        }
        elseif ($type === 'ACCESS_DENIED') {
            $attack_type = 'UNAUTHORIZED_ACCESS';
            $severity = 'MEDIUM';
            $recommended_action = 'Monitor user behavior. Review access logs for privilege escalation attempts.';
        }
        elseif (strpos($type, 'DIRECTORY_SEARCH') !== false) {
            // Detect LDAP injection patterns in search queries
            if (preg_match('/[\(\)\*\|&\!]/', $payload)) {
                $attack_type = 'LDAP_INJECTION';
                $severity = 'HIGH';
                $recommended_action = 'Block IP. Audit directory queries. Check for information disclosure.';
            }
        }

        // Build enriched details
        $enriched_details = "$details [META: Method=$method | UA=$ua | URI=$uri]";

        // Insert with full attack classification
        if ($attack_type !== null) {
            // This is an attack - populate all security fields
            $stmt = $db->prepare("INSERT INTO logs (
                user_ip, action, details, attack_type, severity,
                user_agent, request_uri, request_method, attack_payload,
                session_id, recommended_action
            ) VALUES (
                :ip, :type, :details, :attack_type, :severity,
                :ua, :uri, :method, :payload,
                :session_id, :recommended_action
            )");

            $stmt->execute([
                ':ip' => $ip,
                ':type' => $type,
                ':details' => $enriched_details,
                ':attack_type' => $attack_type,
                ':severity' => $severity,
                ':ua' => $ua,
                ':uri' => $uri,
                ':method' => $method,
                ':payload' => $attack_payload,
                ':session_id' => $session_id,
                ':recommended_action' => $recommended_action
            ]);
        } else {
            // Normal event - basic logging
            $stmt = $db->prepare("INSERT INTO logs (user_ip, action, details, user_agent, request_uri, request_method) VALUES (:ip, :type, :details, :ua, :uri, :method)");
            $stmt->execute([
                ':ip' => $ip,
                ':type' => $type,
                ':details' => $enriched_details,
                ':ua' => $ua,
                ':uri' => $uri,
                ':method' => $method
            ]);
        }
    } catch (Exception $e) {
        // Silently fail
    }
}
?>