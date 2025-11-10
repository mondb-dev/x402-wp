#!/bin/bash
# Build script for X402 Paywall WordPress Plugin
# Creates an installation-ready plugin package with all dependencies

set -e

echo "🔨 Building X402 Paywall Plugin..."

# Configuration
PLUGIN_SLUG="x402-paywall"
BUILD_DIR="build"
DIST_DIR="dist"
VERSION=$(grep "Version:" x402-paywall.php | awk '{print $3}')

# Clean previous builds
echo "🧹 Cleaning previous builds..."
rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG" "$DIST_DIR"

# Copy plugin files
echo "📦 Copying plugin files..."
rsync -av --progress \
    --exclude="$BUILD_DIR" \
    --exclude="$DIST_DIR" \
    --exclude=".git" \
    --exclude=".gitignore" \
    --exclude=".DS_Store" \
    --exclude="*.log" \
    --exclude="node_modules" \
    --exclude=".idea" \
    --exclude=".vscode" \
    --exclude="*.swp" \
    --exclude="*.swo" \
    --exclude="*~" \
    --exclude="composer.lock" \
    --exclude=".git-commit-message.txt" \
    --exclude="build.sh" \
    --exclude="install-dependencies.sh" \
    --exclude="install-x402.sh" \
    --exclude="test-*.php" \
    --exclude="test-*.html" \
    --exclude="migrate-*.php" \
    ./ "$BUILD_DIR/$PLUGIN_SLUG/"

# Ensure vendor directory is included
if [ ! -d "vendor" ]; then
    echo "❌ Error: vendor/ directory not found. Run composer install first."
    exit 1
fi

echo "📚 Copying vendor dependencies..."
cp -r vendor "$BUILD_DIR/$PLUGIN_SLUG/"

# Create README for distribution
echo "📝 Creating distribution README..."
cat > "$BUILD_DIR/$PLUGIN_SLUG/README.txt" << 'EOF'
=== X402 Paywall ===
Contributors: mondb-dev
Tags: paywall, cryptocurrency, blockchain, payments, x402
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.1.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Implement X402 payment protocol paywalls on WordPress. Accept cryptocurrency payments on EVM and Solana blockchains.

== Description ==

X402 Paywall enables content creators to monetize their WordPress content using cryptocurrency payments via the X402 protocol by Coinbase.

**Features:**
* 🔒 Easy paywall setup on posts and pages
* 👁️ Content preview with embedded videos and images
* ⏱️ Configurable access duration (1 day to permanent)
* 💰 Multi-chain support (Ethereum, Base, Optimism, Arbitrum, Polygon, Solana)
* 🪙 Auto-detect ERC-20 and SPL tokens
* 🛡️ Replay attack prevention
* 📊 Payment logging and tracking

**Supported Networks:**
* Ethereum Mainnet
* Base
* Optimism
* Arbitrum
* Polygon
* Solana

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/x402-paywall/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Users → Your Profile to configure your payment wallet addresses
4. Edit any post/page and enable the X402 Paywall in the meta box

**No Composer required** - All dependencies are included!

== Frequently Asked Questions ==

= Do I need to install dependencies? =
No! This plugin comes with all dependencies pre-installed. Just activate and configure.

= What cryptocurrencies can I accept? =
Any ERC-20 token on supported EVM networks (USDC, DAI, etc.) and SPL tokens on Solana.

= How do I set up a paywall? =
1. Configure your wallet address in your WordPress profile
2. Edit a post and enable "X402 Paywall" in the meta box
3. Choose your network, token, and price
4. Publish!

= Can I show a preview of paywalled content? =
Yes! Choose from 5 preview options: no preview, 100/250/500 words, or custom using <!--more--> tag.

= How long can users access content after paying? =
You configure this per post: 1 day, 1 week, 1 month, 3 months, 6 months, 1 year, or permanent.

== Screenshots ==

1. Meta box for configuring paywall settings
2. User profile wallet address configuration
3. Public paywall display
4. Content preview with gradient fade

== Changelog ==

= 1.1.0 =
* Added content preview system with 5 configurable options
* Added configurable access duration per content
* Integrated X402 protocol library v1.1.0+ with NonceTrackerInterface
* Added replay attack prevention with database-backed nonce tracking
* Added automated cron jobs for cleanup
* Smart HTML trimming preserves embedded media
* Comprehensive documentation added

= 1.0.0 =
* Initial release
* Basic paywall functionality
* EVM and Solana support
* Auto-token detection
* Payment logging

== Upgrade Notice ==

= 1.1.0 =
Major update with content preview, configurable access duration, and enhanced security. Fully backward compatible.

== Additional Info ==

**Documentation:**
* [Content Preview Guide](https://github.com/mondb-dev/x402-wp/blob/main/CONTENT_PREVIEW_GUIDE.md)
* [Access Duration Guide](https://github.com/mondb-dev/x402-wp/blob/main/ACCESS_DURATION_GUIDE.md)
* [Token Detection Guide](https://github.com/mondb-dev/x402-wp/blob/main/TOKEN_DETECTION_GUIDE.md)

**Requirements:**
* PHP 8.1 or higher
* WordPress 6.0 or higher
* HTTPS recommended for production

**Support:**
* GitHub: https://github.com/mondb-dev/x402-wp
* X402 Protocol: https://x402.gitbook.io/x402
EOF

# Create zip archive
echo "📦 Creating zip archive..."
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG" -q
cd ..

# Create checksum
echo "🔐 Creating checksum..."
cd "$DIST_DIR"
sha256sum "${PLUGIN_SLUG}-${VERSION}.zip" > "${PLUGIN_SLUG}-${VERSION}.zip.sha256"
cd ..

# Summary
FILESIZE=$(du -h "$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip" | cut -f1)
echo ""
echo "✅ Build complete!"
echo "📦 Package: $DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
echo "📏 Size: $FILESIZE"
echo "🔐 SHA256: $DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip.sha256"
echo ""
echo "To install, upload $DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip to WordPress"
echo "or extract to wp-content/plugins/"
