# X402 Paywall WordPress Plugin - AI Coding Instructions

## Project Overview

This is a WordPress plugin implementing the X402 payment protocol for cryptocurrency paywalls. It enables content creators to gate posts/pages behind EVM (Ethereum, Base, Polygon) and Solana blockchain payments using any ERC-20 or SPL tokens.

## Architecture & Key Components

### Core Service Boundaries
- **Payment Handler** (`includes/class-x402-paywall-payment-handler.php`) - Wraps the x402-php library for protocol operations
- **Token Detector** (`includes/class-x402-paywall-token-detector.php`) - Auto-detects token metadata from contract addresses via RPC calls
- **Database Layer** (`includes/class-x402-paywall-db.php`) - Manages user profiles and payment logs with proper WordPress DB practices
- **Security Handler** (`includes/class-x402-paywall-security.php`) - Centralized nonce, sanitization, and capability checks

### Dependency Management
The plugin requires `mondb-dev/x402-php` library installed via Composer. **Critical**: Always run `composer install` after cloning. The bootstrap system (`bootstrap.php`) handles graceful fallbacks when dependencies are missing.

### Database Schema
Two custom tables are auto-created on activation:
- `wp_x402_user_profiles` - User wallet addresses (EVM/Solana)
- `wp_x402_payment_logs` - Payment attempts with comprehensive audit trail
- `wp_x402_financial_audit` - Additional financial tracking

## Development Workflows

### Setup Commands (Essential)
```bash
# Fresh setup
cd wp-content/plugins/x402-paywall
composer install --no-dev  # Production
# OR
composer install           # Development with dev deps
./install-x402.sh          # Alternative installer
```

### Testing Workflow
```bash
# Enable WordPress debug mode in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

# Monitor X402 errors
tail -f wp-content/debug.log | grep X402

# Test with testnets first
# Base Sepolia: https://www.alchemy.com/faucets/base-sepolia
# Solana Devnet: https://faucet.solana.com/
```

## Project-Specific Patterns

### Meta Box System
Post paywall configuration uses WordPress meta boxes with custom token detection:
```php
// In admin/class-x402-paywall-meta-boxes.php
// "Custom Token" option triggers AJAX token detection
// Auto-fetches name, symbol, decimals from blockchain
```

### Singleton Pattern Usage
Core handlers use singletons initialized in specific order:
```php
// In includes/class-x402-paywall.php run() method
X402_Paywall_Security::get_instance();      // First - security
X402_Paywall_Token_Detector::get_instance(); // Then - token services
X402_Paywall_Payment_Handler::get_instance(); // Finally - payments
```

### WordPress Security Conventions
- All forms use `wp_nonce_field()` and `wp_verify_nonce()`
- Input sanitization via `sanitize_text_field()`, `sanitize_textarea_field()`
- Capability checks: `author` minimum for creating paywalls
- SQL queries use `$wpdb->prepare()` exclusively

### Content Protection Logic
Located in `public/class-x402-paywall-public.php`:
- Authors/editors bypass paywalls (see their own content)
- Cookie-based access control after payment verification
- Template override system supports theme customization in `/templates/`

## Integration Points

### X402 Protocol Integration
The plugin wraps `mondb-dev/x402-php` library through `X402_Paywall_X402_Client` singleton. Payment verification happens via facilitator service (default: `https://facilitator.x402.org`).

### Blockchain RPC Endpoints
Token detection hits public RPC endpoints:
- EVM networks: Infura, Alchemy, public nodes per network
- Solana: Official RPC endpoints, fallback chains
- Caching: 1-hour WordPress transients for token metadata

### WordPress Hook System
Custom hooks in `includes/class-x402-paywall-hooks.php`:
- `x402_paywall_before_payment_verification`
- `x402_paywall_payment_verified`
- `x402_paywall_access_granted`

## Critical Files for Understanding

- `bootstrap.php` - Dependency loading and error handling
- `includes/class-x402-paywall.php` - Main plugin orchestration
- `admin/class-x402-paywall-meta-boxes.php` - Post paywall configuration UI
- `X402_INTEGRATION.md` - X402 library integration details
- `TOKEN_DETECTION_IMPLEMENTATION.md` - Auto-detection technical details

## Common Pitfalls

1. **Missing Dependencies**: Plugin shows admin notices if x402-php not installed
2. **Address Validation**: EVM addresses must start with `0x` (42 chars), Solana are base58 (32-44 chars)
3. **User Capabilities**: Only `author` role and above can create paywalls
4. **PHP Version**: Requires PHP 8.1+ (hard requirement)
5. **Template Overrides**: Place custom templates in theme's `/x402-paywall/` directory

## Quick Debugging

- Check `wp_x402_payment_logs` table for payment status details
- Verify user addresses in `wp_x402_user_profiles` table
- Test token detection via admin AJAX: `wp_ajax_x402_detect_token`
- Payment verification errors logged to WordPress debug.log