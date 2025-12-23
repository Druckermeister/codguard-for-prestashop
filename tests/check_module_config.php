<?php
/**
 * Check CodGuard module configuration
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>CodGuard Module Configuration Check</h1>\n";

// Check if module is enabled
$enabled = Configuration::get('CODGUARD_ENABLED');
echo "<p>Module Enabled: " . ($enabled ? 'YES' : 'NO') . "</p>\n";

// Check API keys
$shop_id = Configuration::get('CODGUARD_SHOP_ID');
$public_key = Configuration::get('CODGUARD_PUBLIC_KEY');
$private_key = Configuration::get('CODGUARD_PRIVATE_KEY');

echo "<p>Shop ID: " . ($shop_id ?: '(not set)') . "</p>\n";
echo "<p>Public Key: " . ($public_key ? '(set, length: ' . strlen($public_key) . ')' : '(not set)') . "</p>\n";
echo "<p>Private Key: " . ($private_key ? '(set, length: ' . strlen($private_key) . ')' : '(not set)') . "</p>\n";

// Check tolerance
$tolerance = Configuration::get('CODGUARD_RATING_TOLERANCE');
echo "<p>Rating Tolerance: " . $tolerance . "%</p>\n";

// Check rejection message
$message = Configuration::get('CODGUARD_REJECTION_MESSAGE');
echo "<p>Rejection Message: " . htmlspecialchars($message) . "</p>\n";

// Check payment methods
$methods = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true);
echo "<p>Blocked Payment Methods: " . implode(', ', $methods ?: array()) . "</p>\n";

// Check if hooks are registered
$module = Module::getInstanceByName('codguard');
if ($module) {
    echo "<h2>Hook Registration Status</h2>\n<ul>\n";

    $hooks_to_check = [
        'actionFrontControllerSetMedia',
        'displayPaymentTop',
        'displayPayment',
        'displayHeader',
        'actionPresentPaymentOptions'
    ];

    foreach ($hooks_to_check as $hook_name) {
        $hook = Hook::getIdByName($hook_name);
        if ($hook) {
            $registered = Hook::isModuleRegisteredOnHook($module, $hook, Context::getContext()->shop->id);
            echo "<li>" . $hook_name . ": " . ($registered ? 'YES' : 'NO') . "</li>\n";
        } else {
            echo "<li>" . $hook_name . ": (hook doesn't exist)</li>\n";
        }
    }

    echo "</ul>\n";
}

// Test API with a known email
echo "<h2>Test API Call</h2>\n";
$test_email = '1431@privaterelay.appleid.com';

if ($shop_id && $public_key) {
    $url = 'https://api.codguard.com/api/customer-rating/' . $shop_id . '/' . urlencode($test_email);

    echo "<p>Testing API with email: " . htmlspecialchars($test_email) . "</p>\n";
    echo "<p>URL: " . htmlspecialchars($url) . "</p>\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'x-api-key: ' . $public_key
    ));

    $full_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response = substr($full_response, $header_size);
    curl_close($ch);

    echo "<p>HTTP Status: " . $http_code . "</p>\n";

    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['rating'])) {
            $rating_pct = $data['rating'] * 100;
            echo "<p style='color: " . ($rating_pct < $tolerance ? 'red' : 'green') . "'>Rating: " . $rating_pct . "%</p>\n";
            echo "<p>Would block: " . ($rating_pct < $tolerance ? 'YES' : 'NO') . "</p>\n";
        } else {
            echo "<p style='color: red;'>Invalid API response</p>\n";
        }
    } elseif ($http_code == 404) {
        echo "<p>Customer not found (404) - would allow with rating 100%</p>\n";
    } else {
        echo "<p style='color: red;'>API error: " . $http_code . "</p>\n";
        echo "<pre>" . htmlspecialchars($response) . "</pre>\n";
    }
} else {
    echo "<p style='color: red;'>Cannot test API - missing configuration</p>\n";
}

echo "<p style='margin-top: 20px;'><strong>Done!</strong> Delete this file for security.</p>\n";
