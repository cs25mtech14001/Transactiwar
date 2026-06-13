<?php
# Atish Kadam - CS25MTECH14003
# Akarsh Dubey - CS25MTECH14001
# Atharva Kale - CS25MTECH11024
# Prashant Kumar Dubey - CS25MTECH14011
# Debdip Choudhuri - CS25MTECH11025
require_once __DIR__ . '/config.php';

/**
 * Logs: <Webpage | Username | Timestamp | Client IP>
 */
function log_activity(string $page, string $username = 'guest'): void {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    $username  = preg_replace('/[^a-zA-Z0-9_@.]/', '', $username); // sanitize for log
    $entry     = "[{$timestamp}] | Page: {$page} | User: {$username} | IP: {$ip}" . PHP_EOL;

    $log_file = LOG_DIR . 'activity.log';

    // Create logs dir if missing
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0750, true);
    }

    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}