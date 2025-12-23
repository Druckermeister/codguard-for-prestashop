<?php
/**
 * Get the actual PHP error that's causing the 500
 */

// Enable error display temporarily
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "=== Testing Module Load ===\n\n";

try {
    require_once(__DIR__ . '/config/config.inc.php');
    echo "✓ config.inc.php loaded\n";

    // Try to load the module
    $module = Module::getInstanceByName('codguard');
    if ($module) {
        echo "✓ Module instance created\n";
        echo "Module active: " . ($module->active ? 'Yes' : 'No') . "\n";

        // Try calling a hook
        echo "\nTesting hookActionFrontControllerSetMedia...\n";
        ob_start();
        $result = $module->hookActionFrontControllerSetMedia(array());
        $output = ob_get_clean();
        echo "✓ Hook called successfully\n";
        if ($output) {
            echo "Hook output: $output\n";
        }
    } else {
        echo "✗ Failed to get module instance\n";
    }

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n\nDELETE THIS FILE FOR SECURITY.\n";
