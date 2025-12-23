<?php
/**
 * Simple module reinstall script
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>CodGuard Module Simple Reinstall</h1>\n";

// Load module
$module = Module::getInstanceByName('codguard');

if (!$module) {
    echo "<p style='color: red;'>ERROR: Module not found!</p>\n";
    exit;
}

echo "<p>Module found: " . $module->displayName . " (ID: " . $module->id . ")</p>\n";

// Check if installed
if (!$module->id) {
    echo "<p>Module not installed. Installing...</p>\n";
    if ($module->install()) {
        echo "<p style='color: green;'>✓ Module installed successfully!</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Installation failed!</p>\n";
        exit;
    }
} else {
    echo "<p>Module is installed. Reinstalling...</p>\n";

    // Uninstall
    echo "<h2>1. Uninstalling...</h2>\n";
    if (!$module->uninstall()) {
        echo "<p style='color: red;'>✗ Uninstall failed!</p>\n";
        exit;
    }
    echo "<p style='color: green;'>✓ Uninstalled</p>\n";

    // Get fresh instance
    $module = Module::getInstanceByName('codguard');

    // Install
    echo "<h2>2. Installing...</h2>\n";
    if (!$module->install()) {
        echo "<p style='color: red;'>✗ Install failed!</p>\n";
        exit;
    }
    echo "<p style='color: green;'>✓ Installed</p>\n";
}

// Verify hooks are registered
echo "<h2>3. Verifying hooks...</h2>\n<ul>\n";
$module = Module::getInstanceByName('codguard');

$hooks_to_check = [
    'actionFrontControllerSetMedia',
    'displayPaymentTop',
    'displayPayment',
    'displayHeader',
    'actionPresentPaymentOptions'
];

foreach ($hooks_to_check as $hook_name) {
    $hook_id = Hook::getIdByName($hook_name);
    if ($hook_id) {
        $registered = Hook::isModuleRegisteredOnHook($module, $hook_id, Context::getContext()->shop->id);
        $color = $registered ? 'green' : 'red';
        $symbol = $registered ? '✓' : '✗';
        echo "<li style='color: $color;'>$symbol $hook_name</li>\n";
    }
}
echo "</ul>\n";

// Clear cache
echo "<h2>4. Clearing cache...</h2>\n";
Tools::clearCache();
echo "<p style='color: green;'>✓ Cache cleared</p>\n";

echo "<h2 style='color: green;'>✓ Done!</h2>\n";
echo "<p><strong>Delete this file now for security.</strong></p>\n";
