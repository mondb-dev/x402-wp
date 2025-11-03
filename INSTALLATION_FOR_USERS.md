# X402 Paywall Plugin - Installation Instructions

## Required: Install Dependencies

This plugin requires the `mondb-dev/x402-php` library to function. After installing the plugin, you **must** run the following:

### Option 1: Automatic Installation (Recommended)

```bash
cd wp-content/plugins/x402-paywall
chmod +x install-x402.sh
./install-x402.sh
```

### Option 2: Manual Installation

```bash
cd wp-content/plugins/x402-paywall
composer install --no-dev
```

### Requirements

- PHP 8.1 or higher
- Composer (PHP dependency manager)

### Installing Composer

**macOS:**
```bash
brew install composer
```

**Linux:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Windows:**
Download from https://getcomposer.org/download/

## Verification

After installation, check that the vendor directory exists:

```bash
ls -la wp-content/plugins/x402-paywall/vendor/
```

You should see:
- `autoload.php`
- `mondb-dev/` directory
- `guzzlehttp/` directory

## Troubleshooting

If the plugin shows an error notice after activation, it means dependencies are not installed. Follow the steps above to install them.

## Support

- GitHub: https://github.com/mondb-dev/x402-wp
- Issues: https://github.com/mondb-dev/x402-wp/issues
