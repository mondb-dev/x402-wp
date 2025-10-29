# Installation Instructions

## Prerequisites

- WordPress 6.0 or higher
- PHP 8.1 or higher
- Composer (for dependency management)

## Step-by-Step Installation

### 1. Clone or Download the Plugin

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mondb-dev/x402-wp.git x402-paywall
cd x402-paywall
```

### 2. Install PHP Dependencies

The plugin requires the x402-php library and Guzzle HTTP client. Install them using Composer:

```bash
composer install --no-dev
```

**Important**: If you encounter GitHub API rate limits when installing dependencies, you have two options:

#### Option A: Create a GitHub Token (Recommended)

1. Go to https://github.com/settings/tokens/new?scopes=&description=Composer
2. Generate a token (no special scopes needed for public repos)
3. Configure Composer to use it:
   ```bash
   composer config -g github-oauth.github.com YOUR_TOKEN_HERE
   ```
4. Run `composer install --no-dev` again

#### Option B: Manual Installation

1. Install Guzzle and its dependencies:
   ```bash
   composer require guzzlehttp/guzzle:^7.8
   ```

2. Clone and set up the x402-php library:
   ```bash
   # Create vendor directory structure
   mkdir -p vendor/x402-php
   
   # Clone x402-php
   git clone https://github.com/mondb-dev/x402-php.git /tmp/x402-php-temp
   
   # Copy source files
   cp -r /tmp/x402-php-temp/src vendor/x402-php/
   
   # Clean up
   rm -rf /tmp/x402-php-temp
   ```

3. Create a bootstrap autoloader (bootstrap.php should handle this):
   The plugin's bootstrap.php file will automatically load the x402-php classes from vendor/x402-php/src/

### 3. Activate the Plugin

1. Log in to your WordPress admin panel
2. Navigate to **Plugins → Installed Plugins**
3. Find "X402 Paywall" and click **Activate**

### 4. Configure the Plugin

1. Go to **Settings → X402 Paywall** to configure global settings
2. Go to **Users → Your Profile** to set up your payment addresses
3. Start creating paywalls on your posts and pages!

## Troubleshooting

### "composer: command not found"

Install Composer by following the instructions at https://getcomposer.org/doc/00-intro.md

### "PHP version too low"

This plugin requires PHP 8.1 or higher. Contact your hosting provider to upgrade PHP.

### "Class 'X402\...' not found"

This means the dependencies weren't installed correctly. Make sure you ran `composer install` in the plugin directory.

### GitHub API Rate Limits

If you see errors about GitHub API rate limits:
1. Create a GitHub personal access token (see Option A above)
2. Or try again later (GitHub resets rate limits hourly)

## Production Deployment

For production sites:

1. **Always use HTTPS** for your WordPress site
2. **Configure a secure facilitator** in Settings → X402 Paywall
3. **Test on staging** before deploying to production
4. **Backup your database** before installation

## Updates

To update the plugin:

```bash
cd /path/to/wordpress/wp-content/plugins/x402-paywall
git pull origin main
composer update --no-dev
```

Then reactivate the plugin in WordPress admin if necessary.

## Support

For help with installation:
- [GitHub Issues](https://github.com/mondb-dev/x402-wp/issues)
- [WordPress Support Forums](https://wordpress.org/support/)
- [X402 Documentation](https://x402.gitbook.io/x402)
