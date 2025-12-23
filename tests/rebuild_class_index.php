<?php
/**
 * Rebuild PrestaShop class index to recognize override
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>Rebuild Class Index</h1>\n";

// Delete class index
$class_index = _PS_ROOT_DIR_ . '/cache/class_index.php';
if (file_exists($class_index)) {
    unlink($class_index);
    echo "<p>✓ Deleted old class index</p>\n";
}

// Delete var/cache
$cache_dir = _PS_ROOT_DIR_ . '/var/cache';
if (file_exists($cache_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        @$todo($fileinfo->getRealPath());
    }
    echo "<p>✓ Cleared var/cache</p>\n";
}

// Clear all caches
Tools::clearCache();
Tools::clearSmartyCache();
Tools::clearXMLCache();
Media::clearCache();

echo "<p>✓ Cleared all caches</p>\n";

// Force PrestaShop to rebuild class index by loading a class
PrestaShopAutoload::getInstance()->generateIndex();
echo "<p>✓ Regenerated class index</p>\n";

// Verify override exists
$override_file = _PS_ROOT_DIR_ . '/override/classes/checkout/PaymentOptionsFinder.php';
if (file_exists($override_file)) {
    echo "<p style='color:green;'>✓ Override file exists: " . $override_file . "</p>\n";

    // Check if it's in the class index
    $class_index_content = file_get_contents(_PS_ROOT_DIR_ . '/cache/class_index.php');
    if (strpos($class_index_content, 'PaymentOptionsFinder') !== false) {
        echo "<p style='color:green;'>✓ PaymentOptionsFinder is in class index</p>\n";
    } else {
        echo "<p style='color:orange;'>⚠ PaymentOptionsFinder NOT in class index yet</p>\n";
    }
} else {
    echo "<p style='color:red;'>✗ Override file not found!</p>\n";
}

echo "<h2 style='color:green;'>✓ Done!</h2>\n";
echo "<p>Class index rebuilt. Try the checkout now.</p>\n";
echo "<p><strong>Delete this file for security.</strong></p>\n";
