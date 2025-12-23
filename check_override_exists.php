<?php
require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

$override_file = _PS_ROOT_DIR_ . '/override/classes/checkout/PaymentOptionsFinder.php';

echo "Checking for PaymentOptionsFinder override...\n\n";
echo "Path: $override_file\n";

if (file_exists($override_file)) {
    echo "✓ Override file EXISTS\n";
    echo "Size: " . filesize($override_file) . " bytes\n";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($override_file)) . "\n";
} else {
    echo "✗ Override file DOES NOT EXIST\n";
}

echo "\nDELETE THIS FILE FOR SECURITY.\n";
