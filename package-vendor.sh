#!/bin/bash
# Vendor Dependencies Package Creator
# Creates a separate vendor.zip that can be distributed with the plugin

set -e

echo "📦 Creating vendor dependencies package..."

VENDOR_VERSION="1.1.0"
VENDOR_FILE="vendor-${VENDOR_VERSION}.zip"

# Check if vendor exists
if [ ! -d "vendor" ]; then
    echo "❌ Error: vendor/ directory not found"
    echo "Run 'composer install --no-dev' first"
    exit 1
fi

# Clean previous vendor packages
rm -f vendor-*.zip vendor-*.zip.sha256

# Create vendor package
echo "🗜️  Compressing vendor directory..."
zip -r "$VENDOR_FILE" vendor/ -q

# Create checksum
echo "🔐 Creating checksum..."
sha256sum "$VENDOR_FILE" > "${VENDOR_FILE}.sha256"

FILESIZE=$(du -h "$VENDOR_FILE" | cut -f1)

echo ""
echo "✅ Vendor package created!"
echo "📦 File: $VENDOR_FILE"
echo "📏 Size: $FILESIZE"
echo "🔐 SHA256: ${VENDOR_FILE}.sha256"
echo ""
echo "SHA256:"
cat "${VENDOR_FILE}.sha256"
echo ""
echo "To use:"
echo "1. Extract in plugin root: unzip $VENDOR_FILE"
echo "2. Or include in distribution package"
