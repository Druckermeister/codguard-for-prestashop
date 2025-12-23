<?php
/**
 * Install CodGuard override file
 */

require_once(__DIR__ . '/config/config.inc.php');

echo "<h1>Install CodGuard Override</h1>\n";

// Create directory structure
$dirs = [
    _PS_ROOT_DIR_ . '/override',
    _PS_ROOT_DIR_ . '/override/classes',
    _PS_ROOT_DIR_ . '/override/classes/checkout'
];

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<p>✓ Created: $dir</p>\n";
        } else {
            echo "<p style='color:red;'>✗ Failed to create: $dir</p>\n";
        }
    } else {
        echo "<p>Directory exists: $dir</p>\n";
    }
}

// Copy override file from temp location
$source = _PS_MODULE_DIR_ . 'codguard/PaymentOptionsFinder_override.php';
$dest = _PS_ROOT_DIR_ . '/override/classes/checkout/PaymentOptionsFinder.php';

echo "<h2>Installing Override File</h2>\n";
echo "<p>Source: $source</p>\n";
echo "<p>Dest: $dest</p>\n";

if (!file_exists($source)) {
    echo "<p style='color:red;'>✗ Source file not found!</p>\n";
    exit;
}

if (copy($source, $dest)) {
    echo "<p style='color:green;'>✓ Override file installed</p>\n";
    chmod($dest, 0644);
} else {
    echo "<p style='color:red;'>✗ Failed to copy override file</p>\n";
    exit;
}

// Clear class index to force PrestaShop to rebuild
$class_index = _PS_ROOT_DIR_ . '/cache/class_index.php';
if (file_exists($class_index)) {
    unlink($class_index);
    echo "<p style='color:green;'>✓ Cleared class index cache</p>\n";
}

// Clear cache
Tools::clearCache();
echo "<p style='color:green;'>✓ Cache cleared</p>\n";

echo "<h2 style='color:green;'>✓ Override Installed!</h2>\n";
echo "<p>The override is now active. Payment blocking will work via PHP override.</p>\n";
echo "<p><strong>Delete this file now for security.</strong></p>\n";
