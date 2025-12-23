<?php
/**
 * Temporarily disable CodGuard module
 */

require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Disabling CodGuard Module ===\n\n";

$module = Module::getInstanceByName('codguard');
if ($module) {
    if ($module->disable()) {
        echo "✓ Module disabled successfully\n";
    } else {
        echo "✗ Failed to disable module\n";
    }
} else {
    echo "✗ Module not found\n";
}

echo "\nDELETE THIS FILE FOR SECURITY.\n";
