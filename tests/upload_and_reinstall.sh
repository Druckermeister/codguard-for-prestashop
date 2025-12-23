#!/bin/bash

# Upload and reinstall CodGuard module
# This script uploads the new files and clears the cache

HOST="prestashop.codguard.com.uvds288.active24.cz"
USER="prestashop"
PASS="i4fxKAd9vc"
PORT="10222"
REMOTE_PATH="/modules/codguard"

echo "==================================="
echo "CodGuard Module Upload & Reinstall"
echo "==================================="
echo ""

# Get the module directory
MODULE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "Module directory: $MODULE_DIR"
echo ""

# Upload the new files via SFTP
echo "1. Uploading new files to server..."
echo ""

# Create SFTP batch file
cat > /tmp/codguard_upload.sftp << EOF
cd $REMOTE_PATH
put $MODULE_DIR/views/css/codguard.css views/css/codguard.css
put $MODULE_DIR/views/js/codguard.js views/js/codguard.js
put $MODULE_DIR/codguard.php codguard.php
bye
EOF

# Upload via SFTP
sshpass -p "$PASS" sftp -P $PORT -o StrictHostKeyChecking=no -b /tmp/codguard_upload.sftp $USER@$HOST

if [ $? -eq 0 ]; then
    echo "✓ Files uploaded successfully!"
else
    echo "✗ Error uploading files!"
    exit 1
fi

echo ""
echo "2. Clearing PrestaShop cache via SSH..."
echo ""

# Clear cache via SSH
sshpass -p "$PASS" ssh -p $PORT -o StrictHostKeyChecking=no $USER@$HOST << 'ENDSSH'
cd www/prestashop
rm -rf var/cache/*
echo "✓ Cache cleared!"
ENDSSH

echo ""
echo "==================================="
echo "✓ Module update completed!"
echo "==================================="
echo ""
echo "The new CSS and JS files are now uploaded."
echo "You can test the checkout page now."
echo ""
