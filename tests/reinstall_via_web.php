<?php
// Simple script to trigger module reinstall via web
// Upload this to the PrestaShop root and visit it

// Navigate to PrestaShop root
chdir(__DIR__ . '/../');

// Include PrestaShop config
require_once('config/config.inc.php');

echo "<h1>CodGuard Module Reinstall</h1>\n";

// Load the module
$module = Module::getInstanceByName('codguard');

if (!$module) {
    echo "<p style='color:red;'>ERROR: Module not found!</p>\n";
    exit;
}

echo "<p>Module found: " . $module->displayName . "</p>\n";

// Unregister old hooks
echo "<h2>1. Unregistering old hooks...</h2>\n";
$hooks = ['displayPayment', 'displayHeader'];
foreach ($hooks as $hook) {
    if ($module->unregisterHook($hook)) {
        echo "<p>✓ Unregistered: $hook</p>\n";
    }
}

// Register new hooks
echo "<h2>2. Registering new hooks...</h2>\n";
foreach ($hooks as $hook) {
    if ($module->registerHook($hook)) {
        echo "<p>✓ Registered: $hook</p>\n";
    } else {
        echo "<p style='color:orange;'>⚠ Already registered or failed: $hook</p>\n";
    }
}

// Clear cache
echo "<h2>3. Clearing cache...</h2>\n";
Tools::clearCache();
echo "<p>✓ Cache cleared</p>\n";

echo "<h2>✓ Done!</h2>\n";
echo "<p>New hooks registered. Test the checkout page now.</p>\n";
?>
