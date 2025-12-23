<?php
// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "=== Testing PaymentOptionsFinder Override ===\n\n";

try {
    require_once(__DIR__ . '/config/config.inc.php');
    echo "✓ Config loaded\n";

    // Try to instantiate PaymentOptionsFinder
    $finder = new PaymentOptionsFinder();
    echo "✓ PaymentOptionsFinder instantiated\n";

    // Try calling find()
    echo "\nCalling find()...\n";
    $options = $finder->find();
    echo "✓ find() executed\n";
    echo "Returned: " . gettype($options) . "\n";

} catch (Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDELETE THIS FILE.\n";
