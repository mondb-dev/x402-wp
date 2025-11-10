# X402 Paywall - Installation Ready Package

## Installation (No Composer Required!)

This package includes **all dependencies pre-installed**. Just upload and activate!

### ⚠️ IMPORTANT: Dependencies Included

The `x402-paywall-1.1.0.zip` package includes all required dependencies in the `vendor/` directory.

**If you're cloning from GitHub:**
1. Download `vendor-1.1.0.zip` from the releases page
2. Extract it in the plugin root directory
3. OR run `composer install --no-dev`

**If you're downloading the release ZIP:**
Everything is already included! Just install normally.

### Method 1: WordPress Admin Upload (Recommended)

1. Download `x402-paywall-1.1.0.zip` from this release
2. In WordPress admin, go to **Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file you downloaded
5. Click **Install Now**
6. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download and extract `x402-paywall-1.1.0.zip`
2. Upload the `x402-paywall` folder to `/wp-content/plugins/`
3. Go to WordPress admin → **Plugins**
4. Activate **X402 Paywall**

### Method 3: WP-CLI

```bash
wp plugin install x402-paywall-1.1.0.zip --activate
```

## Verification

**Plugin Package SHA256:**
```
3e35d6384fcc29d10b036aed5783976784cca7bc41fa8ca526d0b4d99044a43f
```

**Vendor Package SHA256:**
```
6203309194f1444e229f5b189d6b22c711ef7194f28d5a6c01487dc1616bb97f
```

Verify downloads:
```bash
sha256sum x402-paywall-1.1.0.zip
sha256sum vendor-1.1.0.zip
```

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- HTTPS (recommended for production)

## Quick Setup After Installation

### 1. Configure Your Wallet Address

1. Go to **Users → Your Profile**
2. Scroll to **X402 Payment Addresses**
3. Enter your EVM wallet address (Ethereum, Base, etc.)
4. Optionally enter your Solana wallet address
5. Click **Update Profile**

### 2. Create a Paywalled Post

1. Create or edit a post
2. Find the **X402 Paywall Settings** meta box
3. Check **Enable Paywall**
4. Configure:
   - **Preview Length**: Choose how much to show for free (0-500 words or custom)
   - **Network Type**: Select blockchain (Base, Ethereum, Solana, etc.)
   - **Payment Token**: Choose or paste token contract address
   - **Payment Amount**: Set your price
   - **Access Duration**: How long users can access after payment
5. Publish!

## Features Included

✅ Content preview with embedded videos  
✅ Configurable access duration (1 day to permanent)  
✅ Multi-chain support (EVM + Solana)  
✅ Auto-detect any ERC-20 or SPL token  
✅ Replay attack prevention  
✅ Payment logging and tracking  
✅ No Composer required - all dependencies included!

## Documentation

Full documentation is available in the plugin directory:

- `CONTENT_PREVIEW_GUIDE.md` - Configure content teasers
- `ACCESS_DURATION_GUIDE.md` - Set time-based access
- `TOKEN_DETECTION_GUIDE.md` - Auto-detect tokens
- `QUICKSTART.md` - Quick start guide

## Support

- **GitHub**: https://github.com/mondb-dev/x402-wp
- **Issues**: https://github.com/mondb-dev/x402-wp/issues
- **X402 Protocol**: https://x402.gitbook.io/x402

## What's New in 1.1.0

### Content Preview System
- 5 preview options (0/100/250/500 words, custom <!--more--> tag)
- Smart HTML trimming preserves embeds and media
- YouTube/Vimeo videos preserved in preview
- Gradient fade effect

### Configurable Access Duration
- Set how long users can access after payment
- 7 options: 1 day, 1 week, 1 month, 3 months, 6 months, 1 year, permanent
- Automatic expiry checking

### Enhanced Security
- Replay attack prevention with database-backed nonce tracking
- Daily automated cleanup of old payment logs
- X402 protocol library v1.1.0+ integration

### Backward Compatible
- Existing paywalls continue working
- Default settings maintain current behavior
- No database migration required

## Troubleshooting

### "Plugin could not be activated"

Check PHP version:
```bash
php -v
```

Must be PHP 8.1 or higher. Contact your hosting provider to upgrade.

### Dependencies Missing Error

This should NOT happen with this package. If you see this error:
1. Delete the plugin
2. Re-download the official ZIP
3. Verify the SHA256 checksum
4. Re-install

### Need Help?

Open an issue on GitHub: https://github.com/mondb-dev/x402-wp/issues
