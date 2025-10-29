=== X402 Paywall ===
Contributors: mondb-dev
Tags: paywall, cryptocurrency, payments, blockchain, x402
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Accept cryptocurrency payments for your WordPress content using the x402 payment protocol.

== Description ==

X402 Paywall is a WordPress plugin that enables you to monetize your content using cryptocurrency payments through the x402 protocol. Create paywalls on any post or page and receive payments in USDC on multiple blockchain networks.

= Features =

* **Easy Setup**: Configure paywalls with just a few clicks
* **Multi-Chain Support**: Accept payments on Ethereum, Base, Optimism, Arbitrum, Polygon, and Solana
* **Author Control**: Authors and editors can set their own payment addresses
* **Flexible Pricing**: Set custom amounts for each paywalled post
* **Secure**: Built with WordPress security best practices
* **Standards Compliant**: Implements the x402 payment protocol

= Supported Networks =

**EVM Networks:**
* Ethereum (Mainnet & Sepolia)
* Base (Mainnet & Sepolia)
* Optimism (Mainnet & Sepolia)
* Arbitrum (Mainnet & Sepolia)
* Polygon (Mainnet & Amoy)

**SVM Networks:**
* Solana (Mainnet & Devnet)

= How It Works =

1. Authors configure their cryptocurrency wallet addresses in their WordPress profile
2. When creating a post, authors can enable a paywall and set the payment amount
3. Visitors see a paywall message with payment details
4. After payment via an x402-compatible wallet, content is unlocked
5. Payments are verified through the x402 facilitator service

= About x402 Protocol =

x402 is an open standard for HTTP-based payments that enables:
* Low friction payments without credit card forms
* Support for micropayments
* Zero platform fees (only blockchain gas costs)
* Fast settlement (~2 seconds)
* Decentralized and censorship-resistant

== Installation ==

= Automatic Installation =

1. Install Composer on your server
2. Upload the plugin files to `/wp-content/plugins/x402-paywall/`
3. Run `composer install --no-dev` in the plugin directory
4. Activate the plugin through the 'Plugins' screen in WordPress
5. Configure your payment addresses in Users → Your Profile

= Manual Installation =

See the [Installation Guide](https://github.com/mondb-dev/x402-wp/blob/main/INSTALLATION.md) for detailed instructions.

== Frequently Asked Questions ==

= What cryptocurrencies can I accept? =

Currently, the plugin supports USDC on all supported networks. Additional tokens can be added by extending the configuration.

= Do I need a special wallet? =

Visitors need a wallet that supports the x402 payment protocol. Authors just need a standard wallet address for their chosen blockchain.

= Are there any fees? =

The plugin itself has no fees. You only pay blockchain gas fees when payments settle. The x402 facilitator service is free for public use.

= Is this secure? =

Yes. The plugin follows WordPress security best practices and uses the x402 protocol for cryptographic payment verification.

= Can I use this with other plugins? =

Yes, X402 Paywall is designed to work alongside other WordPress plugins.

= What happens when I deactivate the plugin? =

Paywalls are removed and all content becomes accessible. Your configuration is preserved if you reactivate.

= What happens when I uninstall the plugin? =

All plugin data (settings, payment addresses, payment logs) is permanently deleted.

== Screenshots ==

1. User profile payment address configuration
2. Post editor with paywall meta box
3. Paywall settings: network, token, and amount
4. Public-facing paywall message
5. Plugin settings page
6. Admin dashboard overview

== Changelog ==

= 1.0.0 =
* Initial release
* Support for EVM and Solana networks
* USDC payment support
* User payment profiles
* Post/page paywall configuration
* Payment verification and logging
* Admin settings and documentation

== Upgrade Notice ==

= 1.0.0 =
Initial release of X402 Paywall.

== Additional Information ==

= Requirements =
* PHP 8.1 or higher
* WordPress 6.0 or higher
* Composer for dependency management

= Links =
* [GitHub Repository](https://github.com/mondb-dev/x402-wp)
* [Documentation](https://github.com/mondb-dev/x402-wp#readme)
* [X402 Protocol](https://github.com/coinbase/x402)
* [Support](https://github.com/mondb-dev/x402-wp/issues)

== Privacy Policy ==

This plugin stores:
* User payment addresses (in database)
* Payment attempt logs (in database)
* Temporary session cookies for access control

No data is sent to third parties except:
* Payment verification requests to the configured x402 facilitator service

== Credits ==

Built on top of:
* [x402 Protocol](https://github.com/coinbase/x402) by Coinbase
* [x402-php Library](https://github.com/mondb-dev/x402-php)
