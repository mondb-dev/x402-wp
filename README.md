# X402 Paywall WordPress Plugin

A WordPress plugin that implements the [x402 payment protocol](https://github.com/coinbase/x402) for creating cryptocurrency paywalls on posts and pages.

## Features

- 🔒 **Easy Paywall Setup**: Add paywalls to any post or page with a few clicks
- 💰 **Multi-Chain Support**: Accept payments on EVM (Ethereum, Base, Optimism, Arbitrum, Polygon) and Solana networks
- 🪙 **Auto-Token Detection**: Simply paste any ERC-20 or SPL token contract address - the plugin automatically fetches token name, symbol, and decimals from the blockchain
- 👤 **User Payment Profiles**: Authors and editors can configure their payment addresses
- ⚙️ **Flexible Configuration**: Customize payment amounts and choose from supported tokens
- 🔐 **Secure**: Implements WordPress security best practices with nonces, sanitization, and validation
- 📊 **Payment Logging**: Track all payment attempts in the database
- 🎨 **User-Friendly Interface**: Clean, intuitive admin interface and public paywall display

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- Composer (for dependency management)

## Installation

1. **Clone or download this repository** to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/mondb-dev/x402-wp.git x402-paywall
   ```

2. **Install X402 PHP library and dependencies** using Composer:
   ```bash
   cd x402-paywall
   composer install
   ```
   
   Or use the provided installation script:
   ```bash
   chmod +x install-x402.sh
   ./install-x402.sh
   ```

3. **Activate the plugin** through the WordPress admin panel

**Note**: The plugin requires the official [mondb-dev/x402-php](https://github.com/mondb-dev/x402-php) library for X402 protocol operations. See [X402_INTEGRATION.md](X402_INTEGRATION.md) for detailed integration documentation.

## Quick Start

### 1. Configure Payment Addresses

1. Go to **Users → Your Profile** in WordPress admin
2. Scroll down to the **X402 Paywall Payment Addresses** section
3. Enter your wallet addresses:
   - **EVM Address**: For Ethereum, Base, Optimism, Arbitrum, Polygon (starts with `0x`)
   - **Solana Address**: For Solana network payments

### 2. Set Up a Paywall

1. Create or edit a post/page
2. Find the **X402 Paywall Settings** meta box in the sidebar
3. Check **Enable Paywall**
4. Configure:
   - **Network Type**: Choose EVM or Solana
   - **Network**: Select specific network (e.g., Base Mainnet, Solana Mainnet)
   - **Token**: Choose payment token (e.g., USDC) or select "Custom Token"
   - **Amount**: Set payment amount in the selected token
5. Publish or update the post

#### Using Custom Tokens (NEW!)

You can now accept payments in **any ERC-20 or SPL token** by simply pasting the contract address:

1. In the Token dropdown, select **"Custom Token (Enter Contract Address)"**
2. Paste the token's contract address (ERC-20) or mint address (SPL)
3. Click **"Auto-Detect Token Info"**
4. The plugin will automatically fetch:
   - Token Name
   - Token Symbol
   - Token Decimals
5. Set your payment amount and publish

**Example Token Addresses:**
- USDC on Base: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
- USDC on Solana: `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`

See [TOKEN_DETECTION_GUIDE.md](TOKEN_DETECTION_GUIDE.md) for detailed documentation.

### 3. Plugin Settings

Configure global settings at **Settings → X402 Paywall**:
- **Facilitator URL**: x402 facilitator service URL (default: https://facilitator.x402.org)
- **Auto-settle Payments**: Automatically settle verified payments
- **Valid Before Buffer**: Time buffer for payment validity (seconds)
- **Network Toggles**: Enable/disable EVM and Solana networks

## Supported Networks & Tokens

### EVM Networks
- **Base Mainnet** & Sepolia (Testnet) - USDC + Any ERC-20 token
- **Ethereum Mainnet** & Sepolia (Testnet) - USDC + Any ERC-20 token
- **Polygon**, **Arbitrum**, **Optimism** - Any ERC-20 token

### Solana Networks
- **Solana Mainnet** & Devnet (Testnet) - USDC + Any SPL token

**Custom Token Support:** The plugin now supports **any ERC-20 or SPL token** through automatic token detection. Simply paste the contract address and the plugin will fetch token metadata from the blockchain.

## How It Works

1. **Content Protection**: When a paywall is enabled, the plugin replaces post content with a payment message
2. **Payment Flow**: Users with x402-compatible wallets can pay to access the content
3. **Verification**: Payments are verified through the x402 facilitator service
4. **Access Grant**: Upon successful payment verification, content is unlocked for the user
5. **Logging**: All payment attempts are logged in the database for tracking

## User Capabilities

- **Authors & Editors**: Can create paywalls on their own posts
- **Administrators**: Full access to all plugin features and settings
- **Other Users**: Can view public content and pay to access paywalled content

## Security

The plugin implements multiple security measures:

- ✅ **Input Validation**: All user inputs are validated before processing
- ✅ **Sanitization**: Data is sanitized to prevent XSS attacks
- ✅ **Nonces**: WordPress nonces protect against CSRF attacks
- ✅ **Capability Checks**: User permissions are verified for all actions
- ✅ **Secure Payment Processing**: Utilizes the x402-php library for cryptographic verification
- ✅ **Database Security**: Prepared statements prevent SQL injection

## Development

### File Structure

```
x402-paywall/
├── admin/                          # Admin interface classes
│   ├── class-x402-paywall-admin.php
│   ├── class-x402-paywall-profile.php
│   ├── class-x402-paywall-meta-boxes.php
│   └── class-x402-paywall-settings.php
├── assets/                         # CSS and JavaScript files
│   ├── css/
│   │   ├── admin.css
│   │   └── public.css
│   └── js/
│       ├── admin.js
│       └── public.js
├── includes/                       # Core plugin classes
│   ├── class-x402-paywall.php
│   ├── class-x402-paywall-activator.php
│   ├── class-x402-paywall-deactivator.php
│   ├── class-x402-paywall-db.php
│   └── class-x402-paywall-payment-handler.php
├── public/                         # Public-facing classes
│   └── class-x402-paywall-public.php
├── languages/                      # Translation files
├── composer.json                   # Composer dependencies
├── x402-paywall.php               # Main plugin file
└── uninstall.php                  # Uninstall script
```

### Database Tables

The plugin creates two custom tables:

1. **wp_x402_user_profiles**: Stores user payment addresses
2. **wp_x402_payment_logs**: Logs all payment attempts and statuses

### Extending the Plugin

You can extend the plugin by:

- Adding more networks/tokens in `class-x402-paywall-meta-boxes.php`
- Customizing the paywall display in `class-x402-paywall-public.php`
- Adding custom payment validations in `class-x402-paywall-payment-handler.php`

## Contributing

Contributions are welcome! Please follow WordPress coding standards and test thoroughly before submitting pull requests.

## Support

For issues and questions:
- [GitHub Issues](https://github.com/mondb-dev/x402-wp/issues)
- [X402 Protocol Documentation](https://x402.gitbook.io/x402)
- [X402 PHP Library](https://github.com/mondb-dev/x402-php)

## License

Apache-2.0 License - see LICENSE file for details

## Credits

Built on top of:
- [x402 Protocol](https://github.com/coinbase/x402) by Coinbase
- [x402-php Library](https://github.com/mondb-dev/x402-php)

---

Made with ❤️ for decentralized content monetization