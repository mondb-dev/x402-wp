# X402 Library Integration Guide

## Overview

This plugin now integrates with the official **mondb-dev/x402-php** library for X402 protocol operations. This provides standardized payment request creation, verification, and blockchain interaction.

## Installation

### Prerequisites

- PHP 8.1 or higher
- Composer (PHP dependency manager)

### Install Composer (if not already installed)

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

### Install X402 Library

Navigate to the plugin directory and run:

```bash
cd wp-content/plugins/x402-wp
composer install
```

Or use the provided installation script:

```bash
chmod +x install-x402.sh
./install-x402.sh
```

## Architecture

### Integration Flow

```
WordPress Plugin
    ↓
X402_Paywall_X402_Client (Wrapper)
    ↓
mondb-dev/x402-php (Official Library)
    ↓
Blockchain RPC Nodes (Ethereum, Solana, etc.)
```

### Key Components

#### 1. **X402_Paywall_X402_Client** (Wrapper Class)
- Location: `includes/class-x402-paywall-x402-client.php`
- Purpose: WordPress-friendly wrapper around x402-php library
- Features:
  - Automatic library detection
  - Graceful fallback if library not installed
  - Method name compatibility layer
  - WordPress hooks and filters integration
  - Admin notices for configuration

#### 2. **X402_Paywall_Protocol** (Protocol Handler)
- Location: `includes/class-x402-paywall-protocol.php`
- Purpose: Use X402 library for protocol operations
- Features:
  - Payment request creation
  - Payment verification
  - Signature verification
  - Transaction lookups

#### 3. **Composer Configuration**
- Location: `composer.json`
- Dependency: `mondb-dev/x402-php: ^1.0`
- Repository: https://github.com/mondb-dev/x402-php

## Usage Examples

### Creating Payment Requests

```php
$x402_client = X402_Paywall_X402_Client::get_instance();

$payment_request = $x402_client->create_payment_request([
    'amount' => 10.50,
    'currency' => 'USDC',
    'recipient' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'network' => 'ethereum',
    'post_id' => 123,
    'callback_url' => rest_url('x402-paywall/v1/payment-callback')
]);

if (is_wp_error($payment_request)) {
    // Handle error
    error_log($payment_request->get_error_message());
} else {
    // Use payment request data
    $payment_id = $payment_request['payment_id'];
    $qr_code = $payment_request['qr_code'];
    $deeplink = $payment_request['deeplink'];
}
```

### Verifying Payments

```php
$x402_client = X402_Paywall_X402_Client::get_instance();

$verification = $x402_client->verify_payment([
    'transaction_hash' => '0xabc123...',
    'network' => 'ethereum',
    'expected_amount' => 10.50,
    'expected_recipient' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'token' => 'USDC',
]);

if (!is_wp_error($verification)) {
    if ($verification['verified'] && $verification['confirmations'] >= 12) {
        // Payment confirmed, grant access
        $this->grant_access($user_id, $post_id);
    }
}
```

### Verifying Signatures

```php
$x402_client = X402_Paywall_X402_Client::get_instance();

$is_valid = $x402_client->verify_signature(
    $message,
    $signature,
    $public_key
);

if ($is_valid === true) {
    // Signature is valid
}
```

### Getting Transaction Details

```php
$x402_client = X402_Paywall_X402_Client::get_instance();

$transaction = $x402_client->get_transaction(
    '0xabc123...',
    'ethereum'
);

if (!is_wp_error($transaction)) {
    echo 'From: ' . $transaction['from'];
    echo 'To: ' . $transaction['to'];
    echo 'Amount: ' . $transaction['amount'];
    echo 'Confirmations: ' . $transaction['confirmations'];
}
```

## Features Provided by X402 Library

### 1. **Multi-Chain Support**
- Ethereum and EVM chains
- Solana
- Bitcoin (Lightning Network)
- Additional chains as supported by library

### 2. **Payment Operations**
- Payment request creation with QR codes
- Deep link generation for wallets
- Payment verification via blockchain
- Transaction status checking
- Confirmation tracking

### 3. **Security Features**
- Signature verification (EIP-191, EIP-712)
- Message signing
- Address validation
- Amount verification

### 4. **Token Support**
- Native currencies (ETH, SOL, BTC)
- ERC-20 tokens
- SPL tokens
- Custom token detection

## WordPress Integration

### Hooks and Filters

#### Filter: Customize X402 Client Configuration
```php
add_filter('x402_client_config', function($config) {
    $config['timeout'] = 60;
    $config['api_endpoint'] = 'https://custom-x402-api.com';
    $config['verify_ssl'] = true;
    return $config;
});
```

#### Action: After Client Initialization
```php
add_action('x402_client_initialized', function($client) {
    // Client is ready, do custom initialization
    error_log('X402 client initialized successfully');
});
```

#### Filter: Customize Payment Request
```php
add_filter('x402_payment_request_created', function($payment_request, $data) {
    // Modify payment request before returning
    $payment_request['custom_field'] = 'custom_value';
    return $payment_request;
}, 10, 2);
```

#### Filter: Customize Payment Verification
```php
add_filter('x402_payment_verified', function($result, $data) {
    // Add custom verification logic
    if ($result['verified']) {
        // Log successful verification
        error_log('Payment verified: ' . $data['transaction_hash']);
    }
    return $result;
}, 10, 2);
```

### Admin Notices

The plugin automatically displays admin notices if:

1. **X402 Library Not Installed**
   - Shows installation instructions
   - Provides command to run
   - Links to GitHub repository

2. **Client Initialization Failed**
   - Shows error message
   - Logs detailed error to WordPress debug log

3. **Method Not Found**
   - Indicates X402 library version mismatch
   - Suggests running `composer update`

## Fallback Behavior

### When Library Not Available

If the X402 library is not installed:

1. ✅ Plugin continues to function
2. ⚠️ Admin notice displayed to administrators
3. 🔄 Falls back to basic WordPress implementation
4. 📝 Logs warning in WordPress debug log

### Checking Library Availability

```php
$x402_client = X402_Paywall_X402_Client::get_instance();

if ($x402_client->is_available()) {
    // Use X402 library features
    $payment = $x402_client->create_payment_request($data);
} else {
    // Use fallback implementation
    $payment = $this->create_payment_request_fallback($data);
}
```

## Configuration Options

### WordPress Settings

Configure X402 in **WordPress Admin → Settings → X402 Paywall**:

- **Default Network**: mainnet, testnet, devnet
- **API Endpoint**: Custom X402 API endpoint (optional)
- **Required Confirmations**: Per-network confirmation requirements
- **Timeout**: RPC request timeout (seconds)

### Environment Variables

Set via `wp-config.php`:

```php
// X402 Configuration
define('X402_DEFAULT_NETWORK', 'mainnet');
define('X402_API_ENDPOINT', 'https://api.x402.org');
define('X402_TIMEOUT', 30);
define('X402_VERIFY_SSL', true);
```

## Troubleshooting

### Library Not Found

**Problem:** Admin notice "X402 PHP library not found"

**Solution:**
```bash
cd wp-content/plugins/x402-wp
composer install
```

### Composer Not Installed

**Problem:** `composer: command not found`

**Solution:** Install Composer:
- macOS: `brew install composer`
- Linux: https://getcomposer.org/download/
- Windows: Download from getcomposer.org

### Version Conflicts

**Problem:** "X402 method not found" errors

**Solution:** Update library:
```bash
cd wp-content/plugins/x402-wp
composer update mondb-dev/x402-php
```

### Class Not Found

**Problem:** `Class 'X402\Client' not found`

**Solution:**
1. Verify `vendor/autoload.php` exists
2. Check `composer.json` has x402-php dependency
3. Run `composer install` again
4. Check PHP version >= 8.1

### Payment Verification Fails

**Problem:** Payments not verifying correctly

**Solutions:**
1. Check network configuration matches blockchain
2. Verify RPC endpoints are accessible
3. Ensure sufficient confirmations
4. Check transaction hash format
5. Verify token addresses match

### Slow Performance

**Problem:** Payment operations are slow

**Solutions:**
1. Use caching for repeated lookups
2. Configure faster RPC endpoints
3. Adjust timeout settings
4. Enable WordPress object cache
5. Consider using premium RPC providers

## Performance Optimization

### Caching Strategies

```php
// Cache payment verification results
$cache_key = 'x402_verification_' . md5($tx_hash);
$verification = get_transient($cache_key);

if ($verification === false) {
    $verification = $x402_client->verify_payment($data);
    set_transient($cache_key, $verification, HOUR_IN_SECONDS);
}
```

### RPC Endpoint Configuration

Use premium RPC endpoints for better performance:

```php
add_filter('x402_client_config', function($config) {
    $config['rpc_endpoints'] = [
        'ethereum' => 'https://eth-mainnet.g.alchemy.com/v2/YOUR_KEY',
        'polygon' => 'https://polygon-mainnet.g.alchemy.com/v2/YOUR_KEY',
        'solana-mainnet' => 'https://solana-mainnet.g.alchemy.com/v2/YOUR_KEY',
    ];
    return $config;
});
```

## Security Best Practices

### 1. **Validate All Inputs**
```php
$tx_hash = sanitize_text_field($_POST['transaction_hash']);
$network = sanitize_text_field($_POST['network']);
$amount = floatval($_POST['amount']);
```

### 2. **Verify Signatures**
```php
$signature_valid = $x402_client->verify_signature(
    $message,
    $signature,
    $public_key
);

if (!$signature_valid) {
    wp_die('Invalid signature');
}
```

### 3. **Check Confirmations**
```php
$required_confirmations = [
    'ethereum' => 12,
    'polygon' => 128,
    'solana-mainnet' => 32,
];

$min_confirmations = $required_confirmations[$network] ?? 6;

if ($verification['confirmations'] < $min_confirmations) {
    return new WP_Error('insufficient_confirmations', 'Wait for more confirmations');
}
```

### 4. **Rate Limiting**
```php
// Limit verification requests per user
$rate_limit_key = 'x402_verify_' . $user_id;
$attempts = get_transient($rate_limit_key) ?: 0;

if ($attempts >= 10) {
    return new WP_Error('rate_limit', 'Too many verification attempts');
}

set_transient($rate_limit_key, $attempts + 1, MINUTE_IN_SECONDS * 5);
```

## Development Tips

### Debugging

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check debug log for X402 errors:
```bash
tail -f wp-content/debug.log | grep X402
```

### Testing

Test with testnets first:

```php
// Use Sepolia testnet for Ethereum
$config['network'] = 'sepolia';

// Use devnet for Solana
$config['network'] = 'solana-devnet';
```

### Custom Implementation

Extend the X402 client wrapper:

```php
class My_Custom_X402_Client extends X402_Paywall_X402_Client {
    
    public function custom_payment_method($data) {
        $client = $this->get_client();
        
        // Your custom logic using X402 client
        return $client->customMethod($data);
    }
}
```

## Resources

### Documentation
- **X402 PHP Library**: https://github.com/mondb-dev/x402-php
- **X402 Protocol**: https://x402.org/docs
- **Plugin Documentation**: See `/docs` directory

### Support
- **Library Issues**: https://github.com/mondb-dev/x402-php/issues
- **Plugin Issues**: https://github.com/mondb-dev/x402-wp/issues

### Community
- **X402 Discord**: https://discord.gg/x402
- **WordPress Forums**: https://wordpress.org/support/plugin/x402-paywall

## Changelog

### Version 1.0.0
- ✅ Integrated mondb-dev/x402-php library
- ✅ Created WordPress wrapper class
- ✅ Payment request creation via library
- ✅ Payment verification via library
- ✅ Signature verification via library
- ✅ Transaction lookup via library
- ✅ Graceful fallback if library not installed
- ✅ Admin notices for configuration
- ✅ Comprehensive hooks and filters
- ✅ Installation script
- ✅ Complete documentation

## Next Steps

1. **Install X402 Library**: Run `composer install`
2. **Configure Settings**: WordPress Admin → Settings → X402 Paywall
3. **Set Wallet Addresses**: Users → Your Profile
4. **Create Paywalled Content**: Posts → Add New
5. **Test Payments**: Use testnet first
6. **Go Live**: Switch to mainnet

---

**Note**: The X402 PHP library is actively maintained. Check for updates regularly:

```bash
cd wp-content/plugins/x402-wp
composer update mondb-dev/x402-php
```
