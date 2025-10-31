# Token Auto-Detection Guide

## Overview

The X402 Paywall plugin now supports **automatic token detection** from contract addresses. Authors and administrators can simply paste any ERC-20 (EVM) or SPL (Solana) token contract address, and the plugin will automatically fetch token information from the blockchain.

## Features

### Supported Token Standards
- **ERC-20 Tokens** (Ethereum, Base, Polygon, Arbitrum, Optimism)
- **SPL Tokens** (Solana Mainnet, Devnet, Testnet)

### Auto-Detection Capabilities
- **Token Name** - Fetched from contract/mint metadata
- **Token Symbol** - Fetched from contract/mint metadata
- **Token Decimals** - Automatically detected for precise calculations
- **Network Auto-Detection** - Determines if token is EVM or Solana based on address format

## How to Use

### For Post/Page Authors

1. **Edit a Post or Page**
   - Navigate to Posts → Edit Post (or Pages → Edit Page)
   - Scroll to the **X402 Paywall Settings** meta box in the sidebar

2. **Enable Custom Token**
   - Check "Enable Paywall"
   - Select the appropriate Network Type (EVM or Solana)
   - Choose the network where your token resides
   - In the "Token" dropdown, select **"Custom Token (Enter Contract Address)"**

3. **Enter Contract Address**
   - A new section will appear
   - Paste your token's contract address (ERC-20) or mint address (SPL)
   - Click **"Auto-Detect Token Info"**

4. **Verify Detection**
   - The plugin will fetch token information from the blockchain
   - Token Name, Symbol, and Decimals will be displayed
   - If detection fails, verify:
     - Contract address is correct
     - Network matches where the token is deployed
     - RPC endpoint is accessible

5. **Set Payment Amount**
   - Enter the payment amount in human-readable format
   - The input will automatically adjust based on detected token decimals

6. **Publish**
   - Save or update your post
   - The custom token is now configured for this paywall

## Technical Details

### Token Detection Process

1. **Address Validation**
   - Solana addresses: Base58 format (32-44 characters)
   - EVM addresses: 0x-prefixed hex (42 characters)

2. **Network Detection**
   - Automatically determines if token is SPL or ERC-20
   - Falls back to network parameter if ambiguous

3. **Metadata Retrieval**

   **For SPL Tokens:**
   - Calls Solana RPC `getAccountInfo` method
   - Parses SPL token program data
   - Falls back to Jupiter token registry for additional metadata

   **For ERC-20 Tokens:**
   - Calls `eth_call` for `name()`, `symbol()`, `decimals()` functions
   - Uses standard ERC-20 function signatures:
     - `name()`: `0x06fdde03`
     - `symbol()`: `0x95d89b41`
     - `decimals()`: `0x313ce567`

4. **Caching**
   - Token information is cached for 1 hour using WordPress transients
   - Reduces redundant blockchain calls
   - Cache key format: `x402_token_{network}_{address}`

### Architecture

#### New Classes

**X402_Paywall_Token_Detector**
- Location: `includes/class-x402-paywall-token-detector.php`
- Purpose: Auto-detect token metadata from blockchain
- Key Methods:
  - `detect_token($contract_address, $network)` - Main detection method
  - `detect_spl_token($address, $network)` - SPL-specific detection
  - `detect_erc20_token($address, $network)` - ERC-20 specific detection
  - `fetch_spl_metadata($address, $network)` - Solana RPC calls
  - `fetch_erc20_metadata($address, $network)` - EVM RPC calls
  - `fetch_jupiter_token($address)` - Jupiter registry lookup

**X402_Paywall_SPL_Handler**
- Location: `includes/class-x402-paywall-spl-handler.php`
- Purpose: Handle SPL token operations
- Key Methods:
  - `get_token_balance($network, $wallet, $mint)` - Check SPL balance
  - `verify_transaction($network, $signature, ...)` - Verify SPL transfer
  - `get_token_metadata($network, $mint)` - Get SPL metadata
  - `call_rpc($network, $method, $params)` - Generic Solana RPC caller

#### Modified Classes

**X402_Paywall_Meta_Boxes**
- Added custom token UI section
- Added JavaScript for auto-detection button
- Updated `save_meta_box()` to handle custom tokens
- Stores custom token info in post meta:
  - `_x402_custom_token_address`
  - `_x402_custom_token_name`
  - `_x402_custom_token_symbol`
  - `_x402_custom_token_decimals`

**X402_Paywall_Admin**
- Added AJAX handler: `ajax_detect_token()`
- Verifies nonce and user capabilities
- Calls token detector and returns JSON response

**X402_Paywall**
- Updated `load_dependencies()` to include:
  - `class-x402-paywall-spl-handler.php`
  - `class-x402-paywall-token-detector.php`

### RPC Configuration

#### Default RPC Endpoints

**Solana:**
- Mainnet: `https://api.mainnet-beta.solana.com`
- Devnet: `https://api.devnet.solana.com`
- Testnet: `https://api.testnet.solana.com`

**EVM Networks:**
- Ethereum: `https://eth.llamarpc.com`
- Base: `https://mainnet.base.org`
- Polygon: `https://polygon-rpc.com`
- Arbitrum: `https://arb1.arbitrum.io/rpc`
- Optimism: `https://mainnet.optimism.io`

#### Custom RPC Endpoints

You can override RPC URLs using WordPress filters:

```php
// Custom Solana RPC URLs
add_filter('x402_solana_rpc_urls', function($urls, $network) {
    $urls[$network] = 'https://your-custom-solana-rpc.com';
    return $urls;
}, 10, 2);

// Custom EVM RPC URLs
add_filter('x402_evm_rpc_urls', function($urls, $network) {
    $urls[$network] = 'https://your-custom-evm-rpc.com';
    return $urls;
}, 10, 2);
```

## Error Handling

### Common Errors and Solutions

**"Invalid contract address format"**
- Verify the address is correctly formatted
- EVM: Must start with `0x` and be 42 characters
- Solana: Must be valid Base58 (typically 32-44 characters)

**"Failed to fetch token metadata from blockchain"**
- Check network connection
- Verify RPC endpoint is accessible
- Try again later (RPC rate limiting)

**"Network mismatch: Token exists on different network"**
- Verify the token is deployed on the selected network
- Check token documentation for correct network

**"Contract is not a valid token contract"**
- Ensure the address is for a token contract, not a wallet
- For EVM: Must implement ERC-20 interface
- For Solana: Must be a valid SPL token mint

**"Token detected but missing name/symbol"**
- Some tokens may not implement all metadata functions
- You may need to manually add the token to the registry

## Security Considerations

### Validation
- All contract addresses are sanitized before RPC calls
- Nonce verification prevents CSRF attacks
- User capability checks ensure only authorized users can add tokens
- Rate limiting on detection attempts (via WordPress AJAX)

### RPC Calls
- 15-second timeout prevents hanging requests
- Error responses are properly handled
- No sensitive data transmitted to RPC endpoints

### Data Storage
- Custom token data stored in post meta (not global registry)
- Each post maintains its own custom token configuration
- No permanent storage of unverified token data

## Integration with Payment Handler

Once a custom token is detected and configured:

1. **Payment Requirements**
   - Token address from custom or registry
   - Amount in atomic units (auto-converted)
   - Network and recipient address from author profile

2. **Payment Verification**
   - Uses SPL handler for Solana tokens
   - Uses standard EVM verification for ERC-20
   - Verifies transaction signature and amount

3. **Balance Checks**
   - SPL Handler: `get_token_balance()` method
   - ERC-20: Standard `balanceOf` call via RPC

## Performance Optimization

### Caching Strategy
- Token metadata cached for 1 hour
- Prevents repeated RPC calls for same token
- Cache cleared on plugin deactivation

### Lazy Loading
- Token detection only triggered on button click
- Not executed on every page load
- AJAX-based for non-blocking UI

## Future Enhancements

Potential improvements for future versions:

1. **Token Registry Cache**
   - Store successfully detected tokens in global registry
   - Reduce detection time for commonly used tokens

2. **Batch Detection**
   - Detect multiple tokens at once
   - Useful for authors managing many paywalled posts

3. **Token Validation**
   - Verify token contract source code
   - Check for malicious or scam tokens

4. **Price Oracle Integration**
   - Auto-convert USD amounts to token amounts
   - Real-time price updates from DEX aggregators

5. **Enhanced Metadata**
   - Fetch token logos and descriptions
   - Display additional token information

## Developer API

### Hooks

**Token Detection Filters:**

```php
// Modify detected token data
add_filter('x402_token_detected', function($token_data, $address, $network) {
    // Customize token data
    return $token_data;
}, 10, 3);

// Override RPC URLs
add_filter('x402_solana_rpc_urls', function($urls, $network) {
    return $urls;
}, 10, 2);

add_filter('x402_evm_rpc_urls', function($urls, $network) {
    return $urls;
}, 10, 2);
```

**Token Detection Actions:**

```php
// After successful detection
add_action('x402_token_detected', function($token_data, $address, $network) {
    // Log, notify, or process detected token
}, 10, 3);

// Before RPC call
add_action('x402_before_rpc_call', function($address, $network, $method) {
    // Prepare, log, or modify behavior before RPC
}, 10, 3);
```

### Direct Detection

You can use the token detector directly in your custom code:

```php
$detector = X402_Paywall_Token_Detector::get_instance();

// Detect any token
$token_info = $detector->detect_token($contract_address, $network);

// Detect SPL token specifically
$spl_info = $detector->detect_spl_token($mint_address, 'solana-mainnet');

// Detect ERC-20 token specifically
$erc20_info = $detector->detect_erc20_token($contract_address, 'base-mainnet');

// Check for errors
if (is_wp_error($token_info)) {
    echo $token_info->get_error_message();
} else {
    echo "Token: " . $token_info['name'];
    echo "Symbol: " . $token_info['symbol'];
    echo "Decimals: " . $token_info['decimals'];
}
```

## Testing

### Manual Testing

1. **Test ERC-20 Detection**
   - Use USDC on Base: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913`
   - Should detect: USDC, 6 decimals

2. **Test SPL Detection**
   - Use USDC on Solana: `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
   - Should detect: USD Coin, USDC, 6 decimals

3. **Test Invalid Addresses**
   - Try random string - should error
   - Try wallet address - should error (not a token)

### Automated Testing

Create a test suite for token detection:

```php
// Test SPL detection
$detector = X402_Paywall_Token_Detector::get_instance();
$usdc_solana = $detector->detect_spl_token(
    'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
    'solana-mainnet'
);
assert(!is_wp_error($usdc_solana));
assert($usdc_solana['symbol'] === 'USDC');
assert($usdc_solana['decimals'] === 6);

// Test ERC-20 detection
$usdc_base = $detector->detect_erc20_token(
    '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
    'base-mainnet'
);
assert(!is_wp_error($usdc_base));
assert($usdc_base['symbol'] === 'USDC');
assert($usdc_base['decimals'] === 6);
```

## Support

For issues or questions about token auto-detection:

1. Check that your RPC endpoints are accessible
2. Verify contract address format and network
3. Review WordPress debug log for RPC errors
4. Test with known tokens (USDC) first
5. Check plugin documentation and GitHub issues

## Changelog

### Version 1.0.0
- Initial release of token auto-detection feature
- Support for ERC-20 and SPL tokens
- Auto-detection UI in post meta boxes
- RPC integration with Solana and EVM networks
- Jupiter token registry integration for Solana
- Caching system for detected tokens
- AJAX-based detection for better UX
