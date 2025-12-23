<?php
/**
 * Fix CodGuard API keys - restore correct values from .env
 */

require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Fixing CodGuard API Keys ===\n\n";

// Correct values from .env
$correct_shop_id = '25179266';
$correct_public_key = 'wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38';
$correct_private_key = 'wt-86d53ffbc7265d4428a33b6cdb539bf482d6400423a61b35488a2b92b091b481';

echo "Current values:\n";
echo "Shop ID: " . Configuration::get('CODGUARD_SHOP_ID') . "\n";
echo "Public Key: " . Configuration::get('CODGUARD_PUBLIC_KEY') . "\n";
echo "Private Key: " . Configuration::get('CODGUARD_PRIVATE_KEY') . "\n\n";

echo "Updating configuration...\n";

Configuration::updateValue('CODGUARD_SHOP_ID', $correct_shop_id);
Configuration::updateValue('CODGUARD_PUBLIC_KEY', $correct_public_key);
Configuration::updateValue('CODGUARD_PRIVATE_KEY', $correct_private_key);

echo "\nNew values:\n";
echo "Shop ID: " . Configuration::get('CODGUARD_SHOP_ID') . "\n";
echo "Public Key: " . Configuration::get('CODGUARD_PUBLIC_KEY') . "\n";
echo "Private Key: " . Configuration::get('CODGUARD_PRIVATE_KEY') . "\n\n";

echo "Testing API with new key:\n";
$url = 'https://api.codguard.com/api/customer-rating/' . $correct_shop_id . '/1825@privaterelay.appleid.com';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'x-api-key: ' . $correct_public_key
));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "\n";
echo "Response: " . $response . "\n";

if ($http_code == 200) {
    $data = json_decode($response, true);
    echo "\n✓ API working correctly!\n";
    echo "Customer rating: " . ($data['rating'] * 100) . "%\n";
    echo "Tolerance: " . Configuration::get('CODGUARD_RATING_TOLERANCE') . "%\n";
    if (($data['rating'] * 100) < Configuration::get('CODGUARD_RATING_TOLERANCE')) {
        echo "=> COD should be BLOCKED\n";
    } else {
        echo "=> COD should be ALLOWED\n";
    }
} else {
    echo "\n✗ API still returning error\n";
}

echo "\n✓ Configuration updated!\n";
echo "\nDELETE THIS FILE FOR SECURITY.\n";
