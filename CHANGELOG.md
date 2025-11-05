# Changelog

All notable changes to the X402 Paywall WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-XX

### Added
- **Content Preview System**: Authors can now show a teaser before the paywall with 5 options (no preview, 100/250/500 words, or custom <!--more--> tag)
- **Smart HTML Trimming**: Preview extraction preserves embeds, iframes, videos, images, and HTML structure
- **Media Preservation**: YouTube/Vimeo embeds and other rich media are kept intact in previews
- **Preview Fade Effect**: CSS gradient overlay creates smooth transition from preview to paywall
- **Configurable Access Duration**: Authors can now set how long users can access content after payment (1 day to permanent)
- **Persistent Replay Attack Prevention**: Implemented `NonceTrackerInterface` from x402-php library for database-backed nonce tracking
- **WordPress Nonce Tracker**: New `X402_Paywall_Replay_Prevention` class with transient caching for performance
- **Cron Job Management**: New `X402_Paywall_Cron` class for automated daily cleanup of old payment logs
- **Library Version Check**: Bootstrap now verifies x402-php library has `NonceTrackerInterface` support
- **Access Expiry Column**: Added `expires_at` datetime column to `wp_x402_payment_logs` table
- **Expiry Calculation Helper**: New `X402_Paywall_DB::calculate_access_expiry()` method
- Test script `test-nonce-tracker.php` for verifying integration
- Migration script `migrate-add-expires-at.php` for upgrading existing installations
- Comprehensive documentation in `ACCESS_DURATION_GUIDE.md`

### Changed
- **Content Filtering**: `filter_content()` now calls `render_content_with_paywall()` to show preview before paywall
- **X402 Client Integration**: Updated to pass `NonceTrackerInterface` to PaymentHandler constructor
- **Initialization Order**: Replay prevention now loads before X402 client initialization
- **Composer Dependencies**: Updated x402-php to dev-main (includes NonceTrackerInterface)
- **Payment Verification**: `has_user_paid()` now checks expiry date: `(expires_at IS NULL OR expires_at > NOW())`
- **Meta Box UI**: Added "Access Duration" and "Preview Length" dropdowns in post editor (defaults: 1 year, no preview)
- **Payment Logging**: `log_payment()` now accepts and stores `expires_at` field
- Plugin activation now fires `x402_paywall_activated` action and runs initial cleanup
- Plugin deactivation now fires `x402_paywall_deactivated` action
- **CSS Enhancements**: Updated `.x402-paywall-preview` styles to support responsive embeds and gradient fade

### Security
- Transaction hashes now tracked in database to prevent replay attacks across server restarts
- Atomic check-and-set operations prevent race conditions in payment verification
- Automatic cleanup removes failed attempts older than 7 days and pending payments older than 24 hours
- Nonce format: `network:txHash` prevents cross-chain replay attacks
- Access expiry enforced at database level for secure time-based access control

### Technical
- Implemented 5 NonceTrackerInterface methods: `hasNonce()`, `isNonceUsed()`, `markUsed()`, `markNonceUsed()`, `remove()`
- Added 5-minute transient caching for nonce lookups (reduces database load)
- Maintained backward compatibility with legacy `isProcessed()` and `markProcessed()` methods
- Cron job runs daily at midnight to clean up old entries
- Added statistics tracking via `get_statistics()` method
- Database index on `expires_at` for optimal query performance
- Stored as `_x402_paywall_access_duration` post meta with validation

### Backward Compatibility
- **Default behavior maintained**: 1-year access duration (same as before)
- **Existing payments preserved**: All existing payments have `expires_at = NULL` (permanent access)
- **No data loss**: Migration handled automatically via activator's `dbDelta()`
- **Cookie behavior unchanged**: Client-side cookies still expire after 1 year

### Access Duration Options
- 1 Day (`1_day`)
- 1 Week (`1_week`)
- 1 Month (`1_month`)
- 3 Months (`3_months`)
- 6 Months (`6_months`)
- **1 Year (`1_year`)** - Default, maintains current behavior
- Permanent (`permanent`) - Lifetime access, never expires

### Documentation
- Created `X402_LIBRARY_INTEGRATION_V1.1.md` with comprehensive implementation details
- Created `ACCESS_DURATION_GUIDE.md` with usage examples and migration guide
- Updated integration test suite with NonceTracker verification

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
