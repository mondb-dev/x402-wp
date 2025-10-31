# Changelog

All notable changes to the X402 Paywall WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-29

### Added
- Initial release of X402 Paywall plugin
- Support for EVM networks (Ethereum, Base, Optimism, Arbitrum, Polygon)
- Support for Solana (SVM) network
- **Token Auto-Detection**: Automatically fetch token metadata from any ERC-20 or SPL token contract address
- **Custom Token Support**: Accept payments in any ERC-20 or SPL token, not just pre-configured tokens
- User profile payment address configuration
- Post/page paywall meta boxes with custom token UI
- Custom paywall amounts and token selection
- Global plugin settings page
- Payment verification via x402 facilitator
- Payment logging in database
- Session-based access control via cookies
- Admin UI with WordPress standards
- Public-facing paywall display
- Security features (nonces, sanitization, validation)
- Database tables for user profiles and payment logs
- Comprehensive handler classes for security, hooks, templates, protocol, finance, and REST API
- Template loader system with theme override support
- 40+ extensibility hooks for customization
- Financial audit trail with high-precision calculations (18 decimals)
- REST API endpoints for payment operations
- SPL token handler for Solana-specific operations
- Token detector with blockchain RPC integration
- Uninstall cleanup script
- Extensive documentation (Installation, Quick Start, Hooks Reference, Audit Implementation, Theme Developer Guide, Token Detection Guide)
- Contributing guidelines
- Apache 2.0 license

### Supported Networks (Mainnet)
- Base Mainnet - USDC + Any ERC-20 token
- Ethereum Mainnet - USDC + Any ERC-20 token
- Polygon Mainnet - Any ERC-20 token
- Arbitrum One - Any ERC-20 token
- Optimism Mainnet - Any ERC-20 token
- Solana Mainnet - USDC + Any SPL token

### Supported Networks (Testnet)
- Base Sepolia - USDC + Any ERC-20 token
- Ethereum Sepolia - USDC + Any ERC-20 token
- Solana Devnet - USDC + Any SPL token

### Security
- All user inputs validated and sanitized
- WordPress nonces for CSRF protection
- Capability checks for all actions
- Prepared SQL statements
- XSS prevention
- Address format validation

### Known Limitations
- Requires PHP 8.1 or higher
- Requires Composer for dependency installation
- Payment verification requires external facilitator service
- Currently supports USDC token only (easily extensible)

## [Unreleased]

### Planned Features
- Support for more ERC-20 tokens
- Support for more SPL tokens
- Built-in facilitator health check
- Payment analytics dashboard
- Bulk paywall management
- Custom paywall templates
- Time-limited access options
- Subscription-based paywalls
- Integration with WooCommerce
- REST API endpoints
- WebSocket support for real-time notifications
- Multi-language support

### Under Consideration
- Support for NFT-gated content
- Integration with popular membership plugins
- Automated price conversion (USD to token amount)
- Payment refund functionality
- Author earnings dashboard

---

For details on any version, see the [releases page](https://github.com/mondb-dev/x402-wp/releases).
