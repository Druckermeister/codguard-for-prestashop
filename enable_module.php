<?php
/**
 * Re-enable CodGuard module
 */

require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Enabling CodGuard Module ===\n\n";

$module = Module::getInstanceByName('codguard');
if ($module) {
    if ($module->enable()) {
        echo "✓ Module enabled successfully\n";
    } else {
        echo "✗ Failed to enable module\n";
    }
} else {
    echo "✗ Module not found\n";
}

// Clear cache
Tools::clearCache();
Tools::clearSmartyCache();
echo "✓ Cache cleared\n";

echo "\nDELETE THIS FILE FOR SECURITY.\n";
