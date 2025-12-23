<?php
/**
 * Force register CodGuard hooks with proper shop context
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>Force Register CodGuard Hooks</h1>";

// Load module
$module = Module::getInstanceByName('codguard');

if (!$module || !$module->id) {
    echo "<p style='color: red;'>ERROR: Module not installed!</p>";
    exit;
}

echo "<p>Module ID: " . $module->id . "</p>";

// Get all shops
$shops = Shop::getShops();
echo "<p>Found " . count($shops) . " shop(s)</p>";

// Hooks to register
$hooks_to_register = [
    'actionPresentPaymentOptions',
    'actionOrderStatusPostUpdate',
    'actionValidateOrder',
    'displayPaymentReturn',
    'displayPaymentTop',
    'displayPayment',
    'displayHeader',
    'actionFrontControllerSetMedia'
];

echo "<h2>Registering hooks...</h2><ul>";

foreach ($shops as $shop) {
    $shop_id = $shop['id_shop'];
    echo "<li><strong>Shop ID: $shop_id</strong><ul>";

    foreach ($hooks_to_register as $hook_name) {
        // Unregister first (in case it exists but is broken)
        $hook_id = Hook::getIdByName($hook_name);
        if ($hook_id) {
            Db::getInstance()->delete('hook_module', 'id_module = ' . (int)$module->id . ' AND id_hook = ' . (int)$hook_id . ' AND id_shop = ' . (int)$shop_id);
        }

        // Register
        $result = $module->registerHook($hook_name, [$shop_id]);

        if ($result) {
            echo "<li style='color: green;'>✓ $hook_name</li>";
        } else {
            echo "<li style='color: red;'>✗ $hook_name FAILED</li>";
        }
    }

    echo "</ul></li>";
}

echo "</ul>";

echo "<h2>Verification:</h2><ul>";
$hooks = Hook::getHooks();
foreach ($hooks as $hook) {
    if (Hook::isModuleRegisteredOnHook($module, $hook['id_hook'], Context::getContext()->shop->id)) {
        echo "<li>" . $hook['name'] . "</li>";
    }
}
echo "</ul>";

echo "<p style='background: #d4edda; padding: 10px; margin-top: 20px;'><strong>Done!</strong> Delete this file now.</p>";
