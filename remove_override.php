<?php
/**
 * Remove PaymentOptionsFinder override
 */

require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Removing PaymentOptionsFinder Override ===\n\n";

$override_file = _PS_ROOT_DIR_ . '/override/classes/checkout/PaymentOptionsFinder.php';

if (file_exists($override_file)) {
    if (unlink($override_file)) {
        echo "✓ Override file deleted: $override_file\n";
    } else {
        echo "✗ Failed to delete override file\n";
    }
} else {
    echo "Override file does not exist\n";
}

// Delete class index to force rebuild
$class_index = _PS_ROOT_DIR_ . '/cache/class_index.php';
if (file_exists($class_index)) {
    unlink($class_index);
    echo "✓ Deleted class index\n";
}

// Clear caches
Tools::clearCache();
Tools::clearSmartyCache();
Tools::clearXMLCache();
Media::clearCache();

echo "✓ Cleared all caches\n";

// Rebuild class index
PrestaShopAutoload::getInstance()->generateIndex();
echo "✓ Regenerated class index\n";

echo "\n✓ Override removed successfully!\n";
echo "\nDELETE THIS FILE FOR SECURITY.\n";
