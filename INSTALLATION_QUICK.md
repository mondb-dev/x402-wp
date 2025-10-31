# Quick Installation Guide - X402 Paywall Plugin

## Prerequisites

Before installing, ensure you have:

- ✅ WordPress 6.0 or higher
- ✅ PHP 8.1 or higher  
- ✅ Composer (PHP package manager)

## Step 1: Install Composer

If you don't have Composer installed:

### macOS
```bash
brew install composer
```

### Linux
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Windows
Download from: https://getcomposer.org/download/

Verify installation:
```bash
composer --version
```

## Step 2: Install Plugin

### Option A: Clone from GitHub (Recommended for Development)

```bash
# Navigate to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone the repository
git clone https://github.com/mondb-dev/x402-wp.git x402-paywall

# Navigate to plugin directory
cd x402-paywall

# Install X402 PHP library and dependencies
composer install
```

### Option B: Download ZIP

1. Download plugin ZIP from GitHub
2. Extract to `wp-content/plugins/x402-paywall/`
3. Open terminal and run:
```bash
cd wp-content/plugins/x402-paywall/
composer install
```

### Option C: Use Installation Script

```bash
cd wp-content/plugins/x402-paywall/
chmod +x install-x402.sh
./install-x402.sh
```

## Step 3: Activate Plugin

1. Log into WordPress admin
2. Go to **Plugins → Installed Plugins**
3. Find "X402 Paywall"
4. Click **Activate**

## Step 4: Configure Payment Addresses

1. Go to **Users → Your Profile**
2. Scroll to **X402 Paywall Payment Addresses**
3. Enter your wallet addresses:
   - **EVM Address**: For Ethereum, Base, Polygon, etc. (starts with `0x`)
   - **Solana Address**: For Solana payments (base58 format)
4. Click **Update Profile**

## Step 5: Create Your First Paywall

1. Create or edit a **Post** or **Page**
2. Find the **X402 Paywall Settings** meta box in the sidebar
3. Check ☑️ **Enable Paywall**
4. Configure:
   - **Network Type**: EVM or Solana
   - **Network**: Specific blockchain (e.g., Base Mainnet)
   - **Token**: Select USDC or use Custom Token
   - **Amount**: Set payment amount (e.g., 1.50)
5. Click **Publish** or **Update**

## Step 6: Test Payment (Optional - Use Testnet)

For testing, use testnets:
- **Base Sepolia** (EVM testnet)
- **Solana Devnet** (Solana testnet)

Get testnet tokens:
- Base Sepolia: https://www.alchemy.com/faucets/base-sepolia
- Solana Devnet: https://faucet.solana.com/

## Verification Checklist

After installation, verify:

- [ ] Plugin activated without errors
- [ ] Admin notice shows "X402 library installed" (not "library not found")
- [ ] Payment addresses configured in profile
- [ ] Test post created with paywall enabled
- [ ] Paywall displays on frontend
- [ ] Payment flow works (use testnet first)

## Common Issues

### ❌ "Composer: command not found"

**Solution**: Install Composer (see Step 1)

### ❌ "X402 PHP library not found"

**Solution**: Run `composer install` in plugin directory

### ❌ "PHP Fatal error: require_once() failed"

**Solution**: Ensure all files uploaded correctly, re-extract ZIP

### ❌ "Class 'X402\Client' not found"

**Solutions**:
1. Run `composer install`
2. Check `vendor/` directory exists
3. Verify PHP version >= 8.1
4. Check `vendor/autoload.php` exists

### ❌ Plugin doesn't appear in WordPress

**Solutions**:
1. Check plugin uploaded to `wp-content/plugins/x402-paywall/`
2. Verify main plugin file exists: `x402-paywall.php`
3. Check folder permissions (755 for directories, 644 for files)

## File Structure Verification

After installation, verify this structure exists:

```
x402-paywall/
├── x402-paywall.php          ✓ Main plugin file
├── composer.json             ✓ Composer configuration
├── composer.lock             ✓ Dependency lock file
├── vendor/                   ✓ Composer dependencies
│   ├── autoload.php          ✓ Autoloader
│   └── mondb-dev/            ✓ X402 PHP library
├── includes/                 ✓ Core classes
├── admin/                    ✓ Admin functionality
├── public/                   ✓ Public-facing code
├── assets/                   ✓ CSS/JS files
└── templates/                ✓ Template files
```

## Next Steps

1. **Read Documentation**:
   - [README.md](README.md) - Overview and features
   - [QUICKSTART.md](QUICKSTART.md) - Quick start guide
   - [X402_INTEGRATION.md](X402_INTEGRATION.md) - X402 library integration
   - [TOKEN_DETECTION_GUIDE.md](TOKEN_DETECTION_GUIDE.md) - Custom tokens

2. **Configure Settings**:
   - Go to **Settings → X402 Paywall**
   - Review default options
   - Configure RPC endpoints (optional)

3. **Test on Testnet**:
   - Create test post with low amount
   - Use testnet tokens
   - Verify payment flow

4. **Go Live**:
   - Switch to mainnet
   - Set real payment amounts
   - Update wallet addresses
   - Test with small amount first

## Support

Need help?

- **Installation Issues**: Check [INSTALLATION.md](INSTALLATION.md)
- **Usage Questions**: See [QUICKSTART.md](QUICKSTART.md)
- **Technical Details**: Review [X402_INTEGRATION.md](X402_INTEGRATION.md)
- **Bug Reports**: https://github.com/mondb-dev/x402-wp/issues
- **X402 Library**: https://github.com/mondb-dev/x402-php

## Security Notes

- 🔒 Never share your private keys
- 🔒 Always test on testnet first
- 🔒 Use strong WordPress passwords
- 🔒 Keep plugin updated
- 🔒 Backup wallet addresses

## Updating

To update the plugin:

```bash
cd wp-content/plugins/x402-paywall/

# Update from git
git pull origin main

# Update dependencies
composer update

# Clear WordPress cache
wp cache flush  # (if using WP-CLI)
```

## Uninstallation

To uninstall:

1. Deactivate plugin in WordPress admin
2. Click **Delete** (this runs cleanup)
3. Or manually remove:
```bash
rm -rf wp-content/plugins/x402-paywall/
```

**Note**: Deactivation/deletion removes:
- Database tables (`wp_x402_*`)
- Plugin options
- User meta data
- Post meta data

Backup first if you need to preserve data!

---

**Congratulations! 🎉**

Your X402 Paywall plugin is now installed and ready to monetize your WordPress content with cryptocurrency payments!
