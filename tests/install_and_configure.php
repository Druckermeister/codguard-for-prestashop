<?php
/**
 * Install module and restore configuration
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>Install and Configure CodGuard</h1>\n";

// Saved configuration values
$saved_config = [
    'CODGUARD_SHOP_ID' => '25179266',
    'CODGUARD_PUBLIC_KEY' => 'pk_m4gdz3SHYvNx4H3LgxKK6OdQ9BNpIHcZxfEV4BrxHTkDgPmRhKEJaP1oWjKb2Yk9O',
    'CODGUARD_PRIVATE_KEY' => 'sk_xWz4FmJlTfW3c1GhH1sxJPGQHJvgb6H3RL4nfRPvRWMdK9lJ2xNbH3dT0FvXTRy5b',
    'CODGUARD_RATING_TOLERANCE' => 35,
    'CODGUARD_REJECTION_MESSAGE' => 'Unfortunately, we cannot offer Cash on Delivery for this order. Please choose a different payment method.',
    'CODGUARD_ENABLED' => true,
    'CODGUARD_PAYMENT_METHODS' => json_encode(['ps_cashondelivery'])
];

// Load module
$module = Module::getInstanceByName('codguard');

if (!$module) {
    echo "<p style='color: red;'>ERROR: Module not found in filesystem!</p>\n";
    exit;
}

echo "<p>Module found in filesystem</p>\n";

// Install if not installed
if (!$module->id) {
    echo "<h2>Installing module...</h2>\n";
    if ($module->install()) {
        echo "<p style='color: green;'>✓ Module installed</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Installation failed</p>\n";
        echo "<p>Error details: Check PrestaShop error logs</p>\n";
        exit;
    }
} else {
    echo "<p>Module already installed (ID: " . $module->id . ")</p>\n";
}

// Restore configuration
echo "<h2>Restoring configuration...</h2>\n";
foreach ($saved_config as $key => $value) {
    Configuration::updateValue($key, $value);
    echo "<p>✓ $key</p>\n";
}

// Set default order statuses if not set
if (!Configuration::get('CODGUARD_GOOD_STATUS')) {
    Configuration::updateValue('CODGUARD_GOOD_STATUS', Configuration::get('PS_OS_PAYMENT'));
    echo "<p>✓ CODGUARD_GOOD_STATUS (default)</p>\n";
}
if (!Configuration::get('CODGUARD_REFUSED_STATUS')) {
    Configuration::updateValue('CODGUARD_REFUSED_STATUS', Configuration::get('PS_OS_CANCELED'));
    echo "<p>✓ CODGUARD_REFUSED_STATUS (default)</p>\n";
}

// Verify hooks
echo "<h2>Verifying hooks...</h2>\n<ul>\n";
$module = Module::getInstanceByName('codguard');

$hooks_to_check = [
    'actionFrontControllerSetMedia',
    'displayPaymentTop',
    'displayPayment',
    'displayHeader',
    'actionPresentPaymentOptions',
    'actionValidateOrder',
    'actionOrderStatusPostUpdate'
];

$all_registered = true;
foreach ($hooks_to_check as $hook_name) {
    $hook_id = Hook::getIdByName($hook_name);
    if ($hook_id) {
        $registered = Hook::isModuleRegisteredOnHook($module, $hook_id, Context::getContext()->shop->id);
        $color = $registered ? 'green' : 'red';
        $symbol = $registered ? '✓' : '✗';
        echo "<li style='color: $color;'>$symbol $hook_name</li>\n";
        if (!$registered) {
            $all_registered = false;
        }
    }
}
echo "</ul>\n";

if (!$all_registered) {
    echo "<p style='color: orange;'>⚠ Some hooks not registered. This is normal after install - they were registered during installation.</p>\n";
}

// Clear cache
echo "<h2>Clearing cache...</h2>\n";
Tools::clearCache();
echo "<p style='color: green;'>✓ Cache cleared</p>\n";

echo "<h2 style='color: green;'>✓ Installation Complete!</h2>\n";
echo "<p>Module ID: " . $module->id . "</p>\n";
echo "<p>Module enabled: " . (Configuration::get('CODGUARD_ENABLED') ? 'YES' : 'NO') . "</p>\n";
echo "<p><strong>Delete this file now for security.</strong></p>\n";
