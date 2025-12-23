<?php
/**
 * Check what API key is actually stored in PrestaShop configuration
 */

require_once(__DIR__ . '/../config/config.inc.php');

echo "<h1>CodGuard API Key Check</h1>\n";

$shop_id = Configuration::get('CODGUARD_SHOP_ID');
$public_key = Configuration::get('CODGUARD_PUBLIC_KEY');
$private_key = Configuration::get('CODGUARD_PRIVATE_KEY');

echo "<p><strong>Shop ID:</strong> " . htmlspecialchars($shop_id) . "</p>\n";
echo "<p><strong>Public Key:</strong> " . htmlspecialchars($public_key) . "</p>\n";
echo "<p><strong>Public Key Length:</strong> " . strlen($public_key) . "</p>\n";
echo "<p><strong>Private Key:</strong> " . htmlspecialchars($private_key) . "</p>\n";
echo "<p><strong>Private Key Length:</strong> " . strlen($private_key) . "</p>\n";

echo "<h2>Expected Values from .env:</h2>\n";
echo "<p><strong>Expected Shop ID:</strong> 25179266</p>\n";
echo "<p><strong>Expected Public Key:</strong> wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38</p>\n";
echo "<p><strong>Expected Private Key:</strong> wt-86d53ffbc7265d4428a33b6cdb539bf482d6400423a61b35488a2b92b091b481</p>\n";

echo "<h2>Comparison:</h2>\n";
if ($shop_id === '25179266') {
    echo "<p style='color:green;'>✓ Shop ID matches</p>\n";
} else {
    echo "<p style='color:red;'>✗ Shop ID does NOT match</p>\n";
}

if ($public_key === 'wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38') {
    echo "<p style='color:green;'>✓ Public Key matches</p>\n";
} else {
    echo "<p style='color:red;'>✗ Public Key does NOT match</p>\n";
}

if ($private_key === 'wt-86d53ffbc7265d4428a33b6cdb539bf482d6400423a61b35488a2b92b091b481') {
    echo "<p style='color:green;'>✓ Private Key matches</p>\n";
} else {
    echo "<p style='color:red;'>✗ Private Key does NOT match</p>\n";
}

echo "<p style='margin-top: 20px;'><strong>Delete this file for security.</strong></p>\n";
