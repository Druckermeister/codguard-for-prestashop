#!/bin/bash

# PrestaShop CodGuard Module - Deploy and Test Script
# This script uploads the module and runs automated tests

set -e  # Exit on error

echo "======================================"
echo "PrestaShop CodGuard Deploy & Test"
echo "======================================"
echo ""

FTP_HOST="prestashop.codguard.com.uvds288.active24.cz"
FTP_USER="prestashop"
FTP_PASS="i4fxKAd9vc"
LOCAL_DIR="/home/tamas/Documents/Github/codguard-for-prestashop"

# Step 1: Upload module file
echo "[1/4] Uploading codguard.php to server..."
lftp -c "set ssl:verify-certificate no; \
         open -u $FTP_USER,$FTP_PASS ftp://$FTP_HOST; \
         cd www/modules/codguard; \
         put $LOCAL_DIR/codguard.php"

if [ $? -eq 0 ]; then
    echo "  ✓ Module uploaded successfully"
else
    echo "  ✗ Upload failed!"
    exit 1
fi

# Step 2: Check if we need to register hooks
echo ""
echo "[2/4] Checking if hooks need registration..."

# Upload and run hook registration check script
cat > /tmp/check_hooks.php << 'EOF'
<?php
require_once(__DIR__ . '/config/config.inc.php');
$module = Module::getInstanceByName('codguard');
if (!$module) { echo "MODULE_NOT_FOUND"; exit; }

$requiredHooks = ['actionPresentPaymentOptions', 'actionOrderStatusPostUpdate'];
$missingHooks = [];

foreach ($requiredHooks as $hookName) {
    $hook = Hook::getIdByName($hookName);
    if (!$hook || !Hook::isModuleRegisteredOnHook($module, $hook, Context::getContext()->shop->id)) {
        $missingHooks[] = $hookName;
    }
}

if (empty($missingHooks)) {
    echo "ALL_REGISTERED";
} else {
    echo "MISSING:" . implode(',', $missingHooks);
}
EOF

lftp -c "set ssl:verify-certificate no; \
         open -u $FTP_USER,$FTP_PASS ftp://$FTP_HOST; \
         cd www; \
         put /tmp/check_hooks.php"

HOOK_STATUS=$(curl -s "http://$FTP_HOST/check_hooks.php")

if [[ "$HOOK_STATUS" == "ALL_REGISTERED" ]]; then
    echo "  ✓ All hooks already registered"
elif [[ "$HOOK_STATUS" == "MODULE_NOT_FOUND" ]]; then
    echo "  ✗ Module not installed! Please install it via admin panel first."
    exit 1
else
    echo "  ⚠ Missing hooks detected, registering now..."

    # Upload and run registration script
    cat > /tmp/register_hooks_auto.php << 'EOF'
<?php
require_once(__DIR__ . '/config/config.inc.php');
$module = Module::getInstanceByName('codguard');
if (!$module) { echo "ERROR"; exit; }

$hooks = ['actionPresentPaymentOptions', 'actionOrderStatusPostUpdate', 'actionValidateOrder', 'displayPaymentReturn', 'actionFrontControllerSetMedia'];
foreach ($hooks as $hookName) {
    $module->registerHook($hookName);
}
echo "SUCCESS";
EOF

    lftp -c "set ssl:verify-certificate no; \
             open -u $FTP_USER,$FTP_PASS ftp://$FTP_HOST; \
             cd www; \
             put /tmp/register_hooks_auto.php"

    REGISTER_RESULT=$(curl -s "http://$FTP_HOST/register_hooks_auto.php")

    if [[ "$REGISTER_RESULT" == "SUCCESS" ]]; then
        echo "  ✓ Hooks registered successfully"
    else
        echo "  ✗ Hook registration failed!"
        exit 1
    fi
fi

# Clean up check/register scripts
lftp -c "set ssl:verify-certificate no; \
         open -u $FTP_USER,$FTP_PASS ftp://$FTP_HOST; \
         cd www; \
         rm -f check_hooks.php register_hooks_auto.php" 2>/dev/null || true

# Step 3: Test Rating API
echo ""
echo "[3/4] Testing Rating API (payment blocking)..."
node /tmp/test_prestashop_rating_api.js

# Step 4: Test Order Upload
echo ""
echo "[4/4] Testing Order Upload API..."
node /tmp/test_prestashop_order_upload.js

echo ""
echo "======================================"
echo "Deployment Complete!"
echo "======================================"
echo ""
echo "Module location: /www/modules/codguard/"
echo "Review screenshots in /tmp/ for test results"
