<?php
require_once "database.php";

/**
 * Database-based Rate Limiter to prevent brute-force / spam.
 *
 * @param string $action_name Unique identifier for the action (e.g. 'contact_request')
 * @param int $max_requests Maximum allowed requests in the time window
 * @param int $seconds Time window in seconds (e.g. 600 = 10 minutes)
 */
function checkRateLimit(string $action_name = 'global', int $max_requests = 5, int $seconds = 600): void
{
    // 1. Establish Database Connection directly inside the Rate Limiter
    $dbInstance = new Database();
    $db = $dbInstance->getConnection();

    if (!$db) {
        // Fallback or error handling if DB connection fails
        http_response_code(500);
        echo "Database connection error.";
        exit();
    }

    // 2. Determine client IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    // 3. Clean up expired logs older than the time window
    $cleanup_stmt = $db->prepare("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL :seconds SECOND)");
    $cleanup_stmt->execute(['seconds' => $seconds]);

    // 4. Count attempts in the current time window
    $count_stmt = $db->prepare("
        SELECT COUNT(*) FROM rate_limits 
        WHERE ip_address = :ip 
          AND action_name = :action 
          AND created_at >= (NOW() - INTERVAL :seconds SECOND)
    ");
    $count_stmt->execute([
        'ip' => $ip,
        'action' => $action_name,
        'seconds' => $seconds
    ]);

    $attempt_count = (int) $count_stmt->fetchColumn();

    // 5. Block request if limit is reached
    if ($attempt_count >= $max_requests) {
        http_response_code(429); // 429 Too Many Requests
        header('Retry-After: ' . $seconds);
        echo "Too many requests. Please wait " . ceil($seconds / 60) . " minute(s).";
        exit();
    }

    // 6. Log current attempt
    $insert_stmt = $db->prepare("INSERT INTO rate_limits (ip_address, action_name) VALUES (:ip, :action)");
    $insert_stmt->execute(['ip' => $ip, 'action' => $action_name]);
}
