<?php
require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Clearing PrestaShop Cache ===\n\n";

// Delete class index
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

echo "\n✓ Cache cleared successfully!\n";
echo "\nDELETE THIS FILE FOR SECURITY.\n";
