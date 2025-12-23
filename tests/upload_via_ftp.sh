#!/bin/bash

# Upload CodGuard module files via FTP
HOST="prestashop.codguard.com"
USER="prestashop"
PASS="i4fxKAd9vc"

echo "==================================="
echo "CodGuard Module Upload via FTP"
echo "==================================="
echo ""

MODULE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "Module directory: $MODULE_DIR"
echo ""

echo "1. Creating remote directories (if needed)..."
echo ""

# Create directories and upload files
ftp -inv $HOST << EOF
user $USER $PASS
binary
cd www/prestashop/modules/codguard
mkdir views
cd views
mkdir css
mkdir js
cd css
put $MODULE_DIR/views/css/codguard.css
cd ..
cd js
put $MODULE_DIR/views/js/codguard.js
cd ../..
put $MODULE_DIR/codguard.php
bye
EOF

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Files uploaded successfully!"
    echo ""
    echo "==================================="
    echo "✓ Upload completed!"
    echo "==================================="
    echo ""
    echo "Next steps:"
    echo "1. Clear PrestaShop cache manually or via admin"
    echo "2. Test the checkout page"
    echo ""
else
    echo ""
    echo "✗ Error during FTP upload!"
    exit 1
fi
