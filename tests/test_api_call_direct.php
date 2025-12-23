<?php
/**
 * Direct API test - simulates what the module does during checkout
 * This will make a real API call and log it
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>CodGuard API Test</h1>";

// Generate sequential test email
$testEmail = 'testuser' . time() . '@example.com';
echo "<p><strong>Test Email:</strong> $testEmail</p>";

// Get module configuration
$shopId = Configuration::get('CODGUARD_SHOP_ID');
$publicKey = Configuration::get('CODGUARD_PUBLIC_KEY');

echo "<p><strong>Shop ID:</strong> $shopId</p>";
echo "<p><strong>Public Key:</strong> " . substr($publicKey, 0, 20) . "...</p>";

if (empty($shopId) || empty($publicKey)) {
    echo "<p style='color: red;'>ERROR: API keys not configured!</p>";
    exit;
}

// Make API call
$url = 'https://api.codguard.com/api/customer-rating/' . urlencode($shopId) . '/' . urlencode($testEmail);

echo "<h2>Making API Request</h2>";
echo "<p><strong>URL:</strong> $url</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'x-api-key: ' . $publicKey
));

$startTime = microtime(true);
$fullResponse = curl_exec($ch);
$endTime = microtime(true);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

$headers = substr($fullResponse, 0, $headerSize);
$response = substr($fullResponse, $headerSize);

curl_close($ch);

$duration = round(($endTime - $startTime) * 1000, 2);

echo "<h2>API Response</h2>";
echo "<p><strong>Duration:</strong> {$duration}ms</p>";
echo "<p><strong>HTTP Status:</strong> $httpCode</p>";

if ($curlError) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $curlError</p>";
}

echo "<h3>Response Headers:</h3>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h3>Response Body:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Parse response
if ($httpCode == 404) {
    echo "<p style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
    echo "<strong>Result:</strong> New customer (404) - Would allow checkout with rating 1.0";
    echo "</p>";
} elseif ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['rating'])) {
        $rating = (float)$data['rating'];
        $ratingPercent = $rating * 100;
        $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');

        echo "<p style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
        echo "<strong>Rating:</strong> $rating ($ratingPercent%)<br>";
        echo "<strong>Tolerance:</strong> $tolerance%<br>";

        if ($ratingPercent < $tolerance) {
            echo "<strong style='color: red;'>Action:</strong> Would BLOCK payment methods (COD)";
        } else {
            echo "<strong style='color: green;'>Action:</strong> Would ALLOW all payment methods";
        }
        echo "</p>";
    } else {
        echo "<p style='color: red;'>Invalid response - missing rating field</p>";
    }
} else {
    echo "<p style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545;'>";
    echo "<strong>Error:</strong> API returned HTTP $httpCode";
    echo "</p>";
}

// Also log to PrestaShop logger
PrestaShopLogger::addLog("CodGuard [TEST]: API call for $testEmail - HTTP $httpCode - Response: $response", 1);

echo "<hr>";
echo "<p style='background: #f0f0f0; padding: 10px;'>";
echo "<strong>Done!</strong> Check PrestaShop logs for the logged entry. Delete this file for security.";
echo "</p>";
