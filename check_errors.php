<?php
/**
 * Check PHP error logs
 */

header('Content-Type: text/plain');

echo "=== Recent PHP Errors ===\n\n";

$log_file = __DIR__ . '/var/logs/dev_' . date('Ymd') . '.log';

if (file_exists($log_file)) {
    echo "Reading from: $log_file\n\n";
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -100); // Last 100 lines
    echo implode('', $recent_lines);
} else {
    echo "Log file not found: $log_file\n";

    // Try alternate locations
    $alt_locations = [
        __DIR__ . '/var/logs/dev.log',
        __DIR__ . '/var/logs/' . date('Ymd') . '.log',
        __DIR__ . '/log/error_log.txt'
    ];

    foreach ($alt_locations as $alt) {
        if (file_exists($alt)) {
            echo "\nFound alternate log: $alt\n\n";
            $lines = file($alt);
            $recent_lines = array_slice($lines, -100);
            echo implode('', $recent_lines);
            break;
        }
    }
}
