<?php
/**
 * Clear all PrestaShop caches
 */

require_once(__DIR__ . '/config/config.inc.php');

header('Content-Type: text/plain');

echo "=== Clearing PrestaShop Caches ===\n\n";

// Clear class index
$class_index = _PS_ROOT_DIR_ . '/cache/class_index.php';
if (file_exists($class_index)) {
    unlink($class_index);
    echo "✓ Deleted class index\n";
}

// Clear var/cache
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
    echo "✓ Cleared var/cache\n";
}

// Clear all caches
Tools::clearCache();
Tools::clearSmartyCache();
Tools::clearXMLCache();
Media::clearCache();

echo "✓ Cleared all caches\n";
echo "\n✓ Cache cleared successfully!\n";
echo "\nDELETE THIS FILE FOR SECURITY.\n";
