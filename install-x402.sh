#!/bin/bash
# Installation script for X402 Paywall plugin
# Run this after cloning/downloading the plugin

echo "🚀 X402 Paywall Plugin - Installation Script"
echo "=============================================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer not found!"
    echo ""
    echo "Please install Composer first:"
    echo "  macOS: brew install composer"
    echo "  Linux: https://getcomposer.org/download/"
    echo ""
    exit 1
fi

echo "✅ Composer found"
echo ""

# Navigate to plugin directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "📍 Plugin directory: $SCRIPT_DIR"
echo ""

# Install dependencies
echo "📦 Installing X402 PHP library and dependencies..."
echo ""

composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ X402 library installed successfully!"
    echo ""
    echo "📋 Next steps:"
    echo "  1. Activate the plugin in WordPress admin"
    echo "  2. Go to Users → Your Profile"
    echo "  3. Configure your payment wallet addresses"
    echo "  4. Create a post/page and enable X402 Paywall"
    echo ""
    echo "📚 Documentation:"
    echo "  - README.md - Quick start guide"
    echo "  - INSTALLATION.md - Detailed installation"
    echo "  - TOKEN_DETECTION_GUIDE.md - Custom token setup"
    echo ""
    echo "✨ Plugin ready to use!"
else
    echo ""
    echo "❌ Installation failed!"
    echo ""
    echo "Troubleshooting:"
    echo "  - Check composer.json is present"
    echo "  - Run: composer diagnose"
    echo "  - Check PHP version >= 8.1"
    echo ""
    exit 1
fi
