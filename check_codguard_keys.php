<?php
require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== CodGuard API Key Check ===\n\n";

$shop_id = Configuration::get('CODGUARD_SHOP_ID');
$public_key = Configuration::get('CODGUARD_PUBLIC_KEY');

echo "Shop ID: " . $shop_id . "\n";
echo "Public Key: " . $public_key . "\n";
echo "Public Key Length: " . strlen($public_key) . "\n\n";

echo "Expected from .env:\n";
echo "Shop ID: 25179266\n";
echo "Public Key: wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38\n\n";

if ($shop_id === '25179266') {
    echo "✓ Shop ID matches\n";
} else {
    echo "✗ Shop ID MISMATCH\n";
}

if ($public_key === 'wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38') {
    echo "✓ Public Key matches\n";
} else {
    echo "✗ Public Key MISMATCH\n";
}

echo "\nTesting API with stored key:\n";
$url = 'https://api.codguard.com/api/customer-rating/' . $shop_id . '/1825@privaterelay.appleid.com';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'x-api-key: ' . $public_key
));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "\n";
echo "Response: " . $response . "\n";

if ($http_code == 200) {
    echo "\n✓ API working correctly\n";
} else {
    echo "\n✗ API returning error\n";
}
