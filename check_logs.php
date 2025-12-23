<?php
require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Recent CodGuard Logs ===\n\n";

$sql = 'SELECT date_add, message, severity
        FROM '._DB_PREFIX_.'log
        WHERE message LIKE "%CodGuard%"
        ORDER BY date_add DESC
        LIMIT 50';

$logs = Db::getInstance()->executeS($sql);

if ($logs) {
    foreach ($logs as $log) {
        $severity = '';
        switch ($log['severity']) {
            case 1: $severity = '[INFO]'; break;
            case 2: $severity = '[WARN]'; break;
            case 3: $severity = '[ERROR]'; break;
            default: $severity = '[LOG]';
        }
        echo $log['date_add'] . ' ' . $severity . ' ' . $log['message'] . "\n";
    }
} else {
    echo "No logs found.\n";
}

echo "\nDELETE THIS FILE FOR SECURITY.\n";
